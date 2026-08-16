<?php

declare(strict_types=1);

namespace App\Import\Dto;

/**
 * Ein Zubereitungsschritt, wie er aus einer externen Quelle gelesen wurde.
 */
final readonly class ImportedStep
{
    public function __construct(
        public string $description,
        /** Optionale Überschrift, z. B. "Tipp" */
        public ?string $title = null,
    ) {
    }
}
