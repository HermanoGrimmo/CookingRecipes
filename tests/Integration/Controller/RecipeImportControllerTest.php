<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Integrationstests für den Rezept-Import über /rezept/importieren.
 *
 * Die HTTP-Clients der Importer werden durch einen MockHttpClient ersetzt –
 * die Tests gehen nie ins Netz.
 */
class RecipeImportControllerTest extends WebTestCase
{
    private const string CHEFKOCH_URL = 'https://www.chefkoch.de/rezepte/1563431263845794/Penne-mit-Ofentomatensauce.html';

    protected function tearDown(): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();

        // Reihenfolge beachtet die FK-Constraints.
        $connection->executeStatement('DELETE FROM recipe_tag');
        $connection->executeStatement('DELETE FROM ingredient');
        $connection->executeStatement('DELETE FROM step');
        $connection->executeStatement('DELETE FROM recipe');
        $connection->executeStatement('DELETE FROM tag');
        $connection->executeStatement('DELETE FROM reset_password_request');
        $connection->executeStatement('DELETE FROM app_user');

        parent::tearDown();
    }

    public function testImportSeiteLeitetUnangemeldeteZumLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/rezept/importieren');

        $this->assertResponseRedirects('/anmelden');
    }

    public function testImportSeiteZeigtDieUnterstuetztenQuellen(): void
    {
        $client = $this->createLoggedInClient('import1@example.com');

        $crawler = $client->request('GET', '/rezept/importieren');

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('Chefkoch', $crawler->filter('.form-page-subtitle')->text());
        self::assertStringContainsString('FOODBOOM', $crawler->filter('.form-page-subtitle')->text());
    }

    public function testNichtUnterstuetzteUrlWirdAbgelehntOhneNetzwerkzugriff(): void
    {
        $client = $this->createLoggedInClient('import2@example.com');

        $crawler = $client->request('GET', '/rezept/importieren');
        $client->submit($crawler->selectButton('Rezept laden')->form([
            'recipe_import[url]' => 'https://www.youtube.com/watch?v=abc',
        ]));

        // 422 ist die reguläre Antwort auf ein ungültig abgeschicktes Formular.
        $this->assertResponseStatusCodeSame(422);
        self::assertStringContainsString('wird nicht unterstützt', $client->getResponse()->getContent() ?: '');
    }

    public function testErfolgreicherImportZeigtDasVorbefuellteFormular(): void
    {
        $client = $this->createLoggedInClient('import3@example.com');
        $this->mockChefkochResponse($this->loadFixture('chefkoch-penne-ofentomatensauce.json'));

        $crawler = $client->request('GET', '/rezept/importieren');
        $crawler = $client->submit($crawler->selectButton('Rezept laden')->form([
            'recipe_import[url]' => self::CHEFKOCH_URL,
        ]));

        $this->assertResponseIsSuccessful();

        // Der Titel steht im Formularfeld, nicht bloß irgendwo auf der Seite.
        self::assertSame(
            'Penne mit Ofentomatensauce',
            $crawler->filter('input[name="recipe[title]"]')->attr('value'),
        );
        self::assertSame('15', $crawler->filter('input[name="recipe[prepTime]"]')->attr('value'));
        self::assertSame('25', $crawler->filter('input[name="recipe[cookTime]"]')->attr('value'));
        self::assertSame('0', $crawler->filter('input[name="recipe[restTime]"]')->attr('value'));
        self::assertCount(10, $crawler->filter('input[name^="recipe[ingredients]"][name$="[name]"]'));
        self::assertCount(3, $crawler->filter('textarea[name^="recipe[steps]"]'));

        // Herkunft wird angezeigt, aber nicht gespeichert – das macht erst save().
        self::assertStringContainsString('Importiert von', $client->getResponse()->getContent() ?: '');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertCount(0, $em->getRepository(Recipe::class)->findAll(), 'Der Import darf noch nichts speichern.');
    }

    public function testBereitsImportiertesRezeptWirdMitLinkGemeldet(): void
    {
        $client = $this->createLoggedInClient('import4@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $vorhandenes = new Recipe();
        $vorhandenes->setTitle('Schon importiert');
        $vorhandenes->setAuthor('anfieta');
        $vorhandenes->setSourceUrl(self::CHEFKOCH_URL);
        $vorhandenes->setSourceName('Chefkoch');
        $em->persist($vorhandenes);
        $em->flush();

        $crawler = $client->request('GET', '/rezept/importieren');
        $crawler = $client->submit($crawler->selectButton('Rezept laden')->form([
            'recipe_import[url]' => self::CHEFKOCH_URL,
        ]));

        $this->assertResponseIsSuccessful();

        $hinweis = $crawler->filter('.flash-warning');
        self::assertCount(1, $hinweis);
        self::assertStringContainsString('bereits importiert', $hinweis->text());
        self::assertSame(
            '/rezept/' . $vorhandenes->getId(),
            $hinweis->filter('a')->attr('href'),
        );
    }

    /**
     * Tracking-Parameter dürfen nicht zu einem zweiten Import derselben Seite
     * führen.
     */
    public function testDoppelterImportWirdAuchMitQueryParameternErkannt(): void
    {
        $client = $this->createLoggedInClient('import5@example.com');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $vorhandenes = new Recipe();
        $vorhandenes->setTitle('Schon importiert');
        $vorhandenes->setSourceUrl(self::CHEFKOCH_URL);
        $vorhandenes->setSourceName('Chefkoch');
        $em->persist($vorhandenes);
        $em->flush();

        $crawler = $client->request('GET', '/rezept/importieren');
        $crawler = $client->submit($crawler->selectButton('Rezept laden')->form([
            'recipe_import[url]' => self::CHEFKOCH_URL . '?utm_source=newsletter',
        ]));

        self::assertCount(1, $crawler->filter('.flash-warning'));
    }

    /**
     * Ein Ausfall der Quelle darf keinen 500er erzeugen.
     */
    public function testFehlerBeiDerQuelleErgibtEineFehlermeldung(): void
    {
        $client = $this->createLoggedInClient('import6@example.com');
        // Erst die API, dann der JSON-LD-Fallback – beide antworten mit Fehlern.
        $this->mockChefkochResponse('', 503, '', 503);

        $crawler = $client->request('GET', '/rezept/importieren');
        $client->submit($crawler->selectButton('Rezept laden')->form([
            'recipe_import[url]' => self::CHEFKOCH_URL,
        ]));

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString('konnte nicht geladen werden', $client->getResponse()->getContent() ?: '');
    }

    /**
     * Programmiert den Chefkoch-HTTP-Client des Test-Containers.
     *
     * Im Test-Environment ist "chefkoch.client" ein MockHttpClient
     * (config/services_test.yaml) – hier bekommt er seine Antworten.
     */
    private function mockChefkochResponse(
        string $apiBody,
        int $apiStatus = 200,
        ?string $htmlBody = null,
        int $htmlStatus = 200,
    ): void {
        $responses = [new MockResponse($apiBody, ['http_code' => $apiStatus])];
        if (null !== $htmlBody) {
            $responses[] = new MockResponse($htmlBody, ['http_code' => $htmlStatus]);
        }

        /** @var MockHttpClient $mock */
        $mock = static::getContainer()->get('app.test.chefkoch_http_client');
        $mock->setResponseFactory($responses);
    }

    private function createLoggedInClient(string $email): KernelBrowser
    {
        $client = static::createClient();

        // Ohne disableReboot() startet der KernelBrowser vor jedem Request
        // einen neuen Kernel – der im Test konfigurierte MockHttpClient wäre
        // dann wieder leer.
        $client->disableReboot();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('test_password');
        $em->persist($user);
        $em->flush();

        $client->loginUser($user);

        return $client;
    }

    private function loadFixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../Fixtures/Import/' . $name);
        self::assertIsString($content, \sprintf('Fixture "%s" konnte nicht gelesen werden.', $name));

        return $content;
    }
}
