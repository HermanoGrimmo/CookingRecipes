<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Components;

use App\Entity\Recipe;
use App\Twig\Components\ServingsCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für den Portionsrechner (Skalierungsfaktor, Mengen-Skalierung, Wertegrenzen).
 */
class ServingsCalculatorTest extends TestCase
{
    /** Erstellt einen Rechner mit Rezept (Standard-Portionen) und aktueller Portionszahl. */
    private function createCalculator(int $defaultServings, int $currentServings): ServingsCalculator
    {
        $recipe = new Recipe();
        $recipe->setServings($defaultServings);

        $calculator = new ServingsCalculator();
        $calculator->recipe = $recipe;
        $calculator->currentServings = $currentServings;

        return $calculator;
    }

    /** Der Faktor ist das Verhältnis von gewählter zu Standard-Portionszahl. */
    public function testFactorIsRatioOfCurrentToDefaultServings(): void
    {
        $calculator = $this->createCalculator(4, 8);

        self::assertSame(2.0, $calculator->getFactor());
    }

    /** Bei ungültiger (nicht positiver) Portionszahl wird neutral mit Faktor 1 skaliert. */
    public function testFactorIsOneWhenCurrentServingsIsNotPositive(): void
    {
        $calculator = $this->createCalculator(4, 0);

        self::assertSame(1.0, $calculator->getFactor());
    }

    /** Numerische Mengen werden mit dem Faktor multipliziert. */
    public function testScaleAmountMultipliesNumericValues(): void
    {
        $calculator = $this->createCalculator(2, 4);

        self::assertSame('400', $calculator->scaleAmount('200'));
    }

    /** Auch Dezimalzahlen mit Komma werden erkannt und skaliert. */
    public function testScaleAmountHandlesCommaDecimals(): void
    {
        $calculator = $this->createCalculator(2, 4);

        self::assertSame('5', $calculator->scaleAmount('2,5'));
    }

    /** Ergebnisse werden auf zwei Nachkommastellen gerundet, ohne überflüssige Nullen. */
    public function testScaleAmountRoundsToTwoDecimals(): void
    {
        $calculator = $this->createCalculator(3, 4);

        self::assertSame('1.33', $calculator->scaleAmount('1'));
    }

    /** Überflüssige Nachkomma-Nullen werden entfernt (2.50 → 2.5). */
    public function testScaleAmountTrimsTrailingZeros(): void
    {
        $calculator = $this->createCalculator(2, 5);

        self::assertSame('2.5', $calculator->scaleAmount('1'));
    }

    /** Nicht-numerische Mengenangaben bleiben unverändert. */
    public function testScaleAmountLeavesNonNumericValuesUntouched(): void
    {
        $calculator = $this->createCalculator(2, 4);

        self::assertSame('etwas', $calculator->scaleAmount('etwas'));
        self::assertSame('nach Geschmack', $calculator->scaleAmount('nach Geschmack'));
    }

    /** Leere Werte werden unverändert durchgereicht. */
    public function testScaleAmountPassesThroughEmptyValues(): void
    {
        $calculator = $this->createCalculator(2, 4);

        self::assertNull($calculator->scaleAmount(null));
        self::assertSame('', $calculator->scaleAmount(''));
    }

    /** increase() erhöht die Portionszahl, überschreitet aber nie das Maximum. */
    public function testIncreaseRespectsUpperBound(): void
    {
        $calculator = $this->createCalculator(4, ServingsCalculator::MAX_SERVINGS);

        $calculator->increase();

        self::assertSame(ServingsCalculator::MAX_SERVINGS, $calculator->currentServings);
    }

    /** decrease() verringert die Portionszahl, unterschreitet aber nie das Minimum. */
    public function testDecreaseRespectsLowerBound(): void
    {
        $calculator = $this->createCalculator(4, ServingsCalculator::MIN_SERVINGS);

        $calculator->decrease();

        self::assertSame(ServingsCalculator::MIN_SERVINGS, $calculator->currentServings);
    }

    /**
     * Der Clamp-Hook begrenzt Client-Updates auf den gültigen Bereich –
     * über das Live-Model könnten sonst beliebige Werte gesetzt werden.
     */
    public function testClampCurrentServingsLimitsClientProvidedValues(): void
    {
        $calculator = $this->createCalculator(4, -5);
        $calculator->clampCurrentServings();
        self::assertSame(ServingsCalculator::MIN_SERVINGS, $calculator->currentServings);

        $calculator->currentServings = 100000;
        $calculator->clampCurrentServings();
        self::assertSame(ServingsCalculator::MAX_SERVINGS, $calculator->currentServings);

        $calculator->currentServings = 6;
        $calculator->clampCurrentServings();
        self::assertSame(6, $calculator->currentServings);
    }
}
