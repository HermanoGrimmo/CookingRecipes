<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StepRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ein einzelner Zubereitungsschritt eines Rezepts.
 */
#[ORM\Entity(repositoryClass: StepRepository::class)]
#[ORM\Table(name: 'step')]
class Step
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Schrittnummer (1, 2, 3, …) */
    #[ORM\Column]
    private int $number = 1;

    /** Titel des Schritts (optional, z. B. „Ragù Bolognese") */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    /** Beschreibung / Anleitung */
    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\ManyToOne(targetEntity: Recipe::class, inversedBy: 'steps')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Recipe $recipe = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

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
