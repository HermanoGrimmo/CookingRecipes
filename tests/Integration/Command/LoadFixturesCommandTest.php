<?php

declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\Recipe;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/** Stellt sicher, dass Fixtures keine unbelegten Bewertungsaggregate erzeugen. */
final class LoadFixturesCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM recipe_rating');
        $connection->executeStatement('DELETE FROM recipe_tag');
        $connection->executeStatement('DELETE FROM ingredient');
        $connection->executeStatement('DELETE FROM step');
        $connection->executeStatement('DELETE FROM recipe');
        parent::tearDown();
    }

    public function testGeladeneRezepteStartenOhneBewertungen(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);
        $application = new Application($kernel);
        $tester = new CommandTester($application->find('app:load-fixtures'));

        self::assertSame(0, $tester->execute([]));

        $recipes = $this->entityManager->getRepository(Recipe::class)->findAll();
        self::assertNotEmpty($recipes);
        foreach ($recipes as $recipe) {
            self::assertSame('0.0', $recipe->getRating());
            self::assertSame(0, $recipe->getRatingCount());
        }
    }
}
