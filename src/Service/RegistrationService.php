<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Dienst zur Registrierung neuer Benutzer.
 * Verantwortlich für das Hashen des Passworts und das Speichern des Benutzers.
 */
class RegistrationService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Registriert einen neuen Benutzer.
     * Das Klartextpasswort wird gehasht und niemals im Klartext gespeichert.
     */
    public function registerUser(User $user, string $plainPassword): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $this->em->persist($user);
        $this->em->flush();
    }
}
