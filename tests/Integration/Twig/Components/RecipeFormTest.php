<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\User;
use App\Twig\Components\RecipeForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

/**
 * Integrationstests für das RecipeForm Live Component:
 * Autorisierung der save-Action und korrektes Entfernen von Collection-Zeilen.
 */
class RecipeFormTest extends KernelTestCase
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

        // Testdaten bereinigen (Reihenfolge beachtet FK-Constraints)
        $connection->executeStatement('DELETE FROM ingredient');
        $connection->executeStatement('DELETE FROM step');
        $connection->executeStatement('DELETE FROM recipe');
        $connection->executeStatement('DELETE FROM reset_password_request');
        $connection->executeStatement('DELETE FROM app_user');

        parent::tearDown();
    }

    /** Nicht eingeloggte Benutzer dürfen kein Rezept speichern. */
    public function testSaveIsDeniedForGuests(): void
    {
        $component = $this->createLiveComponent(RecipeForm::class);

        $this->expectException(AccessDeniedException::class);

        $component->call('save');
    }

    /** Ein fremdes Rezept darf nicht über die save-Action gespeichert werden. */
    public function testSaveOnForeignRecipeIsDenied(): void
    {
        $owner = $this->createUser('owner@example.com');
        $other = $this->createUser('other@example.com');
        $recipe = $this->createRecipeWithIngredients($owner, ['Mehl']);

        $component = $this->createLiveComponent(RecipeForm::class, ['initialFormData' => $recipe])
            ->actingAs($other);

        $this->expectException(AccessDeniedException::class);

        $component->call('save');
    }

    /**
     * Regressionstest: Zwei aufeinanderfolgende Entfern-Aktionen löschen die
     * richtigen Zeilen. Nach dem ersten Entfernen sind die Formular-Keys
     * lückenhaft ([1, 2]) – die Actions müssen mit Keys arbeiten, nicht mit
     * Listenpositionen.
     */
    public function testRemovingTwoIngredientRowsRemovesTheCorrectOnes(): void
    {
        $owner = $this->createUser('owner@example.com');
        $recipe = $this->createRecipeWithIngredients($owner, ['Mehl', 'Zucker', 'Butter']);
        $recipeId = $recipe->getId();

        $component = $this->createLiveComponent(RecipeForm::class, ['initialFormData' => $recipe])
            ->actingAs($owner);

        // Erste Zeile entfernen (Key 0) – danach existieren die Keys 1 und 2.
        $component->call('removeIngredient', ['index' => 0]);
        // Die jetzt erste Zeile ("Zucker") hat weiterhin den Key 1.
        $component->call('removeIngredient', ['index' => 1]);
        $component->call('save');

        $this->em->clear();
        $reloaded = $this->em->find(Recipe::class, $recipeId);

        self::assertNotNull($reloaded);
        $ingredients = $reloaded->getIngredients();
        self::assertCount(1, $ingredients);

        $remaining = $ingredients->first();
        self::assertInstanceOf(Ingredient::class, $remaining);
        self::assertSame('Butter', $remaining->getName());
        // Die Position wurde beim Speichern neu durchnummeriert.
        self::assertSame(0, $remaining->getPosition());
    }

    /** Das Hinzufügen einer Zeile erzeugt eine weitere leere Zutaten-Zeile im Formular. */
    public function testAddIngredientAppendsARow(): void
    {
        $owner = $this->createUser('owner@example.com');
        $recipe = $this->createRecipeWithIngredients($owner, ['Mehl']);

        $component = $this->createLiveComponent(RecipeForm::class, ['initialFormData' => $recipe])
            ->actingAs($owner);

        $component->call('addIngredient');

        $crawler = $component->render()->crawler();
        self::assertCount(2, $crawler->filter('.collection-row'));
    }

    /** Erstellt und persistiert einen Benutzer. */
    private function createUser(string $email): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('test_password'); // plaintext ist im Test-Env erlaubt (algorithm: plaintext)

        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }

    /**
     * Erstellt ein Rezept mit Zutaten in der angegebenen Reihenfolge.
     *
     * @param list<string> $ingredientNames
     */
    private function createRecipeWithIngredients(User $owner, array $ingredientNames): Recipe
    {
        $recipe = new Recipe();
        $recipe->setTitle('Test-Rezept');
        $recipe->setAuthor($owner->getFullName());
        $recipe->setOwner($owner);
        $recipe->setDifficulty('einfach');
        $recipe->setPrepTime(10);
        $recipe->setCookTime(20);
        $recipe->setServings(2);

        foreach ($ingredientNames as $position => $name) {
            $ingredient = new Ingredient();
            $ingredient->setName($name);
            $ingredient->setPosition($position);
            $recipe->addIngredient($ingredient);
        }

        $this->em->persist($recipe);
        $this->em->flush();

        return $recipe;
    }
}
