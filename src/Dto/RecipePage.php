<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\Recipe;

/** Unveränderliches Ergebnis einer paginierten Rezeptabfrage. */
final readonly class RecipePage
{
    /**
     * @param list<Recipe> $items
     */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $page,
        public int $pageSize,
        public int $totalPages,
    ) {
    }
}
