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
 * Importer für chefkoch.de.
 *
 * Nutzt die öffentliche JSON-API (api.chefkoch.de/v2/recipes/{id}), die die
 * Zutaten bereits in Menge/Einheit/Name und Gruppen zerlegt liefert. Die
 * Rezept-ID steht in der URL. Ist die API nicht erreichbar, greift der
 * JSON-LD-Fallback auf der HTML-Seite.
 */
final class ChefkochImporter extends AbstractRecipeImporter
{
    private const string SOURCE_NAME = 'Chefkoch';

    /** Bildformat aus previewImageUrlTemplate; als "<format>" eingesetzt. */
    private const string IMAGE_FORMAT = 'crop-960x540';

    /** Chefkoch-Schwierigkeit (1–3) auf die Werte der Anwendung abbilden. */
    private const array DIFFICULTY_MAP = [
        1 => 'einfach',
        2 => 'mittel',
        3 => 'schwer',
    ];

    // "/|$" statt nur "/": RecipeImportService::normalizeUrl() entfernt einen
    // abschließenden Slash, eine (seltene) slug-lose URL wie ".../rezepte/123"
    // muss also auch ohne folgenden Slash erkannt werden.
    private const string URL_PATTERN = '#^https://(?:www\.)?chefkoch\.de/rezepte/(\d+)(?:/|$)#i';

    public function __construct(
        #[Autowire(service: 'chefkoch.client')]
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
            throw new RecipeImportException(\sprintf('"%s" ist keine gültige Chefkoch-Rezept-URL.', $url));
        }

        try {
            $data = $this->fetchJson('https://api.chefkoch.de/v2/recipes/' . $matches[1]);
        } catch (RecipeImportException) {
            // Die API ist undokumentiert und kann jederzeit wegfallen – dann
            // die strukturierten Daten aus dem HTML der Seite lesen.
            return $this->jsonLdParser->parse($this->fetchHtml($url), $url, self::SOURCE_NAME);
        }

        return $this->map($data, $url);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function map(array $data, string $url): ImportedRecipe
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ('' === $title) {
            throw new RecipeImportException(\sprintf('Die Chefkoch-Antwort für "%s" enthält keinen Titel.', $url));
        }

        $difficulty = self::DIFFICULTY_MAP[$data['difficulty'] ?? 1] ?? 'einfach';

        return new ImportedRecipe(
            title: $title,
            description: $this->nullIfBlank((string) ($data['subtitle'] ?? '')) ?? $this->nullIfBlank((string) ($data['additionalDescription'] ?? '')),
            author: $this->extractAuthor($data),
            imageUrl: $this->extractImage($data),
            servings: max(1, (int) ($data['servings'] ?? 4)),
            prepTime: max(0, (int) ($data['preparationTime'] ?? 0)),
            cookTime: max(0, (int) ($data['cookingTime'] ?? 0)),
            restTime: max(0, (int) ($data['restingTime'] ?? 0)),
            difficulty: $difficulty,
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
        $owner = $data['owner'] ?? null;
        if (!\is_array($owner)) {
            return self::SOURCE_NAME;
        }

        $name = $this->nullIfBlank((string) ($owner['displayName'] ?? ''))
            ?? $this->nullIfBlank((string) ($owner['username'] ?? ''));

        return $name ?? self::SOURCE_NAME;
    }

    /** @param array<string, mixed> $data */
    private function extractImage(array $data): ?string
    {
        if (true !== ($data['hasImage'] ?? false)) {
            return null;
        }

        $template = $this->nullIfBlank((string) ($data['previewImageUrlTemplate'] ?? ''));
        if (null === $template) {
            return null;
        }

        return str_replace('<format>', self::IMAGE_FORMAT, $template);
    }

    /**
     * Die API liefert die Zutaten bereits gruppiert und zerlegt.
     *
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

            // Ungruppierte Rezepte liefern ein einzelnes Leerzeichen als Header.
            $groupName = $this->nullIfBlank((string) ($group['header'] ?? ''));

            foreach ($group['ingredients'] as $ingredient) {
                if (!\is_array($ingredient)) {
                    continue;
                }

                $name = trim((string) ($ingredient['name'] ?? ''));
                if ('' === $name) {
                    continue;
                }

                // usageInfo ist ein Zusatz wie "oder 1/2 TL getrockneter".
                $usageInfo = $this->nullIfBlank((string) ($ingredient['usageInfo'] ?? ''));
                if (null !== $usageInfo) {
                    $name .= ' (' . $usageInfo . ')';
                }

                $ingredients[] = new ImportedIngredient(
                    amount: $this->formatAmount(isset($ingredient['amount']) ? (float) $ingredient['amount'] : null),
                    unit: $this->nullIfBlank((string) ($ingredient['unit'] ?? '')),
                    name: $name,
                    groupName: $groupName,
                );
            }
        }

        return $ingredients;
    }

    /**
     * Die API liefert die Zubereitung als einen Textblock; die einzelnen
     * Schritte sind darin durch Leerzeilen getrennt.
     *
     * @param array<string, mixed> $data
     *
     * @return list<ImportedStep>
     */
    private function extractSteps(array $data): array
    {
        $instructions = trim((string) ($data['instructions'] ?? ''));
        if ('' === $instructions) {
            return [];
        }

        // Genau zwei aufeinanderfolgende Umbrüche trennen die Schritte. Ein
        // "\n \n" (Umbruch, Leerzeichen, Umbruch) ist dagegen ein Absatz
        // INNERHALB eines Schrittes – Chefkoch selbst trennt im JSON-LD ebenso.
        $parts = preg_split('/\R{2,}/u', $instructions) ?: [];

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
