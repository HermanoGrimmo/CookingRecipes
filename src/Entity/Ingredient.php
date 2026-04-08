<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IngredientRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Eine einzelne Zutat eines Rezepts, optional einer Gruppe zugeordnet.
 */
#[ORM\Entity(repositoryClass: IngredientRepository::class)]
#[ORM\Table(name: 'ingredient')]
class Ingredient
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Menge (z. B. 200, 0.5) – bezogen auf die Standard-Portionszahl des Rezepts */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $amount = null;

    /** Einheit (g, ml, EL, TL, Stück, …) */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $unit = null;

    /** Name der Zutat */
    #[ORM\Column(length: 255)]
    private string $name = '';

    /** Optionale Gruppe (z. B. „Für die Bolognese", „Für die Béchamelsauce") */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $groupName = null;

    /** Sortierung innerhalb des Rezepts */
    #[ORM\Column]
    private int $position = 0;

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'ingredients')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Recipe $recipe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): static
    {
        $this->amount = $amount;
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getGroupName(): ?string
    {
        return $this->groupName;
    }

    public function setGroupName(?string $groupName): static
    {
        $this->groupName = $groupName;
        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;
        return $this;
    }

    public function getRecipe(): ?Recipe
    {
        return $this->recipe;
    }

    public function setRecipe(?Recipe $recipe): static
    {
        $this->recipe = $recipe;
        return $this;
    }
}
