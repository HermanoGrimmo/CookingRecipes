<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integrationstests für den RegistrationController.
 */
class RegistrationControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        // Testdaten bereinigen (Reihenfolge beachtet FK-Constraints)
        $connection->executeStatement('DELETE FROM reset_password_request');
        $connection->executeStatement('DELETE FROM app_user');

        parent::tearDown();
    }

    /** Die Registrierungsseite ist öffentlich zugänglich und zeigt ein Formular. */
    public function testRegisterPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/registrieren');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
    }

    /** Erfolgreiche Registrierung leitet zur Login-Seite weiter. */
    public function testSuccessfulRegistrationRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/registrieren');

        $client->submitForm('Konto erstellen', [
            'registration_form[firstName]' => 'Max',
            'registration_form[lastName]' => 'Mustermann',
            'registration_form[email]' => 'max@beispiel.de',
            'registration_form[plainPassword][first]' => 'sicheresPasswort123',
            'registration_form[plainPassword][second]' => 'sicheresPasswort123',
        ]);

        $this->assertResponseRedirects('/anmelden');
    }

    /** Zu kurzes Passwort führt zu einem Validierungsfehler. */
    public function testShortPasswordShowsValidationError(): void
    {
        $client = static::createClient();
        $client->request('GET', '/registrieren');

        $client->submitForm('Konto erstellen', [
            'registration_form[firstName]' => 'Max',
            'registration_form[lastName]' => 'Mustermann',
            'registration_form[email]' => 'max2@beispiel.de',
            'registration_form[plainPassword][first]' => 'kurz',
            'registration_form[plainPassword][second]' => 'kurz',
        ]);

        // Symfony gibt HTTP 422 bei Validierungsfehlern zurück (kein Redirect = Formular mit Fehler)
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('ul li', 'mindestens 8 Zeichen');
    }
}
