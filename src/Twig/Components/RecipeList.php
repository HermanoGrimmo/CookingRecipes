<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Dto\RecipePage;
use App\Entity\Recipe;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\RecipeRatingRepository;
use App\Repository\RecipeRepository;
use App\Repository\TagRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/** Live Component für Suche, Tag-Filter, Sortierung und Pagination. */
#[AsLiveComponent]
final class RecipeList
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $search = '';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $difficulty = '';

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public string $sortBy = 'newest';

    /** @var list<int> */
    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public array $tagIds = [];

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly RecipeRepository $recipeRepository,
        private readonly TagRepository $tagRepository,
        private readonly RecipeRatingRepository $ratingRepository,
        private readonly Security $security,
    ) {
    }

    public function getRecipePage(): RecipePage
    {
        $result = $this->recipeRepository->findFilteredPage(
            '' === $this->search ? null : $this->search,
            '' === $this->difficulty ? null : $this->difficulty,
            array_map('intval', $this->tagIds),
            $this->sortBy,
            $this->page,
        );
        $this->page = $result->page;

        return $result;
    }

    /** @return list<Tag> */
    public function getAvailableTags(): array
    {
        /** @var list<Tag> $tags */
        $tags = $this->tagRepository->findBy([], ['name' => 'ASC']);

        return $tags;
    }

    /**
     * @param list<Recipe> $recipes
     *
     * @return array<int, int>
     */
    public function personalRatings(array $recipes): array
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $this->ratingRepository->findScoresForUser($user, $recipes) : [];
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, $page);
    }

    public function resetPage(): void
    {
        $this->page = 1;
    }
}
