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

    /** @var Collection<int, Ingredient> */
    #[ORM\OneToMany(targetEntity: Ingredient::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $ingredients;

    /** @var Collection<int, Step> */
    #[ORM\OneToMany(targetEntity: Step::class, mappedBy: 'recipe', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $steps;

    public function __construct()
    {
        $this->ingredients = new ArrayCollection();
        $this->steps = new ArrayCollection();
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

    /** Gesamtzeit in Minuten */
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
}
