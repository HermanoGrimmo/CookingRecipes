<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Sucht mehrere Tags case-insensitiv anhand ihrer Namen – in einer
     * einzigen Abfrage statt einer pro Name.
     *
     * @param list<string> $names
     *
     * @return list<Tag>
     */
    public function findByNames(array $names): array
    {
        if ([] === $names) {
            return [];
        }

        $lowered = array_map(static fn (string $name): string => mb_strtolower(trim($name)), $names);

        /** @var list<Tag> $tags */
        $tags = $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.name) IN (:names)')
            ->setParameter('names', $lowered)
            ->getQuery()
            ->getResult();

        return $tags;
    }
}
