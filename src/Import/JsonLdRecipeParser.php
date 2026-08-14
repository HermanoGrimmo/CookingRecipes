<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Dto\ImportedIngredient;
use App\Import\Dto\ImportedRecipe;
use App\Import\Dto\ImportedStep;
use App\Import\Exception\RecipeImportException;

/**
 * Liest ein Rezept aus den schema.org/Recipe-Daten (JSON-LD) einer HTML-Seite.
 *
 * Dient als Fallback, wenn die (undokumentierte) JSON-API einer Quelle nicht
 * mehr erreichbar ist. Beide unterstützten Seiten liefern vollständige
 * JSON-LD-Daten im server-gerenderten HTML.
 *
 * Die Zutaten stehen hier nur unstrukturiert zur Verfügung ("500 g Penne")
 * und werden heuristisch zerlegt – bewusst schlechter als der API-Pfad, aber
 * immer noch brauchbar, weil der Nutzer das Ergebnis ohnehin im Formular
 * prüft, bevor es gespeichert wird.
 */
final class JsonLdRecipeParser
{
    /**
     * Bekannte Mengeneinheiten, um sie vom Zutatennamen zu trennen.
     *
     * @var list<string>
     */
    private const array UNITS = [
        'mg', 'g', 'kg', 'ml', 'cl', 'dl', 'l', 'EL', 'TL', 'Msp.', 'Msp',
        'Prise', 'Prisen', 'Stück', 'Stk.', 'Stk', 'Bund', 'Zweig/e', 'Zweig',
        'Zweige', 'Zehe(n)', 'Zehe', 'Zehen', 'Dose', 'Dose(n)', 'Dosen',
        'Pck.', 'Pck', 'Packung', 'Packungen', 'Päckchen', 'Tasse', 'Tassen',
        'Glas', 'Gläser', 'Becher', 'Scheibe', 'Scheibe(n)', 'Scheiben',
        'Blatt', 'Blätter', 'Kugel', 'Kugeln', 'Handvoll', 'Portion',
        'Portionen', 'Liter', 'Gramm', 'Esslöffel', 'Teelöffel', 'Tropfen',
        'cm', 'Kopf', 'Köpfe', 'Stange', 'Stangen', 'Knolle', 'Knollen',
        'Würfel', 'Beutel', 'Flasche', 'Flaschen', 'Tube', 'Tuben',
    ];

    /**
     * Extrahiert das Rezept aus dem HTML einer Rezeptseite.
     *
     * @throws RecipeImportException wenn kein verwertbarer Recipe-Knoten gefunden wurde
     */
    public function parse(string $html, string $sourceUrl, string $sourceName): ImportedRecipe
    {
        [$recipe, $nodesById] = $this->findRecipeNode($html);

        if (null === $recipe) {
            throw new RecipeImportException(\sprintf('Auf "%s" wurden keine schema.org/Recipe-Daten gefunden.', $sourceUrl));
        }

        $title = trim($this->asString($recipe['name'] ?? ''));
        if ('' === $title) {
            throw new RecipeImportException(\sprintf('Die JSON-LD-Daten von "%s" enthalten keinen Titel.', $sourceUrl));
        }

        return new ImportedRecipe(
            title: $title,
            description: $this->nullIfBlank($this->asString($recipe['description'] ?? '')),
            author: $this->extractAuthor($recipe, $nodesById) ?? $sourceName,
            imageUrl: $this->extractImage($recipe, $nodesById),
            servings: $this->extractYield($recipe['recipeYield'] ?? null),
            prepTime: $this->durationToMinutes($recipe['prepTime'] ?? null),
            cookTime: $this->durationToMinutes($recipe['cookTime'] ?? null),
            restTime: 0,
            difficulty: 'einfach',
            ingredients: $this->extractIngredients($recipe['recipeIngredient'] ?? []),
            steps: $this->extractSteps($recipe['recipeInstructions'] ?? []),
            tags: $this->extractKeywords($recipe['keywords'] ?? []),
            sourceUrl: $sourceUrl,
            sourceName: $sourceName,
        );
    }

    /**
     * Sucht den Recipe-Knoten in allen ld+json-Blöcken der Seite.
     *
     * Beide Quellen betten ihn in einen @graph ein, andere Seiten liefern ihn
     * direkt oder in einem Array – alle drei Formen werden abgedeckt.
     *
     * Zusätzlich wird der Graph nach @id indiziert zurückgegeben: Chefkoch
     * verweist von Bild und Autor nur per Referenz auf andere Knoten.
     *
     * @return array{0: array<string, mixed>|null, 1: array<string, array<string, mixed>>}
     */
    private function findRecipeNode(string $html): array
    {
        $document = new \DOMDocument();

        // JSON-LD-Blöcke sind reine Textknoten; HTML-Fehler der Seite sind
        // dafür irrelevant und werden bewusst unterdrückt.
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, \LIBXML_NOERROR | \LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (false === $loaded) {
            return [null, []];
        }

        $nodes = (new \DOMXPath($document))->query('//script[@type="application/ld+json"]');
        if (false === $nodes) {
            return [null, []];
        }

        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            try {
                $decoded = json_decode($node->textContent, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                continue;
            }

            if (!\is_array($decoded)) {
                continue;
            }

            $candidates = $decoded['@graph'] ?? $decoded;
            if (!\is_array($candidates)) {
                continue;
            }

            $found = $this->firstRecipe($candidates);
            if (null !== $found) {
                return [$found, $this->indexById($candidates)];
            }
        }

        return [null, []];
    }

    /**
     * Indiziert alle Knoten des Graphen nach ihrer @id.
     *
     * @param array<mixed> $nodes
     *
     * @return array<string, array<string, mixed>>
     */
    private function indexById(array $nodes): array
    {
        $index = [];
        foreach ($nodes as $node) {
            if (\is_array($node) && \is_string($node['@id'] ?? null)) {
                /* @var array<string, mixed> $node */
                $index[$node['@id']] = $node;
            }
        }

        return $index;
    }

    /**
     * Löst einen Knoten auf, der nur aus einer @id-Referenz besteht.
     *
     * @param array<string, mixed>                $node
     * @param array<string, array<string, mixed>> $nodesById
     *
     * @return array<string, mixed>
     */
    private function resolveRef(array $node, array $nodesById): array
    {
        $id = $node['@id'] ?? null;

        if (\is_string($id) && isset($nodesById[$id]) && $nodesById[$id] !== $node) {
            return $nodesById[$id];
        }

        return $node;
    }

    /**
     * @param array<mixed> $candidates ein einzelner Knoten oder eine Liste
     *
     * @return array<string, mixed>|null
     */
    private function firstRecipe(array $candidates): ?array
    {
        if ($this->isRecipe($candidates)) {
            /* @var array<string, mixed> $candidates */
            return $candidates;
        }

        foreach ($candidates as $candidate) {
            if (\is_array($candidate) && $this->isRecipe($candidate)) {
                /* @var array<string, mixed> $candidate */
                return $candidate;
            }
        }

        return null;
    }

    /** @param array<mixed> $node */
    private function isRecipe(array $node): bool
    {
        $type = $node['@type'] ?? null;

        return 'Recipe' === $type || (\is_array($type) && \in_array('Recipe', $type, true));
    }

    /**
     * @param array<string, mixed>                $recipe
     * @param array<string, array<string, mixed>> $nodesById
     */
    private function extractAuthor(array $recipe, array $nodesById): ?string
    {
        $author = $recipe['author'] ?? null;

        if (\is_string($author)) {
            return $this->nullIfBlank($author);
        }

        if (\is_array($author)) {
            // Bei mehreren Autoren den ersten nehmen.
            if (isset($author[0]) && \is_array($author[0])) {
                $author = $author[0];
            }

            /** @var array<string, mixed> $author */
            $author = $this->resolveRef($author, $nodesById);

            return $this->nullIfBlank($this->asString($author['name'] ?? ''));
        }

        return null;
    }

    /**
     * @param array<string, mixed>                $recipe
     * @param array<string, array<string, mixed>> $nodesById
     */
    private function extractImage(array $recipe, array $nodesById): ?string
    {
        $image = $recipe['image'] ?? null;

        if (\is_string($image)) {
            return $this->nullIfBlank($image);
        }

        if (\is_array($image)) {
            if (isset($image[0])) {
                $image = $image[0];
            }

            if (\is_string($image)) {
                return $this->nullIfBlank($image);
            }

            if (\is_array($image)) {
                /** @var array<string, mixed> $image */
                $image = $this->resolveRef($image, $nodesById);
                $url = $this->asString($image['contentUrl'] ?? $image['url'] ?? '');

                return $this->nullIfBlank($url);
            }
        }

        return null;
    }

    /**
     * recipeYield kommt als int (4), String ("4 Portionen") oder Array
     * (["4", "4 Portionen"]) – überall die erste Zahl herausziehen.
     */
    private function extractYield(mixed $value): int
    {
        if (\is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (\is_int($value) || \is_float($value)) {
            return max(1, (int) $value);
        }

        if (\is_string($value) && 1 === preg_match('/\d+/', $value, $matches)) {
            return max(1, (int) $matches[0]);
        }

        return 4;
    }

    /**
     * Wandelt eine ISO-8601-Dauer in Minuten.
     *
     * Deckt beide real vorkommenden Schreibweisen ab: "PT15M" (Chefkoch) und
     * "P0Y0M0DT0H720M0S" (Foodboom).
     */
    private function durationToMinutes(mixed $value): int
    {
        if (!\is_string($value) || '' === trim($value)) {
            return 0;
        }

        try {
            $interval = new \DateInterval(trim($value));
        } catch (\Exception) {
            return 0;
        }

        return $interval->y * 525600
            + $interval->m * 43200
            + $interval->d * 1440
            + $interval->h * 60
            + $interval->i
            + (int) round($interval->s / 60);
    }

    /**
     * @return list<ImportedIngredient>
     */
    private function extractIngredients(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $ingredients = [];
        foreach ($value as $line) {
            if (!\is_string($line)) {
                continue;
            }

            $line = trim($line);
            if ('' === $line) {
                continue;
            }

            $ingredients[] = $this->parseIngredientLine($line);
        }

        return $ingredients;
    }

    /**
     * Zerlegt "500 g Penne" heuristisch in Menge, Einheit und Name.
     *
     * Ohne führende Zahl (z. B. "Salz und Pfeffer") bleibt alles der Name.
     */
    private function parseIngredientLine(string $line): ImportedIngredient
    {
        $units = implode('|', array_map(static fn (string $u): string => preg_quote($u, '/'), self::UNITS));

        $pattern = '/^(?<amount>\d+(?:[.,]\d+)?(?:\s*[-–\/]\s*\d+(?:[.,]\d+)?)?)?\s*(?<unit>' . $units . ')?\s*(?<name>.+)$/u';

        if (1 !== preg_match($pattern, $line, $matches)) {
            return new ImportedIngredient(null, null, $line);
        }

        $amount = $this->nullIfBlank($matches['amount'] ?? null);
        $unit = $this->nullIfBlank($matches['unit'] ?? null);
        $name = trim($matches['name'] ?? $line);

        if ('' === $name) {
            return new ImportedIngredient(null, null, $line);
        }

        return new ImportedIngredient($amount, $unit, $name);
    }

    /**
     * recipeInstructions tritt in vier Formen auf: als String, als Liste von
     * Strings (Foodboom), als HowToStep-Liste und als HowToSection mit
     * verschachtelten Schritten (Chefkoch).
     *
     * @return list<ImportedStep>
     */
    private function extractSteps(mixed $value): array
    {
        if (\is_string($value)) {
            return $this->splitTextIntoSteps($value);
        }

        if (!\is_array($value)) {
            return [];
        }

        $steps = [];
        foreach ($value as $entry) {
            if (\is_string($entry)) {
                $text = trim($entry);
                if ('' !== $text) {
                    $steps[] = new ImportedStep($text);
                }
                continue;
            }

            if (!\is_array($entry)) {
                continue;
            }

            if (isset($entry['itemListElement']) && \is_array($entry['itemListElement'])) {
                foreach ($this->extractSteps($entry['itemListElement']) as $nested) {
                    $steps[] = $nested;
                }
                continue;
            }

            $text = trim($this->asString($entry['text'] ?? $entry['name'] ?? ''));
            if ('' !== $text) {
                $steps[] = new ImportedStep($text);
            }
        }

        return $steps;
    }

    /**
     * @return list<ImportedStep>
     */
    private function splitTextIntoSteps(string $text): array
    {
        $parts = preg_split('/\R{2,}/u', trim($text)) ?: [];

        $steps = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ('' !== $part) {
                $steps[] = new ImportedStep($part);
            }
        }

        return $steps;
    }

    /**
     * keywords kommt als kommaseparierter String (Chefkoch) oder als Liste
     * (Foodboom).
     *
     * @return list<string>
     */
    private function extractKeywords(mixed $value): array
    {
        if (\is_string($value)) {
            $value = explode(',', $value);
        }

        if (!\is_array($value)) {
            return [];
        }

        $keywords = [];
        foreach ($value as $keyword) {
            if (!\is_string($keyword)) {
                continue;
            }

            $keyword = trim($keyword);
            if ('' !== $keyword) {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }

    private function asString(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
