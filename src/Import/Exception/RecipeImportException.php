<?php

declare(strict_types=1);

namespace App\Import\Exception;

/**
 * Basisklasse für alle Fehler beim Rezept-Import.
 *
 * Die Controller-Schicht fängt diese Exception und übersetzt sie in eine
 * Flash-Nachricht – der Nutzer soll nie einen 500er sehen, nur weil eine
 * externe Seite gerade nicht erreichbar ist.
 */
class RecipeImportException extends \RuntimeException
{
}
