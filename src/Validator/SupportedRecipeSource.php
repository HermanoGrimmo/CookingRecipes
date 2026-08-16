<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Die URL muss von einer Quelle stammen, für die ein Importer existiert.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SupportedRecipeSource extends Constraint
{
    public string $message = 'Diese Seite wird nicht unterstützt. Möglich sind aktuell: {{ sources }}.';
}
