<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\Recipe;
use App\Repository\RecipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integrationstests für die Filter- und Sortierlogik von RecipeRepository::findFiltered().
 */
class RecipeRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RecipeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var RecipeRepository $repository */
        $repository = static::getContainer()->get(RecipeRepository::class);
        $this->repository = $repository;
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();

        // Testdaten bereinigen (Reihenfolge beachtet FK-Constraints)
        $connection->executeStatement('DELETE FROM ingredient');
        $connection->executeStatement('DELETE FROM step');
        $connection->executeStatement('DELETE FROM recipe');

        parent::tearDown();
    }

    /** Die Suche findet Rezepte unabhängig von Groß-/Kleinschreibung im Titel. */
    public function testSearchMatchesTitleCaseInsensitive(): void
    {
        $this->createRecipe('Cashew Hähnchen-Curry');
        $this->createRecipe('Apfelkuchen');

        $result = $this->repository->findFiltered('CURRY', null, 'newest');

        self::assertCount(1, $result);
        self::assertSame('Cashew Hähnchen-Curry', $result[0]->getTitle());
    }

    /** Die Suche berücksichtigt auch die Beschreibung. */
    public function testSearchMatchesDescription(): void
    {
        $this->createRecipe('Apfelkuchen', description: 'Mit Zimt und Streuseln');
        $this->createRecipe('Brownies');

        $result = $this->repository->findFiltered('zimt', null, 'newest');

        self::assertCount(1, $result);
        self::assertSame('Apfelkuchen', $result[0]->getTitle());
    }

    /** LIKE-Wildcards im Suchbegriff wirken als Literale, nicht als Platzhalter. */
    public function testLikeWildcardsInSearchAreEscaped(): void
    {
        $this->createRecipe('100% Schokoladenkuchen');
        $this->createRecipe('Vanillepudding');

        // "%" darf nur das Rezept mit literalem Prozentzeichen finden – nicht alle.
        $result = $this->repository->findFiltered('100%', null, 'newest');

        self::assertCount(1, $result);
        self::assertSame('100% Schokoladenkuchen', $result[0]->getTitle());

        // Ein einzelnes "_" darf nicht als Ein-Zeichen-Wildcard wirken.
        self::assertCount(0, $this->repository->findFiltered('_', null, 'newest'));
    }

    /** Der Schwierigkeits-Filter liefert nur Rezepte mit exakt diesem Schwierigkeitsgrad. */
    public function testDifficultyFilter(): void
    {
        $this->createRecipe('Spiegelei', difficulty: 'einfach');
        $this->createRecipe('Soufflé', difficulty: 'schwer');

        $result = $this->repository->findFiltered(null, 'schwer', 'newest');

        self::assertCount(1, $result);
        self::assertSame('Soufflé', $result[0]->getTitle());
    }

    /** Sortierung nach Titel erfolgt alphabetisch aufsteigend. */
    public function testSortByTitle(): void
    {
        $this->createRecipe('Zwiebelsuppe');
        $this->createRecipe('Apfelkuchen');

        $result = $this->repository->findFiltered(null, null, 'title');

        self::assertSame(
            ['Apfelkuchen', 'Zwiebelsuppe'],
            array_map(static fn (Recipe $r): string => $r->getTitle(), $result),
        );
    }

    /** Sortierung nach Zeit nutzt die Summe aus Zubereitungs- und Kochzeit. */
    public function testSortByTotalTime(): void
    {
        $this->createRecipe('Schnell', prepTime: 5, cookTime: 5);
        $this->createRecipe('Langsam', prepTime: 60, cookTime: 120);
        $this->createRecipe('Mittel', prepTime: 20, cookTime: 20);

        $result = $this->repository->findFiltered(null, null, 'time');

        self::assertSame(
            ['Schnell', 'Mittel', 'Langsam'],
            array_map(static fn (Recipe $r): string => $r->getTitle(), $result),
        );
    }

    /** Erstellt und persistiert ein Rezept mit sinnvollen Standardwerten. */
    private function createRecipe(
        string $title,
        string $difficulty = 'einfach',
        int $prepTime = 10,
        int $cookTime = 10,
        ?string $description = null,
    ): Recipe {
        $recipe = new Recipe();
        $recipe->setTitle($title);
        $recipe->setDescription($description);
        $recipe->setAuthor('Test Autor');
        $recipe->setDifficulty($difficulty);
        $recipe->setPrepTime($prepTime);
        $recipe->setCookTime($cookTime);
        $recipe->setServings(2);

        $this->em->persist($recipe);
        $this->em->flush();

        return $recipe;
    }
}
