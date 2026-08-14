<?php

declare(strict_types=1);

namespace App\Import\Exception;

/**
 * Für die übergebene URL gibt es keinen zuständigen Importer.
 */
final class UnsupportedSourceException extends RecipeImportException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct(\sprintf('Für die URL "%s" gibt es keinen passenden Importer.', $url));
    }
}
