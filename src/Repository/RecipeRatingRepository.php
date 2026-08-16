<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Recipe;
use App\Entity\RecipeRating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RecipeRating> */
final class RecipeRatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecipeRating::class);
    }

    public function findForUserAndRecipe(User $user, Recipe $recipe): ?RecipeRating
    {
        return $this->findOneBy(['user' => $user, 'recipe' => $recipe]);
    }

    /**
     * Lädt persönliche Bewertungen für eine Rezeptseite in einer Abfrage.
     *
     * @param list<Recipe> $recipes
     *
     * @return array<int, int> Rezept-ID zu Punktzahl
     */
    public function findScoresForUser(User $user, array $recipes): array
    {
        if ([] === $recipes) {
            return [];
        }

        /** @var list<array{recipeId: int, score: int}> $rows */
        $rows = $this->createQueryBuilder('rating')
            ->select('IDENTITY(rating.recipe) AS recipeId', 'rating.score AS score')
            ->andWhere('rating.user = :user')
            ->andWhere('rating.recipe IN (:recipes)')
            ->setParameter('user', $user)
            ->setParameter('recipes', $recipes)
            ->getQuery()
            ->getArrayResult();

        $scores = [];
        foreach ($rows as $row) {
            $scores[(int) $row['recipeId']] = (int) $row['score'];
        }

        return $scores;
    }
}
