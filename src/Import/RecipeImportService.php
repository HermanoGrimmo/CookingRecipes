<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\Step;
use App\Import\Dto\ImportedRecipe;
use App\Import\Exception\RecipeAlreadyImportedException;
use App\Import\Exception\UnsupportedSourceException;
use App\Repository\RecipeRepository;
use App\Service\TagResolver;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Orchestriert den Rezept-Import: URL normalisieren, zuständigen Importer
 * finden, Doppel-Import erkennen und das Ergebnis auf ein Recipe abbilden.
 *
 * Speichert bewusst nichts – das erzeugte Rezept wird im Formular vorbefüllt
 * und erst durch den Nutzer gespeichert.
 */
final class RecipeImportService
{
    /**
     * @param iterable<RecipeImporterInterface> $importers
     */
    public function __construct(
        #[AutowireIterator('app.recipe_importer')]
        private readonly iterable $importers,
        private readonly RecipeRepository $recipeRepository,
        private readonly TagResolver $tagResolver,
    ) {
    }

    /**
     * Importiert das Rezept hinter der URL als noch nicht gespeichertes
     * Recipe-Objekt.
     *
     * @throws UnsupportedSourceException      wenn keine Quelle passt
     * @throws RecipeAlreadyImportedException  wenn die URL bereits importiert wurde
     * @throws Exception\RecipeImportException bei Transport- oder Parse-Fehlern
     */
    public function import(string $url): Recipe
    {
        $url = $this->normalizeUrl($url);
        $importer = $this->findImporter($url);

        $existing = $this->recipeRepository->findOneBySourceUrl($url);
        if (null !== $existing) {
            throw new RecipeAlreadyImportedException($existing);
        }

        return $this->toEntity($importer->fetch($url));
    }

    /**
     * Namen aller unterstützten Quellen – für Hinweis- und Fehlertexte.
     *
     * @return list<string>
     */
    public function getSupportedSourceNames(): array
    {
        $names = [];
        foreach ($this->importers as $importer) {
            $names[] = $importer->getSourceName();
        }

        return $names;
    }

    /** Ob für die URL überhaupt ein Importer existiert (für die Validierung). */
    public function supports(string $url): bool
    {
        $url = $this->normalizeUrl($url);

        foreach ($this->importers as $importer) {
            if ($importer->supports($url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vereinheitlicht die URL, damit dieselbe Seite immer denselben
     * source_url-Wert ergibt: kein http, kein Query-String, kein Fragment,
     * kein abschließender Slash.
     */
    public function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (1 === preg_match('#^http://#i', $url)) {
            $url = 'https://' . substr($url, 7);
        }

        // Query und Fragment tragen bei beiden Quellen nur Tracking-Parameter.
        $url = (string) preg_replace('/[?#].*$/', '', $url);

        return rtrim($url, '/');
    }

    private function findImporter(string $url): RecipeImporterInterface
    {
        foreach ($this->importers as $importer) {
            if ($importer->supports($url)) {
                return $importer;
            }
        }

        throw new UnsupportedSourceException($url);
    }

    /**
     * Bildet das DTO auf ein noch nicht persistiertes Recipe ab.
     *
     * position und number werden hier gesetzt, damit die Reihenfolge auch
     * ohne Formular-Roundtrip stimmt; RecipeForm::save() vergibt sie beim
     * Speichern ohnehin noch einmal neu.
     */
    private function toEntity(ImportedRecipe $imported): Recipe
    {
        $recipe = new Recipe();
        $recipe
            ->setTitle($imported->title)
            ->setDescription($imported->description)
            ->setAuthor($imported->author)
            ->setImagePath($imported->imageUrl)
            ->setServings($imported->servings)
            ->setPrepTime($imported->prepTime)
            ->setCookTime($imported->cookTime)
            ->setRestTime($imported->restTime)
            ->setDifficulty($imported->difficulty)
            ->setSourceUrl($imported->sourceUrl)
            ->setSourceName($imported->sourceName);

        $position = 0;
        foreach ($imported->ingredients as $importedIngredient) {
            $ingredient = new Ingredient();
            $ingredient
                ->setAmount($importedIngredient->amount)
                ->setUnit($importedIngredient->unit)
                ->setName($importedIngredient->name)
                ->setGroupName($importedIngredient->groupName)
                ->setPosition($position++);

            $recipe->addIngredient($ingredient);
        }

        $number = 1;
        foreach ($imported->steps as $importedStep) {
            $step = new Step();
            $step
                ->setNumber($number++)
                ->setTitle($importedStep->title)
                ->setDescription($importedStep->description);

            $recipe->addStep($step);
        }

        foreach ($this->tagResolver->resolveAll($imported->tags) as $tag) {
            $recipe->addTag($tag);
        }

        return $recipe;
    }
}
