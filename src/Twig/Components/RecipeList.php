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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
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

    /** @var list<mixed> Rohwerte des Mehrfach-Selects */
    #[LiveProp(writable: true, onUpdated: 'normalizeTagIds')]
    public array $tagIds = [];

    #[LiveProp]
    public int $page = 1;

    private ?RecipePage $recipePage = null;

    public function __construct(
        private readonly RecipeRepository $recipeRepository,
        private readonly TagRepository $tagRepository,
        private readonly RecipeRatingRepository $ratingRepository,
        private readonly Security $security,
    ) {
    }

    public function getRecipePage(): RecipePage
    {
        return $this->recipePage ??= $this->loadRecipePage($this->page);
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
        $this->recipePage = $this->loadRecipePage(max(1, $page));
        $this->page = $this->recipePage->page;
    }

    public function resetPage(mixed $previousValue = null): void
    {
        $this->page = 1;
        $this->recipePage = null;
    }

    public function normalizeTagIds(mixed $previousValue = null): void
    {
        if (\count($this->tagIds) > 50) {
            throw new BadRequestHttpException('Es dürfen höchstens 50 Tags ausgewählt werden.');
        }

        $normalized = [];
        foreach ($this->tagIds as $tagId) {
            if ((!\is_int($tagId) && (!\is_string($tagId) || !ctype_digit($tagId))) || (int) $tagId < 1) {
                throw new BadRequestHttpException('Tag-IDs müssen positive ganze Zahlen sein.');
            }
            $normalized[] = (int) $tagId;
        }

        $this->tagIds = array_values(array_unique($normalized));
        $this->resetPage($previousValue);
    }

    private function loadRecipePage(int $page): RecipePage
    {
        return $this->recipeRepository->findFilteredPage(
            '' === $this->search ? null : $this->search,
            '' === $this->difficulty ? null : $this->difficulty,
            array_map('intval', $this->tagIds),
            $this->sortBy,
            $page,
        );
    }
}
