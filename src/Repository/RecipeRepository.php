<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Recipe>
 */
class RecipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recipe::class);
    }

    /** @return Recipe[] */
    public function findAllOrderedByNewest(): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Filtert Rezepte nach Suchbegriff (Titel/Beschreibung), Schwierigkeitsgrad
     * und Sortierung. Wird vom RecipeList Live Component aufgerufen.
     *
     * @param string|null $search     suchbegriff (LIKE auf Titel und Beschreibung)
     * @param string|null $difficulty optionaler Schwierigkeits-Filter (einfach|mittel|schwer)
     * @param string      $sortBy     sortierung: newest|oldest|title|time
     *
     * @return Recipe[]
     */
    public function findFiltered(?string $search, ?string $difficulty, string $sortBy): array
    {
        $qb = $this->createQueryBuilder('r');

        if (null !== $search && '' !== trim($search)) {
            $qb->andWhere('LOWER(r.title) LIKE :search OR LOWER(r.description) LIKE :search')
                ->setParameter('search', '%' . strtolower(trim($search)) . '%');
        }

        if (null !== $difficulty && '' !== $difficulty) {
            $qb->andWhere('r.difficulty = :difficulty')
                ->setParameter('difficulty', $difficulty);
        }

        match ($sortBy) {
            'oldest' => $qb->orderBy('r.createdAt', 'ASC'),
            'title' => $qb->orderBy('r.title', 'ASC'),
            'time' => $qb->orderBy('(r.prepTime + r.cookTime)', 'ASC'),
            default => $qb->orderBy('r.createdAt', 'DESC'),
        };

        /** @var Recipe[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
