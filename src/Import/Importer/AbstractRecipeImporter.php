<?php

declare(strict_types=1);

namespace App\Import\Importer;

use App\Import\Exception\RecipeImportException;
use App\Import\RecipeImporterInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Gemeinsame Basis der quellenspezifischen Importer.
 *
 * Bündelt das Fehler-Handling um den HTTP-Client (alle Transport- und
 * Decoding-Fehler werden zu einer RecipeImportException) sowie die
 * Zahlen-Formatierung für Mengenangaben.
 */
abstract class AbstractRecipeImporter implements RecipeImporterInterface
{
    public function __construct(protected readonly HttpClientInterface $client)
    {
    }

    /**
     * Holt eine URL und dekodiert die Antwort als JSON-Objekt.
     *
     * @throws RecipeImportException
     *
     * @return array<string, mixed>
     */
    protected function fetchJson(string $url): array
    {
        try {
            $data = $this->client->request('GET', $url)->toArray();
        } catch (HttpExceptionInterface $e) {
            throw new RecipeImportException(\sprintf('Anfrage an "%s" fehlgeschlagen: %s', $url, $e->getMessage()), 0, $e);
        }

        return $data;
    }

    /**
     * Holt eine URL als HTML-Text (für den JSON-LD-Fallback).
     *
     * @throws RecipeImportException
     */
    protected function fetchHtml(string $url): string
    {
        try {
            return $this->client->request('GET', $url)->getContent();
        } catch (HttpExceptionInterface $e) {
            throw new RecipeImportException(\sprintf('Anfrage an "%s" fehlgeschlagen: %s', $url, $e->getMessage()), 0, $e);
        }
    }

    /**
     * Formatiert eine Menge für die Anzeige: 500.0 → "500", 0.5 → "0,5".
     *
     * Ingredient::$amount ist ein String – die Anwendung rechnet damit nur
     * beim Hochskalieren der Portionen (ServingsCalculator), und der erwartet
     * genau dieses Format.
     */
    protected function formatAmount(?float $amount): ?string
    {
        if (null === $amount || $amount <= 0.0) {
            return null;
        }

        if ($amount === (float) (int) $amount) {
            return (string) (int) $amount;
        }

        return rtrim(rtrim(number_format($amount, 2, ',', ''), '0'), ',');
    }

    /**
     * Liefert den getrimmten String oder NULL, wenn er leer ist.
     */
    protected function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    /**
     * Wandelt HTML-Fließtext aus einer API in reinen Text.
     *
     * Absatz- und Zeilenumbrüche bleiben als Zeilenumbrüche erhalten, damit
     * mehrteilige Tipps lesbar bleiben.
     */
    protected function htmlToText(?string $html): string
    {
        if (null === $html || '' === $html) {
            return '';
        }

        $text = preg_replace('#<(?:br\s*/?|/p|/li|/div)\s*>#i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8'));
    }
}
