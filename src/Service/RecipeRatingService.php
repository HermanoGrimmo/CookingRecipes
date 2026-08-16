<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Recipe;
use App\Entity\RecipeRating;
use App\Entity\User;
use App\Repository\RecipeRatingRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/** Schreibt Einzelbewertungen und erneuert die gecachten Rezeptaggregate. */
final readonly class RecipeRatingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RecipeRatingRepository $ratingRepository,
    ) {
    }

    public function rate(Recipe $recipe, User $user, int $score): void
    {
        if ($score < 1 || $score > 5) {
            throw new \InvalidArgumentException('Eine Bewertung muss zwischen 1 und 5 liegen.');
        }

        $this->entityManager->wrapInTransaction(function () use ($recipe, $user, $score): void {
            $this->entityManager->lock($recipe, LockMode::PESSIMISTIC_WRITE);
            $rating = $this->ratingRepository->findForUserAndRecipe($user, $recipe);
            if (null === $rating) {
                $rating = new RecipeRating($recipe, $user, $score);
                $this->entityManager->persist($rating);
            } else {
                $rating->setScore($score);
            }

            $this->entityManager->flush();
            $this->refreshAggregate($recipe);
        });
    }

    public function personalScore(Recipe $recipe, ?User $user): ?int
    {
        return null === $user ? null : $this->ratingRepository->findForUserAndRecipe($user, $recipe)?->getScore();
    }

    public function remove(Recipe $recipe, User $user): void
    {
        $this->entityManager->wrapInTransaction(function () use ($recipe, $user): void {
            $this->entityManager->lock($recipe, LockMode::PESSIMISTIC_WRITE);
            $rating = $this->ratingRepository->findForUserAndRecipe($user, $recipe);
            if (null !== $rating) {
                $this->entityManager->remove($rating);
                $this->entityManager->flush();
            }

            $this->refreshAggregate($recipe);
        });
    }

    private function refreshAggregate(Recipe $recipe): void
    {
        /** @var array{average: string|null, ratingCount: int|string} $aggregate */
        $aggregate = $this->entityManager->createQueryBuilder()
            ->select('AVG(rating.score) AS average', 'COUNT(rating.id) AS ratingCount')
            ->from(RecipeRating::class, 'rating')
            ->andWhere('rating.recipe = :recipe')
            ->setParameter('recipe', $recipe)
            ->getQuery()
            ->getSingleResult();

        $count = (int) $aggregate['ratingCount'];
        $average = 0 === $count ? '0.0' : number_format((float) $aggregate['average'], 1, '.', '');
        $recipe->setRating($average)->setRatingCount($count);
        $this->entityManager->flush();
    }
}
