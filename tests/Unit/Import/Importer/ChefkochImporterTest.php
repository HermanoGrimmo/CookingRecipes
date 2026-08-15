<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import\Importer;

use App\Import\Dto\ImportedRecipe;
use App\Import\Exception\RecipeImportException;
use App\Import\Importer\ChefkochImporter;
use App\Import\JsonLdRecipeParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Unit-Tests für den Chefkoch-Importer.
 *
 * Die Fixture ist die unveränderte Antwort von
 * https://api.chefkoch.de/v2/recipes/1563431263845794 (Penne mit
 * Ofentomatensauce).
 */
class ChefkochImporterTest extends TestCase
{
    private const string RECIPE_URL = 'https://www.chefkoch.de/rezepte/1563431263845794/Penne-mit-Ofentomatensauce.html';

    public function testSupportsErkenntChefkochRezeptUrls(): void
    {
        $importer = $this->createImporter(new MockResponse('{}'));

        self::assertTrue($importer->supports(self::RECIPE_URL));
        self::assertTrue($importer->supports('https://chefkoch.de/rezepte/1234567890/Irgendwas.html'));

        self::assertFalse($importer->supports('https://www.chefkoch.de/magazin/artikel/1,0/Chefkoch/Tomaten.html'));
        self::assertFalse($importer->supports('https://www.foodboom.de/rezept/irgendwas'));
        self::assertFalse($importer->supports('https://www.youtube.com/watch?v=abc'));
    }

    /**
     * RecipeImportService::normalizeUrl() entfernt einen abschließenden
     * Slash. Eine slug-lose URL (kein Titel im Pfad) darf danach nicht
     * plötzlich unerkannt sein.
     */
    public function testSupportsErkenntSlugloseUrlsAuchOhneAbschliessendenSlash(): void
    {
        $importer = $this->createImporter(new MockResponse('{}'));

        self::assertTrue($importer->supports('https://www.chefkoch.de/rezepte/1563431263845794'));
        self::assertTrue($importer->supports('https://www.chefkoch.de/rezepte/1563431263845794/'));
    }

    public function testFetchLiestDieEckdatenAusDerApi(): void
    {
        $recipe = $this->importFixture();

        self::assertSame('Penne mit Ofentomatensauce', $recipe->title);
        self::assertSame('mit frischen Tomaten, schnell und einfach', $recipe->description);
        self::assertSame('anfieta', $recipe->author);
        self::assertSame(4, $recipe->servings);
        self::assertSame(15, $recipe->prepTime);
        self::assertSame(25, $recipe->cookTime);
        self::assertSame(0, $recipe->restTime);
        self::assertSame('einfach', $recipe->difficulty);
        self::assertSame(self::RECIPE_URL, $recipe->sourceUrl);
        self::assertSame('Chefkoch', $recipe->sourceName);
    }

    public function testFetchSetztDasBildformatInDieUrlEin(): void
    {
        $recipe = $this->importFixture();

        self::assertSame(
            'https://img.chefkoch-cdn.de/rezepte/1563431263845794/bilder/1131184/crop-960x540/penne-mit-ofentomatensauce.jpg',
            $recipe->imageUrl,
        );
    }

    public function testFetchZerlegtDieZutatenInMengeEinheitUndName(): void
    {
        $recipe = $this->importFixture();

        self::assertCount(10, $recipe->ingredients);

        $penne = $recipe->ingredients[0];
        self::assertSame('500', $penne->amount);
        self::assertSame('g', $penne->unit);
        self::assertSame('Penne', $penne->name);

        // Zählbare Zutaten haben in der API eine leere Einheit.
        $tomaten = $recipe->ingredients[1];
        self::assertSame('10', $tomaten->amount);
        self::assertNull($tomaten->unit);
        self::assertSame('Tomate(n)', $tomaten->name);
    }

    public function testFetchHaengtUsageInfoAnDenZutatennamen(): void
    {
        $recipe = $this->importFixture();

        $thymian = $recipe->ingredients[4];
        self::assertSame('Thymian (oder 1/2 TL getrockneter)', $thymian->name);
        self::assertSame('Zweig/e', $thymian->unit);
    }

    public function testFetchLaesstDenGruppennamenLeerWennEsKeineGruppenGibt(): void
    {
        $recipe = $this->importFixture();

        // Der Header dieses Rezepts ist ein einzelnes Leerzeichen.
        foreach ($recipe->ingredients as $ingredient) {
            self::assertNull($ingredient->groupName);
        }
    }

    public function testFetchTrenntDieZubereitungAnLeerzeilenInSchritte(): void
    {
        $recipe = $this->importFixture();

        self::assertCount(3, $recipe->steps);
        self::assertStringStartsWith('Die Tomaten mit einem scharfen Messer', $recipe->steps[0]->description);
        self::assertStringStartsWith('Ein Backblech mit Zucker bestreuen', $recipe->steps[1]->description);
        self::assertStringStartsWith('In der Zwischenzeit die Nudeln al dente', $recipe->steps[2]->description);
        self::assertNull($recipe->steps[0]->title);
    }

    public function testFetchUebernimmtDieTags(): void
    {
        $recipe = $this->importFixture();

        self::assertContains('Pasta', $recipe->tags);
        self::assertContains('vegetarisch', $recipe->tags);
        self::assertContains('Hauptspeise', $recipe->tags);
    }

    /**
     * Die Chefkoch-Skala ist 1–3; alles andere fällt auf "einfach" zurück.
     */
    public function testSchwierigkeitWirdAufDieWerteDerAnwendungAbgebildet(): void
    {
        foreach ([1 => 'einfach', 2 => 'mittel', 3 => 'schwer', 99 => 'einfach'] as $apiValue => $expected) {
            $importer = $this->createImporter(new MockResponse(json_encode([
                'title' => 'Test',
                'difficulty' => $apiValue,
            ], \JSON_THROW_ON_ERROR)));

            self::assertSame($expected, $importer->fetch(self::RECIPE_URL)->difficulty);
        }
    }

    public function testFetchWirftBeiAntwortOhneTitel(): void
    {
        $importer = $this->createImporter(new MockResponse('{"id":"123"}'));

        $this->expectException(RecipeImportException::class);
        $importer->fetch(self::RECIPE_URL);
    }

    public function testFetchWirftBeiUnpassenderUrl(): void
    {
        $importer = $this->createImporter(new MockResponse('{}'));

        $this->expectException(RecipeImportException::class);
        $importer->fetch('https://www.chefkoch.de/magazin/artikel/1,0/Chefkoch/Tomaten.html');
    }

    /**
     * Fällt die API aus, werden die JSON-LD-Daten der HTML-Seite gelesen.
     */
    public function testFetchWeichtBeiApiFehlerAufJsonLdAus(): void
    {
        $importer = $this->createImporter(
            new MockResponse('', ['http_code' => 503]),
            new MockResponse($this->loadFixture('chefkoch-penne-ofentomatensauce.html')),
        );

        $recipe = $importer->fetch(self::RECIPE_URL);

        self::assertSame('Penne mit Ofentomatensauce von anfieta', $recipe->title);
        self::assertSame(4, $recipe->servings);
        self::assertSame(15, $recipe->prepTime);
        self::assertSame(25, $recipe->cookTime);
        self::assertCount(3, $recipe->steps);
        self::assertCount(10, $recipe->ingredients);
        // Autor und Bild stehen im JSON-LD nur als @id-Referenz auf andere
        // Knoten des Graphen und müssen aufgelöst werden.
        self::assertSame('anfieta', $recipe->author);
        self::assertStringContainsString('penne-mit-ofentomatensauce', (string) $recipe->imageUrl);
    }

    private function importFixture(): ImportedRecipe
    {
        $importer = $this->createImporter(new MockResponse($this->loadFixture('chefkoch-penne-ofentomatensauce.json')));

        return $importer->fetch(self::RECIPE_URL);
    }

    private function createImporter(MockResponse ...$responses): ChefkochImporter
    {
        return new ChefkochImporter(new MockHttpClient($responses), new JsonLdRecipeParser());
    }

    private function loadFixture(string $name): string
    {
        $content = file_get_contents(__DIR__ . '/../../../Fixtures/Import/' . $name);
        self::assertIsString($content, \sprintf('Fixture "%s" konnte nicht gelesen werden.', $name));

        return $content;
    }
}
