<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Recipe;
use App\Entity\User;
use App\Service\RecipeRatingService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/** Wiederverwendbare interaktive Bewertung für Karte und Detailseite. */
#[AsLiveComponent]
final class RecipeRating
{
    use DefaultActionTrait;

    #[LiveProp(writable: false)]
    public Recipe $recipe;

    #[LiveProp(writable: false)]
    public ?int $personalScore = null;

    public function __construct(
        private readonly RecipeRatingService $ratingService,
        private readonly Security $security,
    ) {
    }

    public function getCanRate(): bool
    {
        return $this->security->getUser() instanceof User;
    }

    #[LiveAction]
    public function rate(#[LiveArg] mixed $score): void
    {
        if (!\is_int($score) || $score < 1 || $score > 5) {
            throw new BadRequestHttpException('Eine Bewertung muss eine ganze Zahl zwischen 1 und 5 sein.');
        }

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
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Nur angemeldete Benutzer dürfen Rezepte bewerten.');
        }

        return $user;
    }
}
