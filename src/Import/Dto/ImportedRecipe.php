<?php

declare(strict_types=1);

namespace App\Import\Dto;

/**
 * Ein Rezept, wie es aus einer externen Quelle gelesen wurde.
 *
 * Reines Transportobjekt zwischen Importer und RecipeImportService – enthält
 * keine Doctrine-Abhängigkeit und ist damit in Unit-Tests frei konstruierbar.
 */
final readonly class ImportedRecipe
{
    /**
     * @param list<ImportedIngredient> $ingredients
     * @param list<ImportedStep>       $steps
     * @param list<string>             $tags
     */
    public function __construct(
        public string $title,
        public ?string $description,
        /** Name des Original-Autors bei der Quelle */
        public string $author,
        /** Absolute URL des Titelbilds (wird als Remote-URL gespeichert) */
        public ?string $imageUrl,
        public int $servings,
        /** Zubereitungszeit in Minuten */
        public int $prepTime,
        /** Kochzeit in Minuten */
        public int $cookTime,
        /** Ruhe-/Einweichzeit in Minuten */
        public int $restTime,
        /** einfach|mittel|schwer */
        public string $difficulty,
        public array $ingredients,
        public array $steps,
        public array $tags,
        /** Normalisierte URL des Original-Rezepts */
        public string $sourceUrl,
        /** Anzeigename der Quelle, z. B. "Chefkoch" */
        public string $sourceName,
    ) {
    }
}
