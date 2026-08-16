<?php

declare(strict_types=1);

namespace App\Tests\Integration\Twig\Components;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\Tag;
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
        $connection->executeStatement('DELETE FROM recipe_tag');
        $connection->executeStatement('DELETE FROM ingredient');
        $connection->executeStatement('DELETE FROM step');
        $connection->executeStatement('DELETE FROM recipe');
        $connection->executeStatement('DELETE FROM tag');
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

    /**
     * Ein manuell angelegtes Rezept bekommt weiterhin den Namen des
     * eingeloggten Benutzers als Autor und keine Herkunftsdaten.
     */
    public function testManuellAngelegtesRezeptBehaeltDenBenutzerAlsAutor(): void
    {
        $user = $this->createUser('autor@example.com');

        $component = $this->createLiveComponent(RecipeForm::class, [
            'initialFormData' => $this->createUnsavedRecipe('Selbst erfunden'),
        ])->actingAs($user);

        $component->call('save');

        $recipe = $this->findRecipeByTitle('Selbst erfunden');
        self::assertSame('Test User', $recipe->getAuthor());
        self::assertSame($user->getId(), $recipe->getOwner()?->getId());
        self::assertNull($recipe->getSourceUrl());
        self::assertNull($recipe->getSourceName());
        self::assertNull($recipe->getImportedAt());
        self::assertFalse($recipe->isImported());
    }

    /**
     * Ein importiertes Rezept behält den Autor der Quelle und bekommt die
     * Herkunftsdaten – der eingeloggte Benutzer wird trotzdem Eigentümer.
     */
    public function testImportiertesRezeptBehaeltAutorUndHerkunft(): void
    {
        $user = $this->createUser('importeur@example.com');

        $component = $this->createLiveComponent(RecipeForm::class, [
            'initialFormData' => $this->createUnsavedRecipe('Penne mit Ofentomatensauce'),
            'sourceUrl' => 'https://www.chefkoch.de/rezepte/123/Test.html',
            'sourceName' => 'Chefkoch',
            'importedAuthor' => 'anfieta',
        ])->actingAs($user);

        $component->call('save');

        $recipe = $this->findRecipeByTitle('Penne mit Ofentomatensauce');
        self::assertSame('anfieta', $recipe->getAuthor());
        self::assertSame($user->getId(), $recipe->getOwner()?->getId(), 'Der Importeur muss Eigentümer bleiben.');
        self::assertSame('https://www.chefkoch.de/rezepte/123/Test.html', $recipe->getSourceUrl());
        self::assertSame('Chefkoch', $recipe->getSourceName());
        self::assertNotNull($recipe->getImportedAt());
        self::assertTrue($recipe->isImported());
    }

    /**
     * Twig übergibt nicht gesetzte Props als Leerstring – daraus darf kein
     * leerer Autor und keine leere Quell-URL werden (der Unique-Index auf
     * source_url würde beim zweiten Rezept zuschlagen).
     */
    public function testLeereHerkunftsPropsWerdenWieNichtGesetztBehandelt(): void
    {
        $user = $this->createUser('leer@example.com');

        $component = $this->createLiveComponent(RecipeForm::class, [
            'initialFormData' => $this->createUnsavedRecipe('Ohne Herkunft'),
            'sourceUrl' => '',
            'sourceName' => '',
            'importedAuthor' => '',
        ])->actingAs($user);

        $component->call('save');

        $recipe = $this->findRecipeByTitle('Ohne Herkunft');
        self::assertSame('Test User', $recipe->getAuthor());
        self::assertNull($recipe->getSourceUrl());
    }

    /** Tags werden als kommaseparierter Text übernommen. */
    public function testTagsWerdenAusDemTextfeldUebernommen(): void
    {
        $user = $this->createUser('tagger@example.com');

        $unsaved = $this->createUnsavedRecipe('Mit Tags');
        $unsaved->addTag(new Tag('Pasta'));
        $unsaved->addTag(new Tag('Vegetarisch'));

        $component = $this->createLiveComponent(RecipeForm::class, ['initialFormData' => $unsaved])
            ->actingAs($user);

        $component->call('save');

        $recipe = $this->findRecipeByTitle('Mit Tags');

        $names = [];
        foreach ($recipe->getTags() as $tag) {
            $names[] = $tag->getName();
        }
        sort($names);

        self::assertSame(['Pasta', 'Vegetarisch'], $names);
    }

    /**
     * Ein noch nicht gespeichertes, gültig ausgefülltes Rezept – so, wie es
     * der RecipeImportService an die Komponente übergibt.
     */
    private function createUnsavedRecipe(string $title): Recipe
    {
        $recipe = new Recipe();
        $recipe->setTitle($title);
        $recipe->setDifficulty('einfach');
        $recipe->setServings(4);
        $recipe->setPrepTime(15);
        $recipe->setCookTime(25);
        $recipe->setRestTime(0);

        return $recipe;
    }

    /** Lädt ein Rezept frisch aus der Datenbank. */
    private function findRecipeByTitle(string $title): Recipe
    {
        $this->em->clear();

        $recipe = $this->em->getRepository(Recipe::class)->findOneBy(['title' => $title]);
        self::assertInstanceOf(Recipe::class, $recipe, \sprintf('Rezept "%s" wurde nicht gespeichert.', $title));

        return $recipe;
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
