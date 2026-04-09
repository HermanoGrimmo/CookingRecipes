<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrationstests zur Absicherung der Rezept-Routen durch Authentifizierung und Autorisierung.
 */
class RecipeControllerAuthTest extends WebTestCase
{
    /** Unauthentifizierter Zugriff auf /rezept/neu wird zu /anmelden umgeleitet. */
    public function testNewRecipeRedirectsToLoginWhenNotAuthenticated(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rezept/neu');

        $this->assertResponseRedirects('/anmelden');
    }

    /** Eingeloggter User kann /rezept/neu aufrufen. */
    public function testNewRecipeAccessibleWhenAuthenticated(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->createTestUser($em, 'author@example.com');

        $client->loginUser($user);
        $client->request('GET', '/rezept/neu');

        $this->assertResponseIsSuccessful();
    }

    /** Fremdes Rezept bearbeiten führt zu 403 Forbidden. */
    public function testEditForeignRecipeReturnsForbidden(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createTestUser($em, 'owner@example.com');
        $otherUser = $this->createTestUser($em, 'other@example.com');
        $recipe = $this->createTestRecipe($em, $owner);

        $client->loginUser($otherUser);
        $client->request('GET', '/rezept/' . $recipe->getId() . '/bearbeiten');

        $this->assertResponseStatusCodeSame(403);
    }

    /** Eigenes Rezept bearbeiten ist für den Ersteller erlaubt. */
    public function testEditOwnRecipeAllowedForOwner(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createTestUser($em, 'owner2@example.com');
        $recipe = $this->createTestRecipe($em, $owner);

        $client->loginUser($owner);
        $client->request('GET', '/rezept/' . $recipe->getId() . '/bearbeiten');

        $this->assertResponseIsSuccessful();
    }

    /** Admin darf fremde Rezepte bearbeiten. */
    public function testEditAnyRecipeAllowedForAdmin(): void
    {
        $client = static::createClient();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $owner = $this->createTestUser($em, 'owner3@example.com');
        $admin = $this->createTestUser($em, 'admin@example.com', ['ROLE_ADMIN']);
        $recipe = $this->createTestRecipe($em, $owner);

        $client->loginUser($admin);
        $client->request('GET', '/rezept/' . $recipe->getId() . '/bearbeiten');

        $this->assertResponseIsSuccessful();
    }

    /**
     * Erstellt einen Test-User und gibt ihn zurück.
     *
     * @param list<string> $roles
     */
    private function createTestUser(EntityManagerInterface $em, string $email, array $roles = []): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('test_password'); // plaintext ist im Test-Env erlaubt (algorithm: plaintext)
        $user->setRoles($roles);

        $em->persist($user);
        $em->flush();

        return $user;
    }

    /** Erstellt ein Test-Rezept mit dem gegebenen Eigentümer. */
    private function createTestRecipe(EntityManagerInterface $em, User $owner): Recipe
    {
        $recipe = new Recipe();
        $recipe->setTitle('Test-Rezept');
        $recipe->setAuthor($owner->getFullName());
        $recipe->setOwner($owner);
        $recipe->setDifficulty('einfach');
        $recipe->setPrepTime(10);
        $recipe->setCookTime(20);
        $recipe->setServings(2);

        $em->persist($recipe);
        $em->flush();

        return $recipe;
    }
}
