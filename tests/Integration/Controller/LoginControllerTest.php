<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrationstests für den LoginController.
 */
class LoginControllerTest extends WebTestCase
{
    /** Die Login-Seite ist öffentlich zugänglich und zeigt ein Formular. */
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/anmelden');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="_email"]');
        $this->assertSelectorExists('input[name="_password"]');
    }

    /** Unauthentifizierter Zugriff auf eine geschützte Seite leitet zur Login-Seite um. */
    public function testProtectedPageRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rezept/neu');

        $this->assertResponseRedirects('/anmelden');
    }

    /** Die Registrierungsseite ist in der Anmeldemaske verlinkt. */
    public function testLoginPageHasLinkToRegistration(): void
    {
        $client = static::createClient();
        $client->request('GET', '/anmelden');

        $this->assertSelectorExists('a[href="/registrieren"]');
    }

    /** Die Login-Seite enthält einen Link zur Passwort-Vergessen-Seite. */
    public function testLoginPageHasLinkToPasswordReset(): void
    {
        $client = static::createClient();
        $client->request('GET', '/anmelden');

        $this->assertSelectorExists('a[href="/passwort/vergessen"]');
    }
}
