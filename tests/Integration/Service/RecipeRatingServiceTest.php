<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Recipe;
use App\Entity\RecipeRating;
use App\Entity\User;
use App\Repository\RecipeRatingRepository;
use App\Service\RecipeRatingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** Integrationstests für persistierte Einzelbewertungen und Aggregate. */
final class RecipeRatingServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RecipeRatingService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $ratingRepository = static::getContainer()->get(RecipeRatingRepository::class);
        $this->service = new RecipeRatingService($this->em, $ratingRepository);
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

    public function testBewertungKannGesetztGeaendertUndZurueckgezogenWerden(): void
    {
        $user = $this->createUser('rating@example.test');
        $recipe = $this->createRecipe();
        $this->em->flush();

        $this->service->rate($recipe, $user, 5);
        self::assertSame('5.0', $recipe->getRating());
        self::assertSame(1, $recipe->getRatingCount());

        $this->service->rate($recipe, $user, 3);
        self::assertSame('3.0', $recipe->getRating());
        self::assertSame(1, $recipe->getRatingCount());

        $this->service->remove($recipe, $user);
        self::assertSame('0.0', $recipe->getRating());
        self::assertSame(0, $recipe->getRatingCount());
        self::assertSame(0, $this->em->getRepository(RecipeRating::class)->count([]));
    }

    public function testDurchschnittWirdAusEinzelbewertungenNeuBerechnet(): void
    {
        $first = $this->createUser('first@example.test');
        $second = $this->createUser('second@example.test');
        $recipe = $this->createRecipe();
        $this->em->flush();

        $this->service->rate($recipe, $first, 1);
        $this->service->rate($recipe, $second, 4);

        self::assertSame('2.5', $recipe->getRating());
        self::assertSame(2, $recipe->getRatingCount());
    }

    public function testUngueltigeBewertungWirdAbgelehnt(): void
    {
        $user = $this->createUser('invalid@example.test');
        $recipe = $this->createRecipe();
        $this->em->flush();

        $this->expectException(\InvalidArgumentException::class);
        $this->service->rate($recipe, $user, 6);
    }

    private function createUser(string $email): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Test')
            ->setLastName('User')
            ->setPassword('password');
        $this->em->persist($user);

        return $user;
    }

    private function createRecipe(): Recipe
    {
        $recipe = (new Recipe())
            ->setTitle('Testrezept')
            ->setAuthor('Test Autor')
            ->setDifficulty('einfach')
            ->setPrepTime(10)
            ->setCookTime(10)
            ->setServings(2);
        $this->em->persist($recipe);

        return $recipe;
    }
}
