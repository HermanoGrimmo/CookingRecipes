<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Dto\ImportedRecipe;
use App\Import\Exception\RecipeImportException;
use App\Import\JsonLdRecipeParser;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für den JSON-LD-Fallback.
 *
 * Deckt die real vorkommenden Formvarianten von schema.org/Recipe ab –
 * recipeInstructions und recipeYield sind je nach Seite unterschiedlich
 * strukturiert.
 */
class JsonLdRecipeParserTest extends TestCase
{
    private JsonLdRecipeParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonLdRecipeParser();
    }

    public function testWirftWennKeinRecipeKnotenVorhandenIst(): void
    {
        $this->expectException(RecipeImportException::class);
        $this->parse(['@type' => 'WebPage', 'name' => 'Kein Rezept']);
    }

    public function testWirftWennDerTitelFehlt(): void
    {
        $this->expectException(RecipeImportException::class);
        $this->parse(['@type' => 'Recipe', 'description' => 'ohne Namen']);
    }

    public function testFindetDenRecipeKnotenAufOberterEbene(): void
    {
        $recipe = $this->parse(['@type' => 'Recipe', 'name' => 'Direkt']);

        self::assertSame('Direkt', $recipe->title);
    }

    public function testFindetDenRecipeKnotenInEinemGraph(): void
    {
        $recipe = $this->parse([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'WebPage', 'name' => 'Seite'],
                ['@type' => 'Recipe', 'name' => 'Im Graph'],
            ],
        ]);

        self::assertSame('Im Graph', $recipe->title);
    }

    public function testFindetDenRecipeKnotenInEinerListe(): void
    {
        $recipe = $this->parse([
            ['@type' => 'Organization', 'name' => 'Verlag'],
            ['@type' => 'Recipe', 'name' => 'In Liste'],
        ]);

        self::assertSame('In Liste', $recipe->title);
    }

    public function testErkenntRecipeAuchBeiMehrerenTypen(): void
    {
        $recipe = $this->parse(['@type' => ['Recipe', 'NewsArticle'], 'name' => 'Mehrfachtyp']);

        self::assertSame('Mehrfachtyp', $recipe->title);
    }

    /**
     * @return iterable<string, array{mixed, int}>
     */
    public static function yieldProvider(): iterable
    {
        yield 'int (FOODBOOM)' => [4, 4];
        yield 'array (Chefkoch)' => [['4', '4 Portionen'], 4];
        yield 'string mit Text' => ['6 Portionen', 6];
        yield 'nicht gesetzt' => [null, 4];
        yield 'unbrauchbar' => ['nach Bedarf', 4];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('yieldProvider')]
    public function testRecipeYieldWirdInAllenFormenGelesen(mixed $value, int $expected): void
    {
        $recipe = $this->parse(['@type' => 'Recipe', 'name' => 'T', 'recipeYield' => $value]);

        self::assertSame($expected, $recipe->servings);
    }

    /**
     * @return iterable<string, array{string|null, int}>
     */
    public static function durationProvider(): iterable
    {
        yield 'kurze Form (Chefkoch)' => ['PT15M', 15];
        yield 'lange Form (FOODBOOM)' => ['P0Y0M0DT0H720M0S', 720];
        yield 'mit Stunden' => ['PT1H30M', 90];
        yield 'mit Tagen' => ['P1DT2H', 1560];
        yield 'leer' => ['', 0];
        yield 'ungueltig' => ['keine Dauer', 0];
        yield 'fehlt' => [null, 0];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('durationProvider')]
    public function testDauernWerdenInMinutenUmgerechnet(?string $value, int $expected): void
    {
        $recipe = $this->parse(['@type' => 'Recipe', 'name' => 'T', 'prepTime' => $value]);

        self::assertSame($expected, $recipe->prepTime);
    }

    public function testInstructionsAlsStringWerdenAnLeerzeilenGetrennt(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'recipeInstructions' => "Erster Schritt.\n\nZweiter Schritt.",
        ]);

        self::assertCount(2, $recipe->steps);
        self::assertSame('Erster Schritt.', $recipe->steps[0]->description);
    }

    public function testInstructionsAlsStringlisteWerdenUebernommen(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'recipeInstructions' => ['Eins.', '  ', 'Zwei.'],
        ]);

        self::assertCount(2, $recipe->steps);
        self::assertSame('Zwei.', $recipe->steps[1]->description);
    }

    public function testInstructionsAlsHowToStepListeWerdenUebernommen(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'position' => 1, 'text' => 'Schritt A'],
                ['@type' => 'HowToStep', 'position' => 2, 'text' => 'Schritt B'],
            ],
        ]);

        self::assertCount(2, $recipe->steps);
        self::assertSame('Schritt A', $recipe->steps[0]->description);
    }

    public function testInstructionsAlsHowToSectionWerdenFlachgezogen(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'recipeInstructions' => [[
                '@type' => 'HowToSection',
                'name' => 'Zubereitung',
                'itemListElement' => [
                    ['@type' => 'HowToStep', 'text' => 'Erst dies'],
                    ['@type' => 'HowToStep', 'text' => 'Dann das'],
                ],
            ]],
        ]);

        self::assertCount(2, $recipe->steps);
        self::assertSame('Dann das', $recipe->steps[1]->description);
    }

    public function testKeywordsAlsKommastringWerdenZerlegt(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'keywords' => 'Gemüse, Hauptspeise, Pasta',
        ]);

        self::assertSame(['Gemüse', 'Hauptspeise', 'Pasta'], $recipe->tags);
    }

    public function testKeywordsAlsListeWerdenUebernommen(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'keywords' => ['Alltag', ' ', 'Vegetarisch'],
        ]);

        self::assertSame(['Alltag', 'Vegetarisch'], $recipe->tags);
    }

    public function testAutorUndBildWerdenUeberIdReferenzenAufgeloest(): void
    {
        $recipe = $this->parse([
            '@graph' => [
                [
                    '@type' => 'Recipe',
                    'name' => 'T',
                    'author' => ['@id' => 'https://example.org/#author'],
                    'image' => ['@id' => 'https://example.org/#primaryimage'],
                ],
                ['@type' => 'Person', '@id' => 'https://example.org/#author', 'name' => 'Referenzierter Autor'],
                ['@type' => 'ImageObject', '@id' => 'https://example.org/#primaryimage', 'contentUrl' => 'https://example.org/bild.jpg'],
            ],
        ]);

        self::assertSame('Referenzierter Autor', $recipe->author);
        self::assertSame('https://example.org/bild.jpg', $recipe->imageUrl);
    }

    public function testAutorFaelltAufDenQuellennamenZurueck(): void
    {
        $recipe = $this->parse(['@type' => 'Recipe', 'name' => 'T']);

        self::assertSame('Testquelle', $recipe->author);
    }

    public function testBildAlsEinfacherString(): void
    {
        $recipe = $this->parse(['@type' => 'Recipe', 'name' => 'T', 'image' => 'https://example.org/a.jpg']);

        self::assertSame('https://example.org/a.jpg', $recipe->imageUrl);
    }

    public function testBildAlsListeNimmtDenErstenEintrag(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'image' => ['https://example.org/a.jpg', 'https://example.org/b.jpg'],
        ]);

        self::assertSame('https://example.org/a.jpg', $recipe->imageUrl);
    }

    public function testZutatenzeilenWerdenHeuristischZerlegt(): void
    {
        $recipe = $this->parse([
            '@type' => 'Recipe',
            'name' => 'T',
            'recipeIngredient' => [
                '500 g Penne',
                '2 Knoblauchzehe(n)',
                '3 EL Olivenöl',
                'Salz und Pfeffer',
                '1200 ml Gemüsebrühe',
            ],
        ]);

        self::assertCount(5, $recipe->ingredients);

        self::assertSame('500', $recipe->ingredients[0]->amount);
        self::assertSame('g', $recipe->ingredients[0]->unit);
        self::assertSame('Penne', $recipe->ingredients[0]->name);

        // "Knoblauchzehe(n)" ist der Name, keine Einheit.
        self::assertSame('2', $recipe->ingredients[1]->amount);
        self::assertNull($recipe->ingredients[1]->unit);
        self::assertSame('Knoblauchzehe(n)', $recipe->ingredients[1]->name);

        self::assertSame('3', $recipe->ingredients[2]->amount);
        self::assertSame('EL', $recipe->ingredients[2]->unit);

        self::assertNull($recipe->ingredients[3]->amount);
        self::assertNull($recipe->ingredients[3]->unit);
        self::assertSame('Salz und Pfeffer', $recipe->ingredients[3]->name);

        self::assertSame('1200', $recipe->ingredients[4]->amount);
        self::assertSame('ml', $recipe->ingredients[4]->unit);
    }

    public function testUngueltigesJsonLdWirdUebersprungen(): void
    {
        $html = '<html><head>'
            . '<script type="application/ld+json">{ kaputt </script>'
            . '<script type="application/ld+json">{"@type":"Recipe","name":"Zweiter Block"}</script>'
            . '</head><body></body></html>';

        $recipe = $this->parser->parse($html, 'https://example.org/r', 'Testquelle');

        self::assertSame('Zweiter Block', $recipe->title);
    }

    /**
     * Baut eine Seite mit genau einem JSON-LD-Block um die Nutzdaten herum.
     *
     * @param array<mixed> $jsonLd
     */
    private function parse(array $jsonLd): ImportedRecipe
    {
        $html = '<html lang="de"><head><meta charset="utf-8">'
            . '<script type="application/ld+json">' . json_encode($jsonLd, \JSON_THROW_ON_ERROR) . '</script>'
            . '</head><body></body></html>';

        return $this->parser->parse($html, 'https://example.org/rezept', 'Testquelle');
    }
}
