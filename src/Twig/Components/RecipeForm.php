<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Ingredient;
use App\Entity\Recipe;
use App\Entity\Step;
use App\Entity\User;
use App\Form\RecipeType;
use App\Security\RecipeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentWithFormTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live Component für das Rezept-Formular.
 *
 * Übernimmt das dynamische Hinzufügen und Entfernen von Zutaten- und
 * Schritt-Zeilen sowie das Speichern des Rezepts ohne handgeschriebenes
 * JavaScript.
 */
#[AsLiveComponent]
final class RecipeForm extends AbstractController
{
    use ComponentWithFormTrait;
    use DefaultActionTrait;

    /** Wird vom Aufrufer übergeben (neues oder bestehendes Rezept). */
    #[LiveProp]
    public ?Recipe $initialFormData = null;

    /** URL für den „Abbrechen"-Link. */
    #[LiveProp]
    public string $backUrl = '/';

    /** Beschriftung des Submit-Buttons. */
    #[LiveProp]
    public string $submitLabel = 'Rezept speichern';

    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /**
     * Erzeugt den Symfony-Form für die Live-Component-Bindung.
     *
     * @return FormInterface<Recipe>
     */
    protected function instantiateForm(): FormInterface
    {
        $recipe = $this->initialFormData ?? new Recipe();

        return $this->createForm(RecipeType::class, $recipe);
    }

    /**
     * Fügt eine neue, leere Zutat ans Rezept an.
     */
    #[LiveAction]
    public function addIngredient(): void
    {
        // Aktuelle Eingaben binden, ohne Validierung auszulösen.
        $this->submitForm(false);

        /** @var Recipe $recipe */
        $recipe = $this->getForm()->getData();
        $recipe->addIngredient(new Ingredient());
    }

    /**
     * Entfernt die Zutat an gegebener Position.
     */
    #[LiveAction]
    public function removeIngredient(#[LiveArg] int $index): void
    {
        $this->submitForm(false);

        /** @var Recipe $recipe */
        $recipe = $this->getForm()->getData();
        $ingredients = $recipe->getIngredients()->toArray();

        if (isset($ingredients[$index])) {
            $recipe->removeIngredient($ingredients[$index]);
        }
    }

    /**
     * Fügt einen neuen, leeren Zubereitungsschritt an.
     */
    #[LiveAction]
    public function addStep(): void
    {
        $this->submitForm(false);

        /** @var Recipe $recipe */
        $recipe = $this->getForm()->getData();
        $recipe->addStep(new Step());
    }

    /**
     * Entfernt den Schritt an gegebener Position.
     */
    #[LiveAction]
    public function removeStep(#[LiveArg] int $index): void
    {
        $this->submitForm(false);

        /** @var Recipe $recipe */
        $recipe = $this->getForm()->getData();
        $steps = $recipe->getSteps()->toArray();

        if (isset($steps[$index])) {
            $recipe->removeStep($steps[$index]);
        }
    }

    /**
     * Speichert das Rezept (Neu oder Update) und leitet zur Detail-Seite weiter.
     */
    #[LiveAction]
    public function save(): Response
    {
        $this->submitForm();

        /** @var Recipe $recipe */
        $recipe = $this->getForm()->getData();
        $isNew = null === $recipe->getId();

        if ($isNew) {
            $this->denyAccessUnlessGranted('ROLE_USER');
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \LogicException('Kein eingeloggter Benutzer vorhanden.');
            }
            $recipe->setOwner($user);
            $recipe->setAuthor($user->getFullName());
        } else {
            $this->denyAccessUnlessGranted(RecipeVoter::EDIT, $recipe);
        }

        // Sortierung von Zutaten und Schritten anhand der Formular-Reihenfolge fixieren.
        foreach ($recipe->getIngredients() as $i => $ingredient) {
            $ingredient->setPosition($i);
        }
        foreach ($recipe->getSteps() as $i => $step) {
            $step->setNumber($i + 1);
        }

        if ($isNew) {
            $this->em->persist($recipe);
        }
        $this->em->flush();

        $this->addFlash(
            'success',
            $isNew ? 'Rezept wurde erfolgreich erstellt!' : 'Rezept wurde erfolgreich aktualisiert!',
        );

        return $this->redirectToRoute('recipe_show', ['id' => $recipe->getId()]);
    }
}
