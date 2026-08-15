<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RecipeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Repräsentiert ein Kochrezept mit Zutaten und Zubereitungsschritten.
 */
#[ORM\Entity(repositoryClass: RecipeRepository::class)]
#[ORM\Table(name: 'recipe')]
#[ORM\UniqueConstraint(name: 'UNIQ_recipe_source_url', columns: ['source_url'])]
class Recipe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Titel des Rezepts */
    #[ORM\Column(length: 255)]
    private string $title = '';

    /** Kurzbeschreibung / Teaser */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Name des Autors */
    #[ORM\Column(length: 255)]
    private string $author = '';

    /** Pfad zum Hero-Bild */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $imagePath = null;

    /** Anzahl Portionen (Standard) */
    #[ORM\Column]
    private int $servings = 4;

    /** Zubereitungszeit in Minuten */
    #[ORM\Column]
    private int $prepTime = 0;

    /** Kochzeit / Wartezeit in Minuten */
    #[ORM\Column]
    private int $cookTime = 0;

    /**
     * Ruhezeit in Minuten (Teig gehen lassen, Einweichen, Marinieren …).
     *
     * Bewusst getrennt von prepTime/cookTime: Sie fließt nicht in
     * getTotalTime() ein, weil sie keine aktive Arbeitszeit ist und die
     * Sortierung nach Gesamtzeit sonst von Einweichzeiten dominiert würde.
     */
    #[ORM\Column]
    private int $restTime = 0;

    /** Schwierigkeitsgrad: einfach, mittel, schwer */
    #[ORM\Column(length: 50)]
    private string $difficulty = 'einfach';

    /** Durchschnittliche Bewertung (1–5) */
    #[ORM\Column(type: Types::DECIMAL, precision: 2, scale: 1)]
    private string $rating = '0.0';

    /** Anzahl abgegebener Bewertungen */
    #[ORM\Column]
    private int $ratingCount = 0;

    /** Erstellungsdatum */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * URL des Original-Rezepts, falls importiert (sonst NULL).
     *
     * Der Unique-Index verhindert, dass dieselbe Quelle zweimal importiert
     * wird. PostgreSQL lässt mehrere NULL-Werte in einem Unique-Index zu –
     * manuell angelegte Rezepte sind davon also nicht betroffen.
     */
    #[ORM\Column(length: 1024, nullable: true)]
    private ?string $sourceUrl = null;

    /** Anzeigename der Quelle, z. B. "Chefkoch" oder "FOODBOOM" */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $sourceName = null;

    /** Zeitpunkt des Imports (NULL bei manuell angelegten Rezepten) */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $importedAt = null;

    /** @var Collection<int, Ingredient> */
    #[ORM\OneToMany(targetEntity: Ingredient::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $ingredients;

    /** @var Collection<int, Step> */
    #[ORM\OneToMany(targetEntity: Step::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $steps;

    /**
     * Der Benutzer, der das Rezept erstellt hat.
     * Wird auf NULL gesetzt, wenn der Benutzer gelöscht wird (Rezept bleibt erhalten).
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'recipes')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $owner = null;

    /**
     * Schlagwörter des Rezepts.
     *
     * cascade: ['persist'] erlaubt es, beim Import neue Tags einfach an das
     * Rezept zu hängen – sie werden zusammen mit dem Rezept gespeichert.
     * Kein orphanRemoval: Tags werden von mehreren Rezepten geteilt.
     *
     * @var Collection<int, Tag>
     */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'recipes', cascade: ['persist'])]
    #[ORM\JoinTable(name: 'recipe_tag')]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $tags;

    public function __construct()
    {
        $this->ingredients = new ArrayCollection();
        $this->steps = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;

        return $this;
    }

    public function getServings(): int
    {
        return $this->servings;
    }

    public function setServings(int $servings): static
    {
        $this->servings = $servings;

        return $this;
    }

    public function getPrepTime(): int
    {
        return $this->prepTime;
    }

    public function setPrepTime(int $prepTime): static
    {
        $this->prepTime = $prepTime;

        return $this;
    }

    public function getCookTime(): int
    {
        return $this->cookTime;
    }

    public function setCookTime(int $cookTime): static
    {
        $this->cookTime = $cookTime;

        return $this;
    }

    public function getRestTime(): int
    {
        return $this->restTime;
    }

    public function setRestTime(int $restTime): static
    {
        $this->restTime = $restTime;

        return $this;
    }

    /**
     * Aktive Gesamtzeit in Minuten.
     *
     * Ruhezeit ist bewusst NICHT enthalten – siehe Kommentar an $restTime.
     * Die Sortierung in RecipeRepository::findFiltered() rechnet identisch.
     */
    public function getTotalTime(): int
    {
        return $this->prepTime + $this->cookTime;
    }

    public function getDifficulty(): string
    {
        return $this->difficulty;
    }

    public function setDifficulty(string $difficulty): static
    {
        $this->difficulty = $difficulty;

        return $this;
    }

    public function getRating(): string
    {
        return $this->rating;
    }

    public function setRating(string $rating): static
    {
        $this->rating = $rating;

        return $this;
    }

    public function getRatingCount(): int
    {
        return $this->ratingCount;
    }

    public function setRatingCount(int $ratingCount): static
    {
        $this->ratingCount = $ratingCount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Ingredient> */
    public function getIngredients(): Collection
    {
        return $this->ingredients;
    }

    public function addIngredient(Ingredient $ingredient): static
    {
        if (!$this->ingredients->contains($ingredient)) {
            $this->ingredients->add($ingredient);
            $ingredient->setRecipe($this);
        }

        return $this;
    }

    public function removeIngredient(Ingredient $ingredient): static
    {
        // orphanRemoval: true sorgt dafür, dass die Zutat beim nächsten flush gelöscht wird
        $this->ingredients->removeElement($ingredient);

        return $this;
    }

    /** @return Collection<int, Step> */
    public function getSteps(): Collection
    {
        return $this->steps;
    }

    public function addStep(Step $step): static
    {
        if (!$this->steps->contains($step)) {
            $this->steps->add($step);
            $step->setRecipe($this);
        }

        return $this;
    }

    public function removeStep(Step $step): static
    {
        // orphanRemoval: true sorgt dafür, dass der Schritt beim nächsten flush gelöscht wird
        $this->steps->removeElement($step);

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getSourceUrl(): ?string
    {
        return $this->sourceUrl;
    }

    public function setSourceUrl(?string $sourceUrl): static
    {
        $this->sourceUrl = $sourceUrl;

        return $this;
    }

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function setSourceName(?string $sourceName): static
    {
        $this->sourceName = $sourceName;

        return $this;
    }

    public function getImportedAt(): ?\DateTimeImmutable
    {
        return $this->importedAt;
    }

    public function setImportedAt(?\DateTimeImmutable $importedAt): static
    {
        $this->importedAt = $importedAt;

        return $this;
    }

    /** Ob das Rezept aus einer externen Quelle importiert wurde. */
    public function isImported(): bool
    {
        return null !== $this->sourceUrl;
    }

    /** @return Collection<int, Tag> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): static
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): static
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
