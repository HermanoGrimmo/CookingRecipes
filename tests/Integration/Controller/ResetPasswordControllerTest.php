<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrationstests für den ResetPasswordController.
 */
class ResetPasswordControllerTest extends WebTestCase
{
    /** Die Passwort-Vergessen-Seite ist öffentlich zugänglich und zeigt ein Formular. */
    public function testForgotPasswordPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/passwort/vergessen');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name*="email"]');
    }

    /** Absenden des Formulars leitet immer zur Check-E-Mail-Seite weiter (auch bei unbekannter E-Mail). */
    public function testSubmitWithUnknownEmailRedirectsToCheckEmail(): void
    {
        $client = static::createClient();
        $client->request('GET', '/passwort/vergessen');

        $client->submitForm('Reset-Link senden', [
            'reset_password_request_form[email]' => 'unbekannt@beispiel.de',
        ]);

        $this->assertResponseRedirects('/passwort/vergessen/check');
    }

    /** Die Check-E-Mail-Seite ist öffentlich zugänglich. */
    public function testCheckEmailPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/passwort/vergessen/check');

        $this->assertResponseIsSuccessful();
    }

    /** Zugriff auf die Reset-Seite ohne Token leitet zur Passwort-Vergessen-Seite um. */
    public function testResetPageWithoutTokenRedirectsToRequest(): void
    {
        $client = static::createClient();
        $client->request('GET', '/passwort/zuruecksetzen');

        $this->assertResponseRedirects('/passwort/vergessen');
    }

    /** Ungültiger Token zeigt eine Fehlermeldung und leitet zur Passwort-Vergessen-Seite um. */
    public function testInvalidTokenRedirectsWithError(): void
    {
        $client = static::createClient();
        // Zuerst den Token in der Session speichern (simuliert den Redirect von /zuruecksetzen/{token})
        $client->request('GET', '/passwort/zuruecksetzen/ungueltigerToken123');

        // Sollte auf die Token-freie URL weiterleiten
        $this->assertResponseRedirects('/passwort/zuruecksetzen');

        // Folge dem Redirect – ungültiger Token führt zu Fehlermeldung
        $client->followRedirect();
        $this->assertResponseRedirects('/passwort/vergessen');
    }

    /** Die Passwort-Vergessen-Seite enthält einen Link zurück zur Anmeldeseite. */
    public function testForgotPasswordPageHasLinkToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/passwort/vergessen');

        $this->assertSelectorExists('a[href="/anmelden"]');
    }
}
