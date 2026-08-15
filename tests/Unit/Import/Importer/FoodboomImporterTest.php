<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Importer;

use App\Import\Dto\ImportedIngredient;
use App\Import\Dto\ImportedRecipe;
use App\Import\Exception\RecipeImportException;
use App\Import\Importer\FoodboomImporter;
use App\Import\JsonLdRecipeParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Unit-Tests für den FOODBOOM-Importer.
 *
 * Die Fixture ist die unveränderte Antwort von
 * https://api.foodboom.de/v3/recipes/indisches-koernererbsen-dal.
 */
class FoodboomImporterTest extends TestCase
{
    private const string RECIPE_URL = 'https://www.foodboom.de/rezept/indisches-koernererbsen-dal';

    public function testSupportsErkenntFoodboomRezeptUrls(): void
    {
        $importer = $this->createImporter(new MockResponse('{}'));

        self::assertTrue($importer->supports(self::RECIPE_URL));
        self::assertTrue($importer->supports('https://foodboom.de/rezept/irgendein-slug'));

        self::assertFalse($importer->supports('https://www.foodboom.de/warenkunde/tomaten'));
        self::assertFalse($importer->supports('https://www.chefkoch.de/rezepte/123/Test.html'));
    }

    public function testFetchLiestDieEckdatenAusDerApi(): void
    {
        $recipe = $this->importFixture();

        self::assertSame('Indisches Körnererbsen-Dal mit Naanbrot', $recipe->title);
        self::assertSame('Hannes', $recipe->author);
        self::assertSame(4, $recipe->servings);
        self::assertSame('einfach', $recipe->difficulty);
        self::assertSame(self::RECIPE_URL, $recipe->sourceUrl);
        self::assertSame('FOODBOOM', $recipe->sourceName);
    }

    /**
     * waitTime (12 h Einweichen) darf nicht in der Kochzeit landen.
     */
    public function testWartezeitLandetInDerRuhezeitUndNichtInDerKochzeit(): void
    {
        $recipe = $this->importFixture();

        self::assertSame(0, $recipe->prepTime);
        self::assertSame(90, $recipe->cookTime);
        self::assertSame(720, $recipe->restTime);
    }

    public function testBeschreibungWirdAusDemHtmlIntroTextGewonnen(): void
    {
        $recipe = $this->importFixture();

        self::assertNotNull($recipe->description);
        self::assertStringStartsWith('Willkommen in der Welt der indischen Aromen!', $recipe->description);
        self::assertStringNotContainsString('<p>', $recipe->description);
    }

    public function testBildUrlWirdMitFokuspunktZusammengebaut(): void
    {
        $recipe = $this->importFixture();

        self::assertSame(
            'https://images.foodboom.de/cdn-cgi/image/f=auto,g=0.46x0.61,fit=cover,w=1200,h=960/mp3r5o1a9siicwffikbywq61ha4f',
            $recipe->imageUrl,
        );
    }

    public function testZutatenBehaltenIhreGruppen(): void
    {
        $recipe = $this->importFixture();

        $groups = [];
        foreach ($recipe->ingredients as $ingredient) {
            $groups[(string) $ingredient->groupName] = true;
        }

        self::assertArrayHasKey('Für das Dal:', $groups);
        self::assertCount(2, $groups, 'Erwartet werden zwei Gruppen (Dal und Naanbrot).');
    }

    public function testZutatenWerdenMitAttributenUndEinheitenAbgebildet(): void
    {
        $recipe = $this->importFixture();

        $koernererbsen = $recipe->ingredients[0];
        self::assertSame('250', $koernererbsen->amount);
        self::assertSame('g', $koernererbsen->unit);
        self::assertSame('Körnererbsen, getrocknet', $koernererbsen->name);
        self::assertSame('Für das Dal:', $koernererbsen->groupName);
    }

    /**
     * Ein leeres unit-Objekt bedeutet "zählbar" – nicht die Einheit "0".
     */
    public function testZaehlbareZutatenHabenKeineEinheitUndStehenImPlural(): void
    {
        $recipe = $this->importFixture();

        $zwiebeln = $recipe->ingredients[1];
        self::assertSame('2', $zwiebeln->amount);
        self::assertNull($zwiebeln->unit);
        self::assertSame('Zwiebeln, rot', $zwiebeln->name);
    }

    public function testMengeEinsBenutztDenSingular(): void
    {
        $recipe = $this->importOne([
            'title' => ['one' => 'Zwiebel', 'many' => 'Zwiebeln'],
            'quantity' => 1,
            'unit' => [],
            'attributes' => [],
        ]);

        self::assertSame('Zwiebel', $recipe->ingredients[0]->name);
        self::assertSame('1', $recipe->ingredients[0]->amount);
    }

    public function testZutatOhneMengeHatKeineMengenangabe(): void
    {
        $recipe = $this->importOne([
            'title' => ['one' => 'Salz', 'many' => 'Salz'],
            'quantity' => 0,
            'unit' => [],
            'attributes' => [],
        ]);

        self::assertNull($recipe->ingredients[0]->amount);
        self::assertSame('Salz', $recipe->ingredients[0]->name);
    }

    public function testSchritteWerdenNachOrderSortiertUndEntHtmltGetrimmt(): void
    {
        $recipe = $this->importFixture();

        // 5 Zubereitungsschritte + der redaktionelle Tipp als letzter Schritt.
        self::assertCount(6, $recipe->steps);
        self::assertStringStartsWith('Für das Dal Körnerberbsen ca. 12 Stunden', $recipe->steps[0]->description);
        self::assertStringNotContainsString('<p>', $recipe->steps[1]->description);
        self::assertNull($recipe->steps[0]->title);
    }

    public function testDerRedaktionelleTippWirdLetzterSchritt(): void
    {
        $recipe = $this->importFixture();

        $letzter = $recipe->steps[\count($recipe->steps) - 1];
        self::assertSame('Tipp', $letzter->title);
        self::assertStringContainsString('Für noch mehr Kick beim Naanbrot', $letzter->description);
    }

    public function testTagsWerdenUebernommen(): void
    {
        $recipe = $this->importFixture();

        self::assertContains('Vegetarisch', $recipe->tags);
        self::assertContains('Hauptgang', $recipe->tags);
    }

    public function testFetchWirftWennDieAntwortKeinenDatenknotenHat(): void
    {
        $importer = $this->createImporter(new MockResponse('{"meta":{}}'));

        $this->expectException(RecipeImportException::class);
        $importer->fetch(self::RECIPE_URL);
    }

    public function testFetchWeichtBeiApiFehlerAufJsonLdAus(): void
    {
        $importer = $this->createImporter(
            new MockResponse('', ['http_code' => 500]),
            new MockResponse($this->loadFixture('foodboom-koernererbsen-dal.html')),
        );

        $recipe = $importer->fetch(self::RECIPE_URL);

        self::assertSame('Indisches Körnererbsen-Dal mit Naanbrot', $recipe->title);
        self::assertSame('Hannes', $recipe->author);
        self::assertSame(4, $recipe->servings);
        // "P0Y0M0DT0H720M0S" muss als 720 Minuten gelesen werden.
        self::assertSame(720, $recipe->cookTime);
        self::assertCount(5, $recipe->steps);
        self::assertCount(18, $recipe->ingredients);
    }

    /**
     * Der JSON-LD-Fallback kennt nur unstrukturierte Zutatenzeilen und
     * zerlegt sie heuristisch.
     */
    public function testJsonLdFallbackZerlegtZutatenzeilenHeuristisch(): void
    {
        $importer = $this->createImporter(
            new MockResponse('', ['http_code' => 500]),
            new MockResponse($this->loadFixture('foodboom-koernererbsen-dal.html')),
        );

        $recipe = $importer->fetch(self::RECIPE_URL);

        $erste = $recipe->ingredients[0];
        self::assertSame('250', $erste->amount);
        self::assertSame('g', $erste->unit);
        self::assertSame('Körnererbsen, getrocknet', $erste->name);

        // "Salz" hat weder Menge noch Einheit.
        $salz = $this->findIngredient($recipe, 'Salz');
        self::assertNull($salz->amount);
        self::assertNull($salz->unit);
    }

    private function findIngredient(ImportedRecipe $recipe, string $name): ImportedIngredient
    {
        foreach ($recipe->ingredients as $ingredient) {
            if ($ingredient->name === $name) {
                return $ingredient;
            }
        }

        self::fail(\sprintf('Zutat "%s" nicht gefunden.', $name));
    }

    /**
     * Baut eine Minimal-Antwort mit genau einer Zutat.
     *
     * @param array<string, mixed> $ingredient
     */
    private function importOne(array $ingredient): ImportedRecipe
    {
        $payload = json_encode([
            'data' => [
                'title' => 'Test',
                'ingredientGroups' => [['title' => '', 'ingredients' => [$ingredient]]],
            ],
        ], \JSON_THROW_ON_ERROR);

        return $this->createImporter(new MockResponse($payload))->fetch(self::RECIPE_URL);
    }

    private function importFixture(): ImportedRecipe
    {
        $importer = $this->createImporter(new MockResponse($this->loadFixture('foodboom-koernererbsen-dal.json')));

        return $importer->fetch(self::RECIPE_URL);
    }

    private function createImporter(MockResponse ...$responses): FoodboomImporter
    {
        return new FoodboomImporter(new MockHttpClient($responses), new JsonLdRecipeParser());
    }

    private function loadFixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../Fixtures/Import/' . $name);
        self::assertIsString($content, \sprintf('Fixture "%s" konnte nicht gelesen werden.', $name));

        return $content;
    }
}
