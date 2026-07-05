<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Recipe;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Live Component für den Portionsrechner auf der Rezept-Detailseite.
 *
 * Skaliert die Mengenangaben der Zutaten reaktiv auf eine vom Benutzer
 * gewählte Portionszahl, ohne handgeschriebenes JavaScript.
 */
#[AsLiveComponent]
final class ServingsCalculator
{
    use DefaultActionTrait;

    /** Untergrenze für die Portionszahl */
    public const int MIN_SERVINGS = 1;

    /** Obergrenze für die Portionszahl (entspricht dem Maximum im Rezeptformular) */
    public const int MAX_SERVINGS = 100;

    #[LiveProp]
    public ?Recipe $recipe = null;

    /**
     * Aktuell gewählte Portionszahl (vom Benutzer veränderbar).
     * Da die Prop writable ist, wird sie nach jedem Client-Update geklemmt.
     */
    #[LiveProp(writable: true, onUpdated: 'clampCurrentServings')]
    public int $currentServings = 0;

    /**
     * Begrenzt die Portionszahl auf den gültigen Bereich – der Client könnte
     * über das Live-Model beliebige Werte (0, negativ, riesig) setzen.
     */
    public function clampCurrentServings(): void
    {
        $this->currentServings = max(self::MIN_SERVINGS, min(self::MAX_SERVINGS, $this->currentServings));
    }

    /**
     * Liefert den Skalierungsfaktor (currentServings / defaultServings).
     */
    public function getFactor(): float
    {
        $default = $this->recipe?->getServings() ?? 1;
        if ($default <= 0 || $this->currentServings <= 0) {
            return 1.0;
        }

        return $this->currentServings / $default;
    }

    /**
     * Skaliert eine Mengenangabe; gibt nicht-numerische Werte unverändert zurück.
     */
    public function scaleAmount(?string $amount): ?string
    {
        if (null === $amount || '' === $amount) {
            return $amount;
        }

        // Nur reine numerische Werte (auch mit Komma) skalieren.
        $normalized = str_replace(',', '.', $amount);
        if (!is_numeric($normalized)) {
            return $amount;
        }

        $scaled = round((float) $normalized * $this->getFactor(), 2);

        // Ganzzahlen ohne Dezimalstellen ausgeben.
        if (0.0 === fmod($scaled, 1.0)) {
            return (string) (int) $scaled;
        }

        return rtrim(rtrim(number_format($scaled, 2, '.', ''), '0'), '.');
    }

    #[LiveAction]
    public function increase(): void
    {
        $this->currentServings = min(self::MAX_SERVINGS, $this->currentServings + 1);
    }

    #[LiveAction]
    public function decrease(): void
    {
        $this->currentServings = max(self::MIN_SERVINGS, $this->currentServings - 1);
    }
}
