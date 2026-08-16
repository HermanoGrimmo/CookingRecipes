<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Recipe;
use App\Entity\User;
use App\Form\RecipeType;
use App\Security\RecipeVoter;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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

    /**
     * Herkunftsdaten eines importierten Rezepts.
     *
     * Diese Felder sind bewusst KEINE Formularfelder: Sie sollen vom Nutzer
     * nicht bearbeitet werden und würden über den Live-Component-Roundtrip
     * verloren gehen, weil sie nicht in den formValues stecken. Als nicht
     * schreibbare LiveProps sind sie Checksummen-signiert und damit auch
     * nicht manipulierbar.
     */
    #[LiveProp(writable: false)]
    public ?string $sourceUrl = null;

    #[LiveProp(writable: false)]
    public ?string $sourceName = null;

    /** Original-Autor der Quelle (überschreibt den Namen des Benutzers). */
    #[LiveProp(writable: false)]
    public ?string $importedAuthor = null;

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
     * Fügt eine neue, leere Zutaten-Zeile an.
     *
     * Die Zeilen werden direkt über die formValues verwaltet (Muster aus der
     * Symfony-UX-Dokumentation): Die aktuellen Eingaben sind dort bereits
     * synchronisiert, das Formular wird beim Re-Render daraus neu aufgebaut.
     */
    #[LiveAction]
    public function addIngredient(): void
    {
        $this->formValues['ingredients'][] = [];
    }

    /**
     * Entfernt die Zutaten-Zeile mit dem gegebenen Formular-Key.
     *
     * Wichtig: $index ist der Key des Formular-Kinds (im Template per
     * `{% for key, ... %}` übergeben), NICHT die Position in der Liste –
     * nach einem Entfernen sind die Keys lückenhaft.
     */
    #[LiveAction]
    public function removeIngredient(#[LiveArg] int $index): void
    {
        unset($this->formValues['ingredients'][$index]);
    }

    /**
     * Fügt eine neue, leere Schritt-Zeile an.
     */
    #[LiveAction]
    public function addStep(): void
    {
        $this->formValues['steps'][] = [];
    }

    /**
     * Entfernt die Schritt-Zeile mit dem gegebenen Formular-Key.
     */
    #[LiveAction]
    public function removeStep(#[LiveArg] int $index): void
    {
        unset($this->formValues['steps'][$index]);
    }

    /**
     * Speichert das Rezept (Neu oder Update) und leitet zur Detail-Seite weiter.
     */
    #[LiveAction]
    public function save(): Response
    {
        // Autorisierung VOR der Formularverarbeitung prüfen. initialFormData ist
        // eine nicht-schreibbare LiveProp und damit Checksummen-geschützt.
        $isNew = null === $this->initialFormData?->getId();

        if ($isNew) {
            $this->denyAccessUnlessGranted('ROLE_USER');
        } else {
            $this->denyAccessUnlessGranted(RecipeVoter::EDIT, $this->initialFormData);
        }

        $this->submitForm();

        /** @var Recipe $recipe */
        $recipe = $this->getForm()->getData();

        if ($isNew) {
            $user = $this->getUser();
            if (!$user instanceof User) {
                throw new \LogicException('Kein eingeloggter Benutzer vorhanden.');
            }
            $recipe->setOwner($user);

            // Twig übergibt nicht gesetzte Props als Leerstring – leer zählt
            // hier wie "nicht importiert".
            $importedAuthor = $this->nullIfBlank($this->importedAuthor);
            $sourceUrl = $this->nullIfBlank($this->sourceUrl);

            // Importierte Rezepte behalten den Autor der Quelle; manuell
            // angelegte bekommen wie bisher den Namen des Benutzers.
            $recipe->setAuthor($importedAuthor ?? $user->getFullName());

            if (null !== $sourceUrl) {
                $recipe->setSourceUrl($sourceUrl);
                $recipe->setSourceName($this->nullIfBlank($this->sourceName));
                $recipe->setImportedAt(new \DateTimeImmutable());
            }
        }

        // Sortierung von Zutaten und Schritten anhand der Formular-Reihenfolge fixieren.
        // Zähler statt Collection-Keys verwenden – die Keys können nach dem
        // Entfernen von Zeilen Lücken haben.
        $position = 0;
        foreach ($recipe->getIngredients() as $ingredient) {
            $ingredient->setPosition($position++);
        }
        $number = 1;
        foreach ($recipe->getSteps() as $step) {
            $step->setNumber($number++);
        }

        if ($isNew) {
            $this->em->persist($recipe);
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Zwei Unique-Indizes können hier zuschlagen: recipe.source_url
            // (dieselbe Quelle wurde zwischenzeitlich anderswo importiert)
            // oder tag.name (ein neuer Tag wurde parallel von einem anderen
            // Request angelegt – TagResolver dedupliziert nur pro Request).
            // Postgres faltet unquotierte Bezeichner auf Kleinschreibung, der
            // Indexname steht daher als "uniq_recipe_source_url" bzw.
            // "uniq_tag_name" in der Fehlermeldung.
            $message = str_contains(strtolower($e->getMessage()), 'source_url')
                ? 'Dieses Rezept wurde inzwischen bereits aus derselben Quelle gespeichert.'
                : 'Das Rezept konnte nicht gespeichert werden (ein verwendeter Tag wurde zeitgleich angelegt). Bitte versuche es erneut.';

            $this->addFlash('error', $message);

            return $this->redirectToRoute('recipe_index');
        }

        $this->addFlash(
            'success',
            $isNew ? 'Rezept wurde erfolgreich erstellt!' : 'Rezept wurde erfolgreich aktualisiert!',
        );

        return $this->redirectToRoute('recipe_show', ['id' => $recipe->getId()]);
    }

    /** Liefert den getrimmten Wert oder NULL, wenn er leer ist. */
    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }
}
