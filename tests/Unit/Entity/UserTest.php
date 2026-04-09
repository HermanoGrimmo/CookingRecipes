<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für die User-Entity.
 */
class UserTest extends TestCase
{
    /** Testet, dass getRoles() immer ROLE_USER enthält, auch bei leerem roles-Array. */
    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        $user = new User();

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    /** Testet, dass getRoles() keine Duplikate enthält. */
    public function testGetRolesContainsNoDuplicates(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_USER', 'ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertCount(\count(array_unique($roles)), $roles);
    }

    /** Testet, dass ROLE_ADMIN korrekt gesetzt und abgerufen wird. */
    public function testGetRolesContainsAdminRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $this->assertContains('ROLE_ADMIN', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    /** Testet, dass getFullName() Vor- und Nachname korrekt kombiniert. */
    public function testGetFullName(): void
    {
        $user = new User();
        $user->setFirstName('Max');
        $user->setLastName('Mustermann');

        $this->assertSame('Max Mustermann', $user->getFullName());
    }

    /** Testet, dass getFullName() bei leerem Nachnamen keinen führenden/nachfolgenden Leerzeichen enthält. */
    public function testGetFullNameTrimmed(): void
    {
        $user = new User();
        $user->setFirstName('Max');
        $user->setLastName('');

        $this->assertSame('Max', $user->getFullName());
    }

    /** Testet, dass getUserIdentifier() die E-Mail-Adresse zurückgibt. */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $user = new User();
        $user->setEmail('max@beispiel.de');

        $this->assertSame('max@beispiel.de', $user->getUserIdentifier());
    }

    /** Testet, dass das Erstellungsdatum beim Erstellen gesetzt wird. */
    public function testCreatedAtIsSetOnConstruct(): void
    {
        $before = new \DateTimeImmutable();
        $user = new User();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $user->getCreatedAt());
        $this->assertLessThanOrEqual($after, $user->getCreatedAt());
    }
}
