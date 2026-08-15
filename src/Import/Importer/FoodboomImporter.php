<?php

declare(strict_types=1);

namespace App\Import\Importer;

use App\Import\Dto\ImportedIngredient;
use App\Import\Dto\ImportedRecipe;
use App\Import\Dto\ImportedStep;
use App\Import\Exception\RecipeImportException;
use App\Import\JsonLdRecipeParser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Importer für foodboom.de.
 *
 * Nutzt die öffentliche JSON-API (api.foodboom.de/v3/recipes/{slug}), die die
 * Zutaten gruppiert und mit Singular-/Plural-Formen liefert. Der Slug steht
 * in der URL. Ist die API nicht erreichbar, greift der JSON-LD-Fallback auf
 * der HTML-Seite.
 */
final class FoodboomImporter extends AbstractRecipeImporter
{
    private const string SOURCE_NAME = 'FOODBOOM';

    private const string URL_PATTERN = '#^https://(?:www\.)?foodboom\.de/rezept/([a-z0-9-]+)#i';

    /** Foodboom liefert keine Schwierigkeit – der Nutzer korrigiert im Formular. */
    private const string DEFAULT_DIFFICULTY = 'einfach';

    public function __construct(
        #[Autowire(service: 'foodboom.client')]
        HttpClientInterface $client,
        private readonly JsonLdRecipeParser $jsonLdParser,
    ) {
        parent::__construct($client);
    }

    public function getSourceName(): string
    {
        return self::SOURCE_NAME;
    }

    public function supports(string $url): bool
    {
        return 1 === preg_match(self::URL_PATTERN, $url);
    }

    public function fetch(string $url): ImportedRecipe
    {
        if (1 !== preg_match(self::URL_PATTERN, $url, $matches)) {
            throw new RecipeImportException(\sprintf('"%s" ist keine gültige FOODBOOM-Rezept-URL.', $url));
        }

        try {
            $response = $this->fetchJson('https://api.foodboom.de/v3/recipes/' . $matches[1]);
        } catch (RecipeImportException) {
            return $this->jsonLdParser->parse($this->fetchHtml($url), $url, self::SOURCE_NAME);
        }

        $data = $response['data'] ?? null;
        if (!\is_array($data)) {
            throw new RecipeImportException(\sprintf('Die FOODBOOM-Antwort für "%s" enthält keine Rezeptdaten.', $url));
        }

        /* @var array<string, mixed> $data */
        return $this->map($data, $url);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function map(array $data, string $url): ImportedRecipe
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ('' === $title) {
            throw new RecipeImportException(\sprintf('Die FOODBOOM-Antwort für "%s" enthält keinen Titel.', $url));
        }

        return new ImportedRecipe(
            title: $title,
            description: $this->nullIfBlank($this->htmlToText(\is_string($data['introText'] ?? null) ? $data['introText'] : null)),
            author: $this->extractAuthor($data),
            imageUrl: $this->extractImage($data),
            servings: $this->extractServings($data),
            // Foodboom kennt keine getrennte Zubereitungszeit; cookTime ist die
            // aktive Zeit, waitTime das Einweichen/Gehenlassen.
            prepTime: 0,
            cookTime: max(0, (int) ($data['cookTime'] ?? 0)),
            restTime: max(0, (int) ($data['waitTime'] ?? 0)),
            difficulty: self::DEFAULT_DIFFICULTY,
            ingredients: $this->extractIngredients($data),
            steps: $this->extractSteps($data),
            tags: $this->extractTags($data),
            sourceUrl: $url,
            sourceName: self::SOURCE_NAME,
        );
    }

    /** @param array<string, mixed> $data */
    private function extractAuthor(array $data): string
    {
        $foodstylist = $data['foodstylist'] ?? null;
        if (\is_array($foodstylist)) {
            $name = $this->nullIfBlank((string) ($foodstylist['name'] ?? ''));
            if (null !== $name) {
                return $name;
            }
        }

        return self::SOURCE_NAME;
    }

    /**
     * Baut die Bild-URL über den Cloudflare-Images-Endpunkt zusammen.
     *
     * headerImage.url ist nur der Schlüssel; focalPointX/Y (in Prozent)
     * steuern den Bildausschnitt.
     *
     * @param array<string, mixed> $data
     */
    private function extractImage(array $data): ?string
    {
        $image = $data['headerImage'] ?? null;
        if (!\is_array($image)) {
            return null;
        }

        $key = $this->nullIfBlank((string) ($image['url'] ?? ''));
        if (null === $key) {
            return null;
        }

        $gravityX = round(((float) ($image['focalPointX'] ?? 50)) / 100, 2);
        $gravityY = round(((float) ($image['focalPointY'] ?? 50)) / 100, 2);

        return \sprintf(
            'https://images.foodboom.de/cdn-cgi/image/f=auto,g=%sx%s,fit=cover,w=1200,h=960/%s',
            $gravityX,
            $gravityY,
            $key,
        );
    }

    /** @param array<string, mixed> $data */
    private function extractServings(array $data): int
    {
        $servings = $data['servings'] ?? null;
        if (\is_array($servings)) {
            return max(1, (int) ($servings['amount'] ?? 4));
        }

        return max(1, (int) ($servings ?? 4));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ImportedIngredient>
     */
    private function extractIngredients(array $data): array
    {
        $groups = $data['ingredientGroups'] ?? [];
        if (!\is_array($groups)) {
            return [];
        }

        $ingredients = [];
        foreach ($groups as $group) {
            if (!\is_array($group) || !\is_array($group['ingredients'] ?? null)) {
                continue;
            }

            $groupName = $this->nullIfBlank((string) ($group['title'] ?? ''));

            foreach ($group['ingredients'] as $ingredient) {
                if (!\is_array($ingredient)) {
                    continue;
                }

                $quantity = isset($ingredient['quantity']) ? (float) $ingredient['quantity'] : null;
                $name = $this->extractIngredientName($ingredient, $quantity);
                if ('' === $name) {
                    continue;
                }

                $ingredients[] = new ImportedIngredient(
                    amount: $this->formatAmount($quantity),
                    unit: $this->extractUnit($ingredient),
                    name: $name,
                    groupName: $groupName,
                );
            }
        }

        return $ingredients;
    }

    /**
     * Wählt Singular oder Plural und hängt Attribute wie "getrocknet" an.
     *
     * @param array<string, mixed> $ingredient
     */
    private function extractIngredientName(array $ingredient, ?float $quantity): string
    {
        $title = $ingredient['title'] ?? null;
        if (!\is_array($title)) {
            return trim((string) ($title ?? ''));
        }

        $singular = trim((string) ($title['one'] ?? ''));
        $plural = trim((string) ($title['many'] ?? ''));

        $name = (null !== $quantity && $quantity > 1.0 && '' !== $plural) ? $plural : ($singular ?: $plural);
        if ('' === $name) {
            return '';
        }

        $attributes = $ingredient['attributes'] ?? [];
        if (\is_array($attributes)) {
            $extras = [];
            foreach ($attributes as $attribute) {
                if (\is_string($attribute) && '' !== trim($attribute)) {
                    $extras[] = trim($attribute);
                }
            }

            if ([] !== $extras) {
                $name .= ', ' . implode(', ', $extras);
            }
        }

        return $name;
    }

    /**
     * Zählbare Zutaten haben ein leeres Einheiten-Objekt.
     *
     * @param array<string, mixed> $ingredient
     */
    private function extractUnit(array $ingredient): ?string
    {
        $unit = $ingredient['unit'] ?? null;
        if (!\is_array($unit) || [] === $unit) {
            return null;
        }

        return $this->nullIfBlank((string) ($unit['abbr'] ?? $unit['one'] ?? ''));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<ImportedStep>
     */
    private function extractSteps(array $data): array
    {
        $rawSteps = $data['preparationSteps'] ?? [];
        $steps = [];

        if (\is_array($rawSteps)) {
            // Nach "order" sortieren – die API garantiert die Reihenfolge nicht.
            usort($rawSteps, static fn (mixed $a, mixed $b): int => ((int) (\is_array($a) ? ($a['order'] ?? 0) : 0)) <=> ((int) (\is_array($b) ? ($b['order'] ?? 0) : 0)));

            foreach ($rawSteps as $step) {
                if (!\is_array($step)) {
                    continue;
                }

                $text = $this->htmlToText(\is_string($step['body'] ?? null) ? $step['body'] : null);
                if ('' !== $text) {
                    $steps[] = new ImportedStep($text);
                }
            }
        }

        // Der redaktionelle Tipp gehört zum Rezept, hat aber kein eigenes Feld
        // in der Anwendung – daher als abschließender Schritt.
        $tip = $this->htmlToText(\is_string($data['tip'] ?? null) ? $data['tip'] : null);
        if ('' !== $tip) {
            $steps[] = new ImportedStep($tip, 'Tipp');
        }

        return $steps;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return list<string>
     */
    private function extractTags(array $data): array
    {
        $tags = $data['tags'] ?? [];
        if (!\is_array($tags)) {
            return [];
        }

        $result = [];
        foreach ($tags as $tag) {
            if (\is_string($tag) && '' !== trim($tag)) {
                $result[] = trim($tag);
            }
        }

        return $result;
    }
}
