<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Recipe;
use App\Entity\User;
use App\Form\RecipeType;
use App\Repository\RecipeRepository;
use App\Security\RecipeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller für die Rezeptverwaltung (Übersicht, Detail, Neu, Bearbeiten).
 */
class RecipeController extends AbstractController
{
    #[Route('/', name: 'recipe_index')]
    public function index(RecipeRepository $recipeRepository): Response
    {
        return $this->render('recipe/index.html.twig', [
            'recipes' => $recipeRepository->findAllOrderedByNewest(),
        ]);
    }

    /** Muss VOR recipe_show stehen, damit "neu" nicht als ID interpretiert wird. */
    #[Route('/rezept/neu', name: 'recipe_new')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $recipe = new Recipe();
        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Eingeloggten Benutzer als Eigentümer setzen
            $user = $this->getUser();
            \assert($user instanceof User);
            $recipe->setOwner($user);
            $recipe->setAuthor($user->getFullName());

            $this->fixIngredientPositions($recipe);
            $this->fixStepNumbers($recipe);
            $em->persist($recipe);
            $em->flush();

            $this->addFlash('success', 'Rezept wurde erfolgreich erstellt!');

            return $this->redirectToRoute('recipe_show', ['id' => $recipe->getId()]);
        }

        return $this->render('recipe/new.html.twig', [
            'form' => $form,
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
    public function edit(Request $request, Recipe $recipe, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(RecipeVoter::EDIT, $recipe);

        $form = $this->createForm(RecipeType::class, $recipe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->fixIngredientPositions($recipe);
            $this->fixStepNumbers($recipe);
            $em->flush();

            $this->addFlash('success', 'Rezept wurde erfolgreich aktualisiert!');

            return $this->redirectToRoute('recipe_show', ['id' => $recipe->getId()]);
        }

        return $this->render('recipe/edit.html.twig', [
            'form' => $form,
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

    /** Setzt die Position der Zutaten basierend auf ihrer Reihenfolge im Formular. */
    private function fixIngredientPositions(Recipe $recipe): void
    {
        foreach ($recipe->getIngredients() as $index => $ingredient) {
            $ingredient->setPosition($index);
        }
    }

    /** Setzt die Schrittnummern basierend auf ihrer Reihenfolge im Formular. */
    private function fixStepNumbers(Recipe $recipe): void
    {
        foreach ($recipe->getSteps() as $index => $step) {
            $step->setNumber($index + 1);
        }
    }
}
