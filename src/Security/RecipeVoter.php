<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Recipe;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Prüft, ob ein Benutzer ein Rezept bearbeiten oder löschen darf.
 *
 * Erlaubt ist:
 * - Der Ersteller des Rezepts (owner)
 * - Administratoren (ROLE_ADMIN)
 *
 * @extends Voter<string, Recipe>
 */
class RecipeVoter extends Voter
{
    public const string EDIT = 'recipe.edit';
    public const string DELETE = 'recipe.delete';

    /**
     * Prüft, ob dieser Voter für das gegebene Attribut und Subjekt zuständig ist.
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof Recipe;
    }

    /**
     * Entscheidet, ob der Zugriff gewährt wird.
     *
     * @param Recipe $subject
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        // Nicht eingeloggte Benutzer haben keinen Zugriff
        if (!$user instanceof User) {
            return false;
        }

        // Administratoren dürfen alle Rezepte bearbeiten und löschen
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Nur der Ersteller darf sein eigenes Rezept bearbeiten oder löschen
        return $subject->getOwner() === $user;
    }
}
