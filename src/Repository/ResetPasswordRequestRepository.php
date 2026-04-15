<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Persistence\Repository\ResetPasswordRequestRepositoryTrait;
use SymfonyCasts\Bundle\ResetPassword\Persistence\ResetPasswordRequestRepositoryInterface;

/**
 * Repository für Passwort-Reset-Anfragen.
 * Verwendet den ResetPasswordRequestRepositoryTrait des symfonycasts/reset-password-bundle.
 *
 * @extends ServiceEntityRepository<ResetPasswordRequest>
 */
class ResetPasswordRequestRepository extends ServiceEntityRepository implements ResetPasswordRequestRepositoryInterface
{
    use ResetPasswordRequestRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ResetPasswordRequest::class);
    }

    /**
     * Erstellt eine neue ResetPasswordRequest-Instanz.
     * Muss manuell implementiert werden, da der Trait die konkrete Entity-Klasse nicht kennt.
     * Die Signatur erzwingt object (Interface-Vorgabe); intern wird ein User erwartet.
     */
    public function createResetPasswordRequest(
        object $user,
        \DateTimeInterface $expiresAt,
        string $selector,
        string $hashedToken,
    ): ResetPasswordRequestInterface {
        if (!$user instanceof User) {
            throw new \InvalidArgumentException(\sprintf('Erwartet wird ein User-Objekt, erhalten: %s', $user::class));
        }

        return new ResetPasswordRequest($user, $expiresAt, $selector, $hashedToken);
    }
}
