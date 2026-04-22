<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\User;
use App\Service\RegistrationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Unit-Tests für den RegistrationService.
 */
class RegistrationServiceTest extends TestCase
{
    private UserPasswordHasherInterface&MockObject $passwordHasher;
    private EntityManagerInterface&MockObject $em;
    private RegistrationService $service;

    protected function setUp(): void
    {
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new RegistrationService($this->passwordHasher, $this->em);
    }

    /** Das Passwort wird gehasht und niemals im Klartext gespeichert. */
    public function testPasswordIsHashed(): void
    {
        $user = new User();
        $plainPassword = 'meinPasswort123';
        $hashedPassword = 'hashed_password_value';

        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->with($user, $plainPassword)
            ->willReturn($hashedPassword);

        $this->em->expects($this->once())->method('persist')->with($user);
        $this->em->expects($this->once())->method('flush');

        $this->service->registerUser($user, $plainPassword);

        $this->assertSame($hashedPassword, $user->getPassword());
        $this->assertNotSame($plainPassword, $user->getPassword());
    }

    /** Der User wird persistiert und der EntityManager flusht. */
    public function testUserIsPersistedAndFlushed(): void
    {
        $user = new User();

        $this->passwordHasher->method('hashPassword')->willReturn('hashed');
        $this->em->expects($this->once())->method('persist')->with($user);
        $this->em->expects($this->once())->method('flush');

        $this->service->registerUser($user, 'password');
    }
}
