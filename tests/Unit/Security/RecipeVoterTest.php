<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Recipe;
use App\Entity\User;
use App\Security\RecipeVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Unit-Tests für den RecipeVoter.
 */
class RecipeVoterTest extends TestCase
{
    private RecipeVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new RecipeVoter();
    }

    /**
     * Hilfsmethode: erstellt einen User mit optionalen Rollen.
     *
     * @param list<string> $roles
     */
    private function createUser(array $roles = []): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setRoles($roles);

        return $user;
    }

    /** Hilfsmethode: erstellt ein Rezept mit optionalem Eigentümer. */
    private function createRecipe(?User $owner = null): Recipe
    {
        $recipe = new Recipe();
        $recipe->setOwner($owner);

        return $recipe;
    }

    /** Hilfsmethode: erstellt einen Token für den gegebenen User. */
    private function createToken(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main', $user->getRoles());
    }

    /** Der Eigentümer darf sein Rezept bearbeiten. */
    public function testOwnerCanEditRecipe(): void
    {
        $user = $this->createUser();
        $recipe = $this->createRecipe($user);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $recipe, [RecipeVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    /** Der Eigentümer darf sein Rezept löschen. */
    public function testOwnerCanDeleteRecipe(): void
    {
        $user = $this->createUser();
        $recipe = $this->createRecipe($user);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $recipe, [RecipeVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    /** Ein anderer User darf fremde Rezepte nicht bearbeiten. */
    public function testOtherUserCannotEditRecipe(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $otherUser->setEmail('other@example.com');
        $recipe = $this->createRecipe($owner);
        $token = $this->createToken($otherUser);

        $result = $this->voter->vote($token, $recipe, [RecipeVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    /** Ein anderer User darf fremde Rezepte nicht löschen. */
    public function testOtherUserCannotDeleteRecipe(): void
    {
        $owner = $this->createUser();
        $otherUser = $this->createUser();
        $otherUser->setEmail('other@example.com');
        $recipe = $this->createRecipe($owner);
        $token = $this->createToken($otherUser);

        $result = $this->voter->vote($token, $recipe, [RecipeVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_DENIED, $result);
    }

    /** Ein Admin darf jedes Rezept bearbeiten. */
    public function testAdminCanEditAnyRecipe(): void
    {
        $owner = $this->createUser();
        $admin = $this->createUser(['ROLE_ADMIN']);
        $admin->setEmail('admin@example.com');
        $recipe = $this->createRecipe($owner);
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $recipe, [RecipeVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    /** Ein Admin darf jedes Rezept löschen. */
    public function testAdminCanDeleteAnyRecipe(): void
    {
        $owner = $this->createUser();
        $admin = $this->createUser(['ROLE_ADMIN']);
        $admin->setEmail('admin@example.com');
        $recipe = $this->createRecipe($owner);
        $token = $this->createToken($admin);

        $result = $this->voter->vote($token, $recipe, [RecipeVoter::DELETE]);

        $this->assertSame(VoterInterface::ACCESS_GRANTED, $result);
    }

    /** Für ein nicht unterstütztes Attribut enthält sich der Voter. */
    public function testVoterAbstainsForUnsupportedAttribute(): void
    {
        $user = $this->createUser();
        $recipe = $this->createRecipe($user);
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, $recipe, ['UNSUPPORTED_ATTRIBUTE']);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }

    /** Für ein nicht unterstütztes Subjekt enthält sich der Voter. */
    public function testVoterAbstainsForUnsupportedSubject(): void
    {
        $user = $this->createUser();
        $token = $this->createToken($user);

        $result = $this->voter->vote($token, new \stdClass(), [RecipeVoter::EDIT]);

        $this->assertSame(VoterInterface::ACCESS_ABSTAIN, $result);
    }
}
