<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Schlagwort eines Rezepts (z. B. "Vegetarisch", "Hauptspeise", "Pasta").
 *
 * Tags werden beim Import aus den Quell-APIs übernommen und über
 * TagRepository::findOrCreate() dedupliziert.
 */
#[ORM\Entity(repositoryClass: TagRepository::class)]
#[ORM\Table(name: 'tag')]
#[ORM\UniqueConstraint(name: 'UNIQ_tag_name', columns: ['name'])]
class Tag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Anzeigename des Tags */
    #[ORM\Column(length: 100)]
    private string $name = '';

    /** @var Collection<int, Recipe> */
    #[ORM\ManyToMany(targetEntity: Recipe::class, mappedBy: 'tags')]
    private Collection $recipes;

    public function __construct(string $name = '')
    {
        $this->name = $name;
        $this->recipes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /** @return Collection<int, Recipe> */
    public function getRecipes(): Collection
    {
        return $this->recipes;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
