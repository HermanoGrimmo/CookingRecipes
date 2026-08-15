<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\DataTransformer;

use App\Entity\Tag;
use App\Form\DataTransformer\TagsToStringTransformer;
use App\Repository\TagRepository;
use App\Service\TagResolver;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Unit-Tests für die Umwandlung zwischen Tag-Collection und Textfeld.
 */
class TagsToStringTransformerTest extends TestCase
{
    public function testTransformVerbindetDieNamenMitKomma(): void
    {
        $transformer = $this->createTransformer();

        $tags = new ArrayCollection([new Tag('Pasta'), new Tag('Vegetarisch')]);

        self::assertSame('Pasta, Vegetarisch', $transformer->transform($tags));
    }

    public function testTransformLiefertLeerstringOhneTags(): void
    {
        $transformer = $this->createTransformer();

        self::assertSame('', $transformer->transform(new ArrayCollection()));
        self::assertSame('', $transformer->transform(null));
    }

    public function testReverseTransformZerlegtUndTrimmt(): void
    {
        $transformer = $this->createTransformer();

        $tags = $transformer->reverseTransform('  Pasta ,Vegetarisch  ')->toArray();

        self::assertCount(2, $tags);
        self::assertSame('Pasta', $tags[0]->getName());
        self::assertSame('Vegetarisch', $tags[1]->getName());
    }

    public function testReverseTransformVerwirftLeereEintraege(): void
    {
        $transformer = $this->createTransformer();

        $tags = $transformer->reverseTransform('Pasta,,  , Vegetarisch,');

        self::assertCount(2, $tags);
    }

    /**
     * Groß-/Kleinschreibung darf keine zwei Tags erzeugen – sonst läuft der
     * Unique-Index auf tag.name beim Speichern in einen Fehler.
     */
    public function testReverseTransformDedupliziertUnabhaengigVonGrossschreibung(): void
    {
        $transformer = $this->createTransformer();

        $tags = $transformer->reverseTransform('Pasta, pasta, PASTA')->toArray();

        self::assertCount(1, $tags);
        self::assertSame('Pasta', $tags[0]->getName());
    }

    public function testReverseTransformLiefertLeereCollectionBeiLeererEingabe(): void
    {
        $transformer = $this->createTransformer();

        self::assertCount(0, $transformer->reverseTransform(''));
        self::assertCount(0, $transformer->reverseTransform('   '));
        self::assertCount(0, $transformer->reverseTransform(null));
    }

    /**
     * Bestehende Tags müssen wiederverwendet und dürfen nicht neu angelegt
     * werden.
     */
    public function testReverseTransformNutztVorhandeneTagsAusDerDatenbank(): void
    {
        $vorhanden = new Tag('Pasta');

        $tagRepository = $this->createMock(TagRepository::class);
        $tagRepository->method('findByNames')->willReturn([$vorhanden]);

        $transformer = new TagsToStringTransformer(new TagResolver($tagRepository));

        $tags = $transformer->reverseTransform('Pasta, Neu')->toArray();

        self::assertSame($vorhanden, $tags[0]);
        self::assertNotSame($vorhanden, $tags[1]);
        self::assertSame('Neu', $tags[1]->getName());
    }

    /** Hin- und Rückweg müssen denselben Text ergeben. */
    public function testRoundTrip(): void
    {
        $transformer = $this->createTransformer();

        $text = 'Pasta, Vegetarisch, Hauptspeise';

        self::assertSame($text, $transformer->transform($transformer->reverseTransform($text)));
    }

    /** Ein zu langer Tag-Name wird als Formularfehler gemeldet statt still gekürzt oder als 500 durchzuschlagen. */
    public function testReverseTransformWirftBeiZuLangemTagnamen(): void
    {
        $transformer = $this->createTransformer();

        $this->expectException(TransformationFailedException::class);
        $transformer->reverseTransform(str_repeat('a', 101));
    }

    private function createTransformer(): TagsToStringTransformer
    {
        $tagRepository = $this->createMock(TagRepository::class);
        $tagRepository->method('findByNames')->willReturn([]);

        return new TagsToStringTransformer(new TagResolver($tagRepository));
    }
}
