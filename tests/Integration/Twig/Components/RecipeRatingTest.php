<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use App\Entity\Recipe;
use App\Entity\User;
use App\Twig\Components\RecipeRating;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/** Integrationstests für den autorisierten Bewertungsschreibpfad. */
final class RecipeRatingTest extends KernelTestCase
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
        $connection->executeStatement('DELETE FROM reset_password_request');
        $connection->executeStatement('DELETE FROM app_user');
        parent::tearDown();
    }

    public function testGastKannKeineBewertungSpeichern(): void
    {
        $recipe = $this->createRecipe();
        $this->em->flush();
        $component = $this->createLiveComponent(RecipeRating::class, ['recipe' => $recipe]);

        $this->expectException(AccessDeniedException::class);
        $component->call('rate', ['score' => 5]);
    }

    public function testAngemeldeterBenutzerSiehtFuenfButtonsUndKannBewerten(): void
    {
        $user = $this->createUser();
        $recipe = $this->createRecipe();
        $this->em->flush();
        $recipeId = $recipe->getId();
        $component = $this->createLiveComponent(RecipeRating::class, ['recipe' => $recipe])->actingAs($user);

        self::assertCount(5, $component->render()->crawler()->filter('button.rating-star[aria-label]'));
        $component->call('rate', ['score' => 4]);

        self::assertNotNull($recipeId);
        $reloaded = $this->em->find(Recipe::class, $recipeId);
        self::assertNotNull($reloaded);
        self::assertSame('4.0', $reloaded->getRating());
        self::assertSame(1, $reloaded->getRatingCount());
    }

    #[DataProvider('invalidScores')]
    public function testManipulierteBewertungWirdAlsBadRequestAbgewiesen(mixed $score): void
    {
        $user = $this->createUser();
        $recipe = $this->createRecipe();
        $this->em->flush();
        $component = $this->createLiveComponent(RecipeRating::class, ['recipe' => $recipe])->actingAs($user);

        $this->expectException(BadRequestHttpException::class);
        $component->call('rate', ['score' => $score]);
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidScores(): iterable
    {
        yield 'zu groß' => [99];
        yield 'kein Integer' => ['abc'];
        yield 'numerischer String' => ['4'];
        yield 'Fließkommazahl' => [4.5];
        yield 'Boolean' => [true];
        yield 'null' => [null];
    }

    private function createUser(): User
    {
        $user = (new User())->setEmail('component@example.test')->setFirstName('Test')->setLastName('User')->setPassword('password');
        $this->em->persist($user);

        return $user;
    }

    private function createRecipe(): Recipe
    {
        $recipe = (new Recipe())->setTitle('Test')->setAuthor('Autor')->setDifficulty('einfach')->setPrepTime(10)->setCookTime(10)->setServings(2);
        $this->em->persist($recipe);

        return $recipe;
    }
}
