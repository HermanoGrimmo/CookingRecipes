<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use App\Entity\Recipe;
use App\Entity\RecipeRating;
use App\Entity\Tag;
use App\Entity\User;
use App\Twig\Components\RecipeList;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

/** Integrationstests für die reaktive Rezeptübersicht. */
final class RecipeListTest extends KernelTestCase
{
    use InteractsWithLiveComponents;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
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
        $connection->executeStatement('DELETE FROM reset_password_request');
        $connection->executeStatement('DELETE FROM app_user');
        parent::tearDown();
    }

    public function testRendersAllRecipesWithoutFilter(): void
    {
        $this->createRecipe('Cashew Hähnchen-Curry');
        $this->createRecipe('Apfelkuchen');
        $this->em->flush();

        $rendered = (string) $this->createLiveComponent(RecipeList::class)->render();

        self::assertStringContainsString('Cashew Hähnchen-Curry', $rendered);
        self::assertStringContainsString('Apfelkuchen', $rendered);
    }

    public function testSearchFiltersTheListAndResetsPage(): void
    {
        for ($i = 1; $i <= 21; ++$i) {
            $this->createRecipe(\sprintf('Rezept %02d', $i));
        }
        $this->em->flush();
        $component = $this->createLiveComponent(RecipeList::class);
        $component->call('goToPage', ['page' => 2]);
        self::assertSame(2, $this->component($component)->page);

        $component->set('search', 'Rezept 01');

        self::assertSame(1, $this->component($component)->page);
        self::assertStringContainsString('Rezept 01', (string) $component->render());
        self::assertStringNotContainsString('Rezept 02', (string) $component->render());
    }

    public function testDifficultyFilterFiltersTheList(): void
    {
        $this->createRecipe('Spiegelei', 'einfach');
        $this->createRecipe('Soufflé', 'schwer');
        $this->em->flush();
        $component = $this->createLiveComponent(RecipeList::class);

        $component->set('difficulty', 'schwer');
        $rendered = (string) $component->render();

        self::assertStringContainsString('Soufflé', $rendered);
        self::assertStringNotContainsString('Spiegelei', $rendered);
    }

    public function testGoToPageBlaettertUndBegrenztZuHoheSeiten(): void
    {
        for ($i = 1; $i <= 21; ++$i) {
            $this->createRecipe(\sprintf('Rezept %02d', $i));
        }
        $this->em->flush();
        $component = $this->createLiveComponent(RecipeList::class);
        $component->set('sortBy', 'title');

        $component->call('goToPage', ['page' => 2]);
        self::assertSame(2, $this->component($component)->page);
        self::assertStringContainsString('Rezept 21', (string) $component->render());
        self::assertStringNotContainsString('Rezept 20', (string) $component->render());

        $component->call('goToPage', ['page' => 999]);
        self::assertSame(2, $this->component($component)->page);
    }

    public function testPaginationZeigtEinBegrenztesSeitenfenster(): void
    {
        for ($i = 1; $i <= 200; ++$i) {
            $this->createRecipe(\sprintf('Fenster %03d', $i));
        }
        $this->em->flush();
        $component = $this->createLiveComponent(RecipeList::class);
        $component->call('goToPage', ['page' => 5]);

        $crawler = $component->render()->crawler();
        self::assertCount(7, $crawler->filter('button[aria-label^="Seite "]'));
        self::assertCount(2, $crawler->filter('.pagination-ellipsis'));
    }

    public function testPaginationDupliziertRandseitenNicht(): void
    {
        for ($i = 1; $i <= 60; ++$i) {
            $this->createRecipe(\sprintf('Rand %02d', $i));
        }
        $this->em->flush();
        $component = $this->createLiveComponent(RecipeList::class);

        self::assertCount(3, $component->render()->crawler()->filter('button[aria-label^="Seite "]'));
        $component->call('goToPage', ['page' => 3]);
        self::assertCount(3, $component->render()->crawler()->filter('button[aria-label^="Seite "]'));
    }

    public function testTagFilterGreiftUndBleibtImSelectAusgewaehlt(): void
    {
        $vegan = new Tag('Vegan');
        $schnell = new Tag('Schnell');
        $this->em->persist($vegan);
        $this->em->persist($schnell);
        $this->createRecipe('Veganer Salat')->addTag($vegan);
        $this->createRecipe('Schnelles Steak')->addTag($schnell);
        $this->em->flush();
        $veganId = $vegan->getId();
        self::assertNotNull($veganId);
        $component = $this->createLiveComponent(RecipeList::class);

        $component->set('tagIds', [(string) $veganId]);
        $render = $component->render();

        self::assertStringContainsString('Veganer Salat', (string) $render);
        self::assertStringNotContainsString('Schnelles Steak', (string) $render);
        self::assertCount(1, $render->crawler()->filter(\sprintf('option[value="%d"][selected]', $veganId)));
    }

    public function testManipulierteTagWerteWerdenAlsBadRequestAbgewiesen(): void
    {
        $component = $this->createLiveComponent(RecipeList::class);

        $this->expectException(BadRequestHttpException::class);
        $component->set('tagIds', [['kein-skalarer-wert']]);
    }

    public function testZuVieleTagWerteWerdenAlsBadRequestAbgewiesen(): void
    {
        $component = $this->createLiveComponent(RecipeList::class);

        $this->expectException(BadRequestHttpException::class);
        $component->set('tagIds', range(1, 51));
    }

    public function testPersoenlicheScoresLandenAnDerRichtigenKarte(): void
    {
        $user = $this->createUser();
        $first = $this->createRecipe('Erstes Rezept');
        $second = $this->createRecipe('Zweites Rezept');
        $this->em->persist(new RecipeRating($first, $user, 2));
        $this->em->persist(new RecipeRating($second, $user, 4));
        $this->em->flush();

        $component = $this->createLiveComponent(RecipeList::class)->actingAs($user);
        $component->set('sortBy', 'title');
        $render = $component->render();
        $cards = $render->crawler()->filter('article.recipe-card');

        self::assertSame('2', $cards->eq(0)->filter('button.rating-star[aria-pressed="true"]')->attr('data-live-score-param'));
        self::assertSame('4', $cards->eq(1)->filter('button.rating-star[aria-pressed="true"]')->attr('data-live-score-param'));
    }

    private function component(TestLiveComponent $testComponent): RecipeList
    {
        $component = $testComponent->component();
        self::assertInstanceOf(RecipeList::class, $component);

        return $component;
    }

    private function createUser(): User
    {
        $user = (new User())
            ->setEmail('list@example.test')
            ->setFirstName('Listen')
            ->setLastName('Tester')
            ->setPassword('password');
        $this->em->persist($user);

        return $user;
    }

    private function createRecipe(string $title, string $difficulty = 'einfach'): Recipe
    {
        $recipe = (new Recipe())
            ->setTitle($title)
            ->setAuthor('Test Autor')
            ->setDifficulty($difficulty)
            ->setPrepTime(10)
            ->setCookTime(20)
            ->setServings(2);
        $this->em->persist($recipe);

        return $recipe;
    }
}
