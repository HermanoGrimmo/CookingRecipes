<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Recipe;
use App\Entity\User;
use App\Service\RecipeRatingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/** Wiederverwendbare interaktive Bewertung für Karte und Detailseite. */
#[AsLiveComponent]
final class RecipeRating extends AbstractController
{
    use DefaultActionTrait;

    #[LiveProp(writable: false)]
    public Recipe $recipe;

    #[LiveProp(writable: false)]
    public ?int $personalScore = null;

    public function __construct(private readonly RecipeRatingService $ratingService)
    {
    }

    #[LiveAction]
    public function rate(#[LiveArg] int $score): void
    {
        $user = $this->authenticatedUser();
        $this->ratingService->rate($this->recipe, $user, $score);
        $this->personalScore = $score;
    }

    #[LiveAction]
    public function remove(): void
    {
        $user = $this->authenticatedUser();
        $this->ratingService->remove($this->recipe, $user);
        $this->personalScore = null;
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Nur angemeldete Benutzer dürfen Rezepte bewerten.');
        }

        return $user;
    }
}
