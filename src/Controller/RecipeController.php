<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Form\RecipeImportType;
use App\Import\Exception\RecipeAlreadyImportedException;
use App\Import\Exception\RecipeImportException;
use App\Import\RecipeImportService;
use App\Repository\RecipeRatingRepository;
use App\Security\RecipeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller für die Rezeptverwaltung (Übersicht, Detail, Neu, Bearbeiten).
 *
 * Das Erstellen und Bearbeiten von Rezepten wird vom RecipeForm Live Component
 * übernommen – die Controller-Actions rendern lediglich die Seiten und
 * übergeben das Recipe-Objekt an die Komponente.
 */
class RecipeController extends AbstractController
{
    #[Route('/', name: 'recipe_index')]
    public function index(): Response
    {
        // Das eigentliche Rendern der Liste übernimmt das RecipeList Live Component.
        return $this->render('recipe/index.html.twig');
    }

    /** Muss VOR recipe_show stehen, damit "neu" nicht als ID interpretiert wird. */
    #[Route('/rezept/neu', name: 'recipe_new')]
    public function new(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        return $this->render('recipe/new.html.twig', [
            'recipe' => new Recipe(),
        ]);
    }

    /**
     * Importiert ein Rezept von einer unterstützten Seite und füllt damit das
     * normale Anlege-Formular vor. Gespeichert wird erst durch den Nutzer.
     *
     * Muss – wie recipe_new – VOR recipe_show stehen.
     */
    #[Route('/rezept/importieren', name: 'recipe_import', methods: ['GET', 'POST'])]
    public function import(Request $request, RecipeImportService $importService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $form = $this->createForm(RecipeImportType::class);
        $form->handleRequest($request);

        // Bereits importiertes Rezept – wird im Template als Hinweis mit Link
        // gerendert (Flash-Nachrichten werden escaped und können keinen
        // Link enthalten).
        $duplicate = null;

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{url: string} $data */
            $data = $form->getData();

            try {
                $recipe = $importService->import($data['url']);

                $this->addFlash('success', \sprintf('Rezept von %s geladen. Bitte prüfen und speichern.', $recipe->getSourceName() ?? 'der Quelle'));

                return $this->render('recipe/new.html.twig', [
                    'recipe' => $recipe,
                    'sourceUrl' => $recipe->getSourceUrl(),
                    'sourceName' => $recipe->getSourceName(),
                    'importedAuthor' => $recipe->getAuthor(),
                ]);
            } catch (RecipeAlreadyImportedException $e) {
                $duplicate = $e->existingRecipe;
            } catch (RecipeImportException) {
                // Details (Timeouts, geänderte APIs) gehören nicht in die UI.
                $this->addFlash('error', 'Das Rezept konnte nicht geladen werden. Bitte die URL prüfen und es später erneut versuchen.');
            }
        }

        return $this->render('recipe/import.html.twig', [
            'form' => $form,
            'sources' => $importService->getSupportedSourceNames(),
            'duplicate' => $duplicate,
        ]);
    }

    #[Route('/rezept/{id}', name: 'recipe_show', requirements: ['id' => '\d+'])]
    public function show(Recipe $recipe, RecipeRatingRepository $ratingRepository): Response
    {
        $user = $this->getUser();
        $personalScore = $user instanceof User ? $ratingRepository->findForUserAndRecipe($user, $recipe)?->getScore() : null;

        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe,
            'personalScore' => $personalScore,
        ]);
    }

    #[Route('/rezept/{id}/bearbeiten', name: 'recipe_edit', requirements: ['id' => '\d+'])]
    public function edit(Recipe $recipe): Response
    {
        $this->denyAccessUnlessGranted(RecipeVoter::EDIT, $recipe);

        return $this->render('recipe/edit.html.twig', [
            'recipe' => $recipe,
        ]);
    }

    #[Route('/rezept/{id}/loeschen', name: 'recipe_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(RecipeVoter::DELETE, $recipe);

        if ($this->isCsrfTokenValid('delete_' . $recipe->getId(), (string) $request->request->get('_token'))) {
            $em->remove($recipe);
            $em->flush();
            $this->addFlash('success', 'Rezept wurde gelöscht.');
        } else {
            $this->addFlash('error', 'Das Rezept konnte nicht gelöscht werden (ungültiges Sicherheits-Token). Bitte versuche es erneut.');
        }

        return $this->redirectToRoute('recipe_index');
    }
}
