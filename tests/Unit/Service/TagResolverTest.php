<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Service\TagResolver;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für die Tag-Auflösung.
 */
class TagResolverTest extends TestCase
{
    public function testResolveAllLiefertLeereListeBeiLeererEingabe(): void
    {
        $resolver = $this->createResolver();

        self::assertSame([], $resolver->resolveAll([]));
        self::assertSame([], $resolver->resolveAll(['', '  ']));
    }

    /**
     * Alle unbekannten Namen werden in einer einzigen Abfrage nachgeschlagen
     * statt einer pro Name.
     */
    public function testResolveAllFragtAlleNamenInEinerAbfrageAb(): void
    {
        $tagRepository = $this->createMock(TagRepository::class);
        $tagRepository->expects(self::once())
            ->method('findByNames')
            ->with(['Pasta', 'Vegetarisch', 'Hauptspeise'])
            ->willReturn([]);

        $resolver = new TagResolver($tagRepository);
        $resolver->resolveAll(['Pasta', 'Vegetarisch', 'Hauptspeise']);
    }

    public function testResolveAllNutztVorhandeneTags(): void
    {
        $vorhanden = new Tag('Pasta');

        $tagRepository = $this->createMock(TagRepository::class);
        $tagRepository->method('findByNames')->willReturn([$vorhanden]);

        $resolver = new TagResolver($tagRepository);
        $tags = $resolver->resolveAll(['Pasta', 'Neu']);

        self::assertSame($vorhanden, $tags[0]);
        self::assertNotSame($vorhanden, $tags[1]);
        self::assertSame('Neu', $tags[1]->getName());
    }

    public function testResolveAllDedupliziertUnabhaengigVonGrossschreibung(): void
    {
        $resolver = $this->createResolver();

        $tags = $resolver->resolveAll(['Pasta', 'pasta', 'PASTA']);

        self::assertCount(1, $tags);
    }

    /**
     * Ein zu langer Name wird gekappt statt beim Flush einen DB-Fehler
     * auszulösen (VARCHAR(100)). Anders als im Formular (siehe
     * TagsToStringTransformerTest) darf der Import nicht mitten im Speichern
     * scraper Daten abbrechen.
     */
    public function testResolveAllKapptZuLangeNamen(): void
    {
        $resolver = $this->createResolver();

        $tags = $resolver->resolveAll([str_repeat('a', 150)]);

        self::assertSame(100, mb_strlen($tags[0]->getName()));
    }

    /**
     * Zwei Aufrufe mit demselben neuen Namen dürfen innerhalb eines Requests
     * kein zweites Tag-Objekt erzeugen – sonst läuft der Unique-Index beim
     * Flush in einen Fehler.
     */
    public function testResolveAllGibtDasselbeObjektFuerWiederholteNeueNamenZurueck(): void
    {
        $resolver = $this->createResolver();

        $erste = $resolver->resolveAll(['Neu']);
        $zweite = $resolver->resolveAll(['neu']);

        self::assertSame($erste[0], $zweite[0]);
    }

    private function createResolver(): TagResolver
    {
        $tagRepository = $this->createMock(TagRepository::class);
        $tagRepository->method('findByNames')->willReturn([]);

        return new TagResolver($tagRepository);
    }
}
