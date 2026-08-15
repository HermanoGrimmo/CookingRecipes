<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Tag;
use App\Service\TagResolver;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Übersetzt zwischen der Tag-Collection eines Rezepts und einem
 * kommaseparierten Textfeld ("Pasta, Vegetarisch, Hauptspeise").
 *
 * Bewusst ein einfaches Textfeld statt einer Auswahlliste: Tags entstehen
 * überwiegend beim Import, und ein Textfeld kommt ohne zusätzliches
 * JavaScript und ohne Autocomplete-Bundle aus.
 *
 * @implements DataTransformerInterface<Collection<int, Tag>, string>
 */
final class TagsToStringTransformer implements DataTransformerInterface
{
    /** Muss zu Tag::$name (VARCHAR(100)) passen. */
    private const int MAX_NAME_LENGTH = 100;

    public function __construct(private readonly TagResolver $tagResolver)
    {
    }

    /**
     * Collection<Tag> → "Pasta, Vegetarisch".
     */
    public function transform(mixed $value): string
    {
        if (!$value instanceof Collection) {
            return '';
        }

        $names = [];
        foreach ($value as $tag) {
            $names[] = $tag->getName();
        }

        return implode(', ', $names);
    }

    /**
     * "Pasta, Vegetarisch" → Collection<Tag>.
     *
     * @return Collection<int, Tag>
     */
    public function reverseTransform(mixed $value): Collection
    {
        if (!\is_string($value) || '' === trim($value)) {
            return new ArrayCollection();
        }

        $names = explode(',', $value);

        // TransformationFailedException wird von Symfony automatisch in einen
        // Formularfehler am tags-Feld umgewandelt – anders als eine normale
        // Exception, die als 500 durchschlagen würde. TagResolver selbst kürzt
        // zu lange Namen defensiv (z. B. beim Import), hier soll der Nutzer
        // aber eine klare Fehlermeldung statt stiller Kürzung sehen.
        foreach ($names as $name) {
            if (mb_strlen(trim($name)) > self::MAX_NAME_LENGTH) {
                throw new TransformationFailedException(\sprintf('Der Tag "%s…" ist zu lang (maximal %d Zeichen).', mb_substr(trim($name), 0, 20), self::MAX_NAME_LENGTH));
            }
        }

        return new ArrayCollection($this->tagResolver->resolveAll($names));
    }
}
