<?php

declare(strict_types=1);

namespace App\Import\Exception;

use App\Entity\Recipe;

/**
 * Die Quell-URL wurde bereits importiert.
 *
 * Trägt das vorhandene Rezept mit, damit der Controller direkt darauf
 * verlinken kann, statt den Nutzer nur abzuweisen.
 */
final class RecipeAlreadyImportedException extends RecipeImportException
{
    public function __construct(public readonly Recipe $existingRecipe)
    {
        parent::__construct(\sprintf('Das Rezept "%s" wurde bereits importiert.', $existingRecipe->getTitle()));
    }
}
