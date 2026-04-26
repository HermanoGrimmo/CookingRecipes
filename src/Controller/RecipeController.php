<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Recipe;
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

    #[Route('/rezept/{id}', name: 'recipe_show', requirements: ['id' => '\d+'])]
    public function show(Recipe $recipe): Response
    {
        return $this->render('recipe/show.html.twig', [
            'recipe' => $recipe,
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
        }

        return $this->redirectToRoute('recipe_index');
    }
}
