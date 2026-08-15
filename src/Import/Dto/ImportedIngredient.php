<?php

declare(strict_types=1);

namespace App\Import\Dto;

/**
 * Eine Zutat, wie sie aus einer externen Quelle gelesen wurde.
 *
 * Bewusst entkoppelt von App\Entity\Ingredient: Die Importer kennen nur
 * dieses DTO, das Mapping auf die Entität passiert an einer Stelle im
 * RecipeImportService.
 */
final readonly class ImportedIngredient
{
    public function __construct(
        /** Menge als Anzeigetext, z. B. "500" oder "0,5" – NULL bei "Salz und Pfeffer" */
        public ?string $amount,
        /** Einheit, z. B. "g", "EL", "Zweig/e" – NULL bei zählbaren Zutaten */
        public ?string $unit,
        public string $name,
        /** Überschrift der Zutatengruppe, z. B. "Für das Dal:" – NULL wenn ungruppiert */
        public ?string $groupName = null,
    ) {
    }
}
