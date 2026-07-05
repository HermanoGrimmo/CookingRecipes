<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use App\Entity\Recipe;
use App\Twig\Components\RecipeList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Integrationstests für die reaktive Rezeptübersicht (Suche und Filter).
 */
class RecipeListTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em = $em;
    }

    protected function tearDown(): void
    {
        $connection = $this->em->getConnection();

        $connection->executeStatement('DELETE FROM ingredient');
        $connection->executeStatement('DELETE FROM step');
        $connection->executeStatement('DELETE FROM recipe');

        parent::tearDown();
    }

    /** Ohne Filter werden alle Rezepte angezeigt. */
    public function testRendersAllRecipesWithoutFilter(): void
    {
        $this->createRecipe('Cashew Hähnchen-Curry');
        $this->createRecipe('Apfelkuchen');

        $component = $this->createLiveComponent(RecipeList::class);
        $rendered = (string) $component->render();

        self::assertStringContainsString('Cashew Hähnchen-Curry', $rendered);
        self::assertStringContainsString('Apfelkuchen', $rendered);
    }

    /** Die Suche blendet nicht passende Rezepte aus. */
    public function testSearchFiltersTheList(): void
    {
        $this->createRecipe('Cashew Hähnchen-Curry');
        $this->createRecipe('Apfelkuchen');

        $component = $this->createLiveComponent(RecipeList::class);
        $component->set('search', 'curry');

        $rendered = (string) $component->render();

        self::assertStringContainsString('Cashew Hähnchen-Curry', $rendered);
        self::assertStringNotContainsString('Apfelkuchen', $rendered);
    }

    /** Der Schwierigkeits-Filter blendet Rezepte anderer Schwierigkeitsgrade aus. */
    public function testDifficultyFilterFiltersTheList(): void
    {
        $this->createRecipe('Spiegelei', 'einfach');
        $this->createRecipe('Soufflé', 'schwer');

        $component = $this->createLiveComponent(RecipeList::class);
        $component->set('difficulty', 'schwer');

        $rendered = (string) $component->render();

        self::assertStringContainsString('Soufflé', $rendered);
        self::assertStringNotContainsString('Spiegelei', $rendered);
    }

    /** Erstellt und persistiert ein Rezept mit sinnvollen Standardwerten. */
    private function createRecipe(string $title, string $difficulty = 'einfach'): Recipe
    {
        $recipe = new Recipe();
        $recipe->setTitle($title);
        $recipe->setAuthor('Test Autor');
        $recipe->setDifficulty($difficulty);
        $recipe->setPrepTime(10);
        $recipe->setCookTime(20);
        $recipe->setServings(2);

        $this->em->persist($recipe);
        $this->em->flush();

        return $recipe;
    }
}
