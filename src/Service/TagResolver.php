<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Tag;
use App\Repository\TagRepository;

/**
 * Löst Tag-Namen zu Tag-Entitäten auf und legt fehlende an.
 *
 * Neue Tags werden bewusst nicht persistiert – das übernimmt
 * cascade: ['persist'] an Recipe::$tags beim Flush.
 */
final class TagResolver
{
    /** Tag::$name ist VARCHAR(100) – längere Namen würden den Flush mit einem DB-Fehler abbrechen. */
    private const int MAX_NAME_LENGTH = 100;

    /**
     * Innerhalb eines Requests neu erzeugte, noch nicht gespeicherte Tags.
     *
     * Ohne diesen Puffer würden zwei Aufrufe mit demselben neuen Tag-Namen
     * zwei Tag-Objekte erzeugen und beim Flush gegen den Unique-Index laufen.
     *
     * @var array<string, Tag>
     */
    private array $created = [];

    public function __construct(private readonly TagRepository $tagRepository)
    {
    }

    /**
     * Löst eine Liste von Namen auf und verwirft dabei leere Einträge und
     * Duplikate (case-insensitiv).
     *
     * Holt alle noch unbekannten Namen in einer einzigen Abfrage statt einer
     * pro Tag – bei den 5-10 Tags eines typischen Imports sonst 5-10
     * sequenzielle Round-Trips statt einem.
     *
     * @param list<string> $names
     *
     * @return list<Tag>
     */
    public function resolveAll(array $names): array
    {
        $uniqueNames = [];
        foreach ($names as $name) {
            $name = trim($name);
            if ('' === $name) {
                continue;
            }

            $key = mb_strtolower($name);
            $uniqueNames[$key] ??= $name;
        }

        if ([] === $uniqueNames) {
            return [];
        }

        $existingByKey = [];
        foreach ($this->tagRepository->findByNames(array_values($uniqueNames)) as $tag) {
            $existingByKey[mb_strtolower($tag->getName())] = $tag;
        }

        $tags = [];
        foreach ($uniqueNames as $key => $name) {
            $tags[] = $existingByKey[$key] ?? $this->created[$key] ?? ($this->created[$key] = new Tag(mb_substr($name, 0, self::MAX_NAME_LENGTH)));
        }

        return $tags;
    }
}
