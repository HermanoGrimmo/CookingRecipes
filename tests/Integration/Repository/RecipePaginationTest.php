<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Recipe;
use App\Entity\Tag;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Integrationstests für Tag-Filter und stabile Pagination. */
final class RecipePaginationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RecipeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->repository = static::getContainer()->get(RecipeRepository::class);
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM recipe_rating');
        $connection->executeStatement('DELETE FROM recipe_tag');
        $connection->executeStatement('DELETE FROM ingredient');
        $connection->executeStatement('DELETE FROM step');
        $connection->executeStatement('DELETE FROM recipe');
        $connection->executeStatement('DELETE FROM tag');
        parent::tearDown();
    }

    public function testMehrereTagsWerdenMitOderVerknuepftUndNichtDupliziert(): void
    {
        $vegan = new Tag('Vegan');
        $schnell = new Tag('Schnell');
        $this->em->persist($vegan);
        $this->em->persist($schnell);
        $this->createRecipe('Beides')->addTag($vegan)->addTag($schnell);
        $this->createRecipe('Nur vegan')->addTag($vegan);
        $this->createRecipe('Ohne Tag');
        $this->em->flush();

        $veganId = $vegan->getId();
        $schnellId = $schnell->getId();
        self::assertNotNull($veganId);
        self::assertNotNull($schnellId);
        $page = $this->repository->findFilteredPage(null, null, [$veganId, $schnellId], 'title', 1, 20);

        self::assertSame(2, $page->totalItems);
        self::assertSame(['Beides', 'Nur vegan'], array_map(static fn (Recipe $recipe): string => $recipe->getTitle(), $page->items));
    }

    public function testPaginationIstStabilUndNormalisiertUngueltigeSeiten(): void
    {
        for ($i = 1; $i <= 41; ++$i) {
            $this->createRecipe(\sprintf('Rezept %02d', $i));
        }
        $this->em->flush();

        $first = $this->repository->findFilteredPage(null, null, [], 'title', 0, 20);
        $second = $this->repository->findFilteredPage(null, null, [], 'title', 2, 20);
        $last = $this->repository->findFilteredPage(null, null, [], 'title', 999, 20);

        self::assertSame(1, $first->page);
        self::assertCount(20, $first->items);
        self::assertSame(3, $first->totalPages);
        self::assertCount(20, $second->items);
        self::assertSame(3, $last->page);
        self::assertCount(1, $last->items);

        $ids = array_merge(
            array_map(static fn (Recipe $recipe): ?int => $recipe->getId(), $first->items),
            array_map(static fn (Recipe $recipe): ?int => $recipe->getId(), $second->items),
            array_map(static fn (Recipe $recipe): ?int => $recipe->getId(), $last->items),
        );
        self::assertCount(41, array_unique($ids));
    }

    private function createRecipe(string $title): Recipe
    {
        $recipe = (new Recipe())
            ->setTitle($title)
            ->setAuthor('Test Autor')
            ->setDifficulty('einfach')
            ->setPrepTime(10)
            ->setCookTime(10)
            ->setServings(2);
        $this->em->persist($recipe);

        return $recipe;
    }
}
