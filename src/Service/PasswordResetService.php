<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Dienst für den Passwort-Reset-Prozess.
 * Koordiniert die Token-Erstellung, den E-Mail-Versand und das Setzen des neuen Passworts.
 */
class PasswordResetService
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly MailerInterface $mailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Generiert einen Reset-Token für den Benutzer.
     * Der Rückgabewert enthält den Klartexttoken, der nur einmalig verfügbar ist.
     */
    public function generateResetToken(User $user): ResetPasswordToken
    {
        return $this->resetPasswordHelper->generateResetToken($user);
    }

    /**
     * Versendet die Passwort-Reset-E-Mail.
     *
     * @param string             $resetUrl   Absolute URL zum Reset-Formular (inkl. Token)
     * @param ResetPasswordToken $resetToken Token-Objekt für die Ablaufzeit-Anzeige in der E-Mail
     */
    public function sendPasswordResetEmail(User $user, string $resetUrl, ResetPasswordToken $resetToken): void
    {
        $email = (new TemplatedEmail())
            ->to($user->getEmail())
            ->subject('Dein Passwort-Reset für CookingRecipes')
            ->htmlTemplate('reset_password/email.html.twig')
            ->context([
                'resetUrl' => $resetUrl,
                'resetToken' => $resetToken,
                'userName' => $user->getFirstName(),
            ]);

        $this->mailer->send($email);
    }

    /**
     * Setzt das Passwort des Benutzers auf ein neues gehashtes Passwort.
     */
    public function resetPassword(User $user, string $plainPassword): void
    {
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);
        $this->em->flush();
    }

    public function getResetPasswordHelper(): ResetPasswordHelperInterface
    {
        return $this->resetPasswordHelper;
    }
}
