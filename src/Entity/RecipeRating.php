<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RecipeRatingRepository;
use Doctrine\ORM\Mapping as ORM;

/** Einzelbewertung eines Rezepts; der Benutzer kann nach Kontolöschung fehlen. */
#[ORM\Entity(repositoryClass: RecipeRatingRepository::class)]
#[ORM\Table(name: 'recipe_rating')]
#[ORM\UniqueConstraint(name: 'UNIQ_recipe_rating_user', columns: ['recipe_id', 'user_id'])]
class RecipeRating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Recipe::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Recipe $recipe;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user;

    #[ORM\Column(type: 'smallint')]
    private int $score;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct(Recipe $recipe, User $user, int $score)
    {
        $this->recipe = $recipe;
        $this->user = $user;
        $this->setScore($score);
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipe(): Recipe
    {
        return $this->recipe;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setScore(int $score): static
    {
        if ($score < 1 || $score > 5) {
            throw new \InvalidArgumentException('Eine Bewertung muss zwischen 1 und 5 liegen.');
        }

        $this->score = $score;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
