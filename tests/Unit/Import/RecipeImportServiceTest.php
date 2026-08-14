<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Entity\Recipe;
use App\Entity\Tag;
use App\Import\Dto\ImportedIngredient;
use App\Import\Dto\ImportedRecipe;
use App\Import\Dto\ImportedStep;
use App\Import\Exception\RecipeAlreadyImportedException;
use App\Import\Exception\RecipeImportException;
use App\Import\Exception\UnsupportedSourceException;
use App\Import\RecipeImporterInterface;
use App\Import\RecipeImportService;
use App\Repository\RecipeRepository;
use App\Repository\TagRepository;
use App\Service\TagResolver;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für die Import-Orchestrierung.
 */
class RecipeImportServiceTest extends TestCase
{
    public function testNormalizeUrlVereinheitlichtProtokollQueryUndSlash(): void
    {
        $service = $this->createService();

        self::assertSame(
            'https://www.chefkoch.de/rezepte/123/Test.html',
            $service->normalizeUrl('  http://www.chefkoch.de/rezepte/123/Test.html?utm_source=x#step1  '),
        );

        self::assertSame(
            'https://www.foodboom.de/rezept/dal',
            $service->normalizeUrl('https://www.foodboom.de/rezept/dal/'),
        );
    }

    public function testSupportsMeldetNurRegistrierteQuellen(): void
    {
        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')]);

        self::assertTrue($service->supports('https://example.org/rezept/1'));
        self::assertFalse($service->supports('https://andere-seite.de/rezept/1'));
    }

    /**
     * Auch eine http-URL mit Tracking-Parametern muss den Importer finden –
     * die Normalisierung greift vor der Auswahl.
     */
    public function testSupportsNormalisiertVorDerPruefung(): void
    {
        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')]);

        self::assertTrue($service->supports('http://example.org/rezept/1?utm_medium=mail'));
    }

    public function testGetSupportedSourceNamesListetAlleImporter(): void
    {
        $service = $this->createService([
            $this->createImporter('Chefkoch', 'https://www.chefkoch.de/'),
            $this->createImporter('FOODBOOM', 'https://www.foodboom.de/'),
        ]);

        self::assertSame(['Chefkoch', 'FOODBOOM'], $service->getSupportedSourceNames());
    }

    public function testImportWirftBeiNichtUnterstuetzterQuelle(): void
    {
        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')]);

        $this->expectException(UnsupportedSourceException::class);
        $service->import('https://www.youtube.com/watch?v=abc');
    }

    public function testImportWirftWennDieQuellUrlBereitsImportiertWurde(): void
    {
        $vorhandenes = (new Recipe())->setTitle('Schon da');

        $repository = $this->createMock(RecipeRepository::class);
        $repository->method('findOneBySourceUrl')->with('https://example.org/rezept/1')->willReturn($vorhandenes);

        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')], $repository);

        try {
            $service->import('https://example.org/rezept/1');
            self::fail('RecipeAlreadyImportedException erwartet.');
        } catch (RecipeAlreadyImportedException $e) {
            self::assertSame($vorhandenes, $e->existingRecipe);
        }
    }

    /**
     * Die Dedupe-Prüfung muss auf der normalisierten URL laufen, sonst
     * entstehen Duplikate über Tracking-Parameter.
     */
    public function testDedupePruefungNutztDieNormalisierteUrl(): void
    {
        $repository = $this->createMock(RecipeRepository::class);
        $repository->expects(self::once())
            ->method('findOneBySourceUrl')
            ->with('https://example.org/rezept/1')
            ->willReturn(null);

        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')], $repository);
        $service->import('http://example.org/rezept/1/?utm_source=newsletter');
    }

    public function testImportReichtFehlerDesImportersDurch(): void
    {
        $importer = $this->createMock(RecipeImporterInterface::class);
        $importer->method('getSourceName')->willReturn('Testquelle');
        $importer->method('supports')->willReturn(true);
        $importer->method('fetch')->willThrowException(new RecipeImportException('Netzwerkfehler'));

        $service = $this->createService([$importer]);

        $this->expectException(RecipeImportException::class);
        $service->import('https://example.org/rezept/1');
    }

    public function testImportBildetDasDtoAufEinNichtGespeichertesRezeptAb(): void
    {
        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')]);

        $recipe = $service->import('https://example.org/rezept/1');

        self::assertNull($recipe->getId(), 'Der Service darf nichts persistieren.');
        self::assertSame('Testrezept', $recipe->getTitle());
        self::assertSame('Beschreibung', $recipe->getDescription());
        self::assertSame('Originalautor', $recipe->getAuthor());
        self::assertSame('https://example.org/bild.jpg', $recipe->getImagePath());
        self::assertSame(2, $recipe->getServings());
        self::assertSame(10, $recipe->getPrepTime());
        self::assertSame(20, $recipe->getCookTime());
        self::assertSame(720, $recipe->getRestTime());
        self::assertSame('mittel', $recipe->getDifficulty());
        self::assertSame('https://example.org/rezept/1', $recipe->getSourceUrl());
        self::assertSame('Testquelle', $recipe->getSourceName());
        // importedAt setzt erst RecipeForm::save() beim Speichern.
        self::assertNull($recipe->getImportedAt());
    }

    public function testImportVergibtPositionenUndSchrittnummern(): void
    {
        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')]);

        $recipe = $service->import('https://example.org/rezept/1');

        $ingredients = $recipe->getIngredients()->toArray();
        self::assertCount(2, $ingredients);
        self::assertSame(0, $ingredients[0]->getPosition());
        self::assertSame(1, $ingredients[1]->getPosition());
        self::assertSame('Für die Sauce', $ingredients[0]->getGroupName());
        self::assertSame($recipe, $ingredients[0]->getRecipe());

        $steps = $recipe->getSteps()->toArray();
        self::assertCount(2, $steps);
        self::assertSame(1, $steps[0]->getNumber());
        self::assertSame(2, $steps[1]->getNumber());
        self::assertSame('Tipp', $steps[1]->getTitle());
    }

    public function testImportLoestTagsAufUndVerwirftDuplikate(): void
    {
        $service = $this->createService([$this->createImporter('Testquelle', 'https://example.org/')]);

        $recipe = $service->import('https://example.org/rezept/1');

        $names = array_map(static fn (Tag $tag): string => $tag->getName(), $recipe->getTags()->toArray());

        // "Pasta", " ", "pasta" und "Vegetarisch" ergeben zwei Tags.
        self::assertSame(['Pasta', 'Vegetarisch'], $names);
    }

    /**
     * @param list<RecipeImporterInterface> $importers
     */
    private function createService(array $importers = [], ?RecipeRepository $repository = null): RecipeImportService
    {
        if (null === $repository) {
            $repository = $this->createMock(RecipeRepository::class);
            $repository->method('findOneBySourceUrl')->willReturn(null);
        }

        $tagRepository = $this->createMock(TagRepository::class);
        $tagRepository->method('findByNames')->willReturn([]);

        return new RecipeImportService($importers, $repository, new TagResolver($tagRepository));
    }

    /**
     * Ein Importer, der für alle URLs unterhalb des Präfixes zuständig ist und
     * ein festes Rezept liefert.
     */
    private function createImporter(string $sourceName, string $urlPrefix): RecipeImporterInterface
    {
        $importer = $this->createMock(RecipeImporterInterface::class);
        $importer->method('getSourceName')->willReturn($sourceName);
        $importer->method('supports')->willReturnCallback(
            static fn (string $url): bool => str_starts_with($url, $urlPrefix),
        );
        $importer->method('fetch')->willReturnCallback(
            static fn (string $url): ImportedRecipe => new ImportedRecipe(
                title: 'Testrezept',
                description: 'Beschreibung',
                author: 'Originalautor',
                imageUrl: 'https://example.org/bild.jpg',
                servings: 2,
                prepTime: 10,
                cookTime: 20,
                restTime: 720,
                difficulty: 'mittel',
                ingredients: [
                    new ImportedIngredient('500', 'g', 'Penne', 'Für die Sauce'),
                    new ImportedIngredient(null, null, 'Salz', 'Für die Sauce'),
                ],
                steps: [
                    new ImportedStep('Erst dies.'),
                    new ImportedStep('Und noch ein Hinweis.', 'Tipp'),
                ],
                tags: ['Pasta', ' ', 'pasta', 'Vegetarisch'],
                sourceUrl: $url,
                sourceName: $sourceName,
            ),
        );

        return $importer;
    }
}
