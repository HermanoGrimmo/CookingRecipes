<?php

declare(strict_types=1);

namespace App\Import;

use App\Import\Dto\ImportedRecipe;
use App\Import\Exception\RecipeImportException;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Liest ein Rezept von einer bestimmten Quelle.
 *
 * Implementierungen werden automatisch getaggt und vom RecipeImportService
 * eingesammelt. Eine neue Quelle (oder eine Extraktion über einen externen
 * Service) ist damit eine zusätzliche Klasse, ohne Änderung am Import-Flow.
 */
#[AutoconfigureTag('app.recipe_importer')]
interface RecipeImporterInterface
{
    /** Anzeigename der Quelle, z. B. "Chefkoch". */
    public function getSourceName(): string;

    /** Ob dieser Importer für die (bereits normalisierte) URL zuständig ist. */
    public function supports(string $url): bool;

    /**
     * Holt das Rezept und gibt es als DTO zurück.
     *
     * @throws RecipeImportException bei Transport-, Parse- oder Mapping-Fehlern
     */
    public function fetch(string $url): ImportedRecipe;
}
