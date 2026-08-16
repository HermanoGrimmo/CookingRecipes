<?php

declare(strict_types=1);

namespace App\Repository;

use App\Dto\RecipePage;
use App\Entity\Recipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * Bestehende, ungepaginierte Schnittstelle für interne Aufrufer.
     *
     * @return list<Recipe>
     */
    public function findFiltered(?string $search, ?string $difficulty, string $sortBy): array
    {
        /** @var list<Recipe> $recipes */
        $recipes = $this->createFilteredQuery($search, $difficulty, [], $sortBy)->getQuery()->getResult();

        return $recipes;
    }

    /**
     * Filtert und paginiert Rezepte. Mehrere Tag-IDs werden per ODER verknüpft.
     *
     * @param list<int> $tagIds
     */
    public function findFilteredPage(
        ?string $search,
        ?string $difficulty,
        array $tagIds,
        string $sortBy,
        int $page,
        int $pageSize = 20,
    ): RecipePage {
        $pageSize = max(1, $pageSize);
        $query = $this->createFilteredQuery($search, $difficulty, $tagIds, $sortBy)->getQuery();
        $paginator = new Paginator($query, true);
        $totalItems = \count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / $pageSize));
        $effectivePage = min(max(1, $page), $totalPages);
        $paginator->getQuery()
            ->setFirstResult(($effectivePage - 1) * $pageSize)
            ->setMaxResults($pageSize);

        /** @var list<Recipe> $items */
        $items = iterator_to_array($paginator->getIterator(), false);

        return new RecipePage($items, $totalItems, $effectivePage, $pageSize, $totalPages);
    }

    public function findOneBySourceUrl(string $sourceUrl): ?Recipe
    {
        return $this->findOneBy(['sourceUrl' => $sourceUrl]);
    }

    /**
     * @param list<int> $tagIds
     */
    private function createFilteredQuery(?string $search, ?string $difficulty, array $tagIds, string $sortBy): \Doctrine\ORM\QueryBuilder
    {
        $qb = $this->createQueryBuilder('r');

        if (null !== $search && '' !== trim($search)) {
            $escaped = addcslashes(strtolower(trim($search)), '\\%_');
            $qb->andWhere('LOWER(r.title) LIKE :search OR LOWER(r.description) LIKE :search')
                ->setParameter('search', '%' . $escaped . '%');
        }

        if (null !== $difficulty && \in_array($difficulty, ['einfach', 'mittel', 'schwer'], true)) {
            $qb->andWhere('r.difficulty = :difficulty')->setParameter('difficulty', $difficulty);
        }

        $normalizedTagIds = array_values(array_unique(array_filter($tagIds, static fn (int $id): bool => $id > 0)));
        if ([] !== $normalizedTagIds) {
            $qb->innerJoin('r.tags', 'filterTag')
                ->andWhere('filterTag.id IN (:tagIds)')
                ->setParameter('tagIds', $normalizedTagIds)
                ->distinct();
        }

        match ($sortBy) {
            'oldest' => $qb->orderBy('r.createdAt', 'ASC')->addOrderBy('r.id', 'ASC'),
            'title' => $qb->orderBy('r.title', 'ASC')->addOrderBy('r.id', 'ASC'),
            'time' => $qb->addSelect('(r.prepTime + r.cookTime) AS HIDDEN totalTime')->orderBy('totalTime', 'ASC')->addOrderBy('r.id', 'ASC'),
            default => $qb->orderBy('r.createdAt', 'DESC')->addOrderBy('r.id', 'DESC'),
        };

        return $qb;
    }
}
