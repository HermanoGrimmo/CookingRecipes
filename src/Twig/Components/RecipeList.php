<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live Component für die Rezeptübersicht mit reaktiver Suche, Filter
 * und Sortierung – ohne Page-Reload.
 */
#[AsLiveComponent]
final class RecipeList
{
    use DefaultActionTrait;

    /** Freitextsuche auf Titel und Beschreibung. */
    #[LiveProp(writable: true)]
    public string $search = '';

    /** Optionaler Schwierigkeits-Filter (Werte: '', 'einfach', 'mittel', 'schwer'). */
    #[LiveProp(writable: true)]
    public string $difficulty = '';

    /** Sortierung: newest | oldest | title | time. */
    #[LiveProp(writable: true)]
    public string $sortBy = 'newest';

    public function __construct(private readonly RecipeRepository $recipeRepository)
    {
    }

    /**
     * Liefert die gefilterten und sortierten Rezepte.
     *
     * @return Recipe[]
     */
    public function getRecipes(): array
    {
        return $this->recipeRepository->findFiltered(
            '' === $this->search ? null : $this->search,
            '' === $this->difficulty ? null : $this->difficulty,
            $this->sortBy,
        );
    }
}
