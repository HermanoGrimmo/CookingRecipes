# CookingRecipes – Projektdokumentation für Claude

## Projektübersicht

Content-/CMS-Webseite für Kochrezepte, entwickelt mit **PHP + Symfony** und **Twig** als Template-Engine.

## Tech-Stack

- **Sprache:** PHP 8.4 (mit `declare(strict_types=1)` in jeder Datei)
- **Framework:** Symfony (aktuelle LTS-Version, Symfony Best Practices einhalten)
- **Templates:** Twig
- **Tests:** PHPUnit (Unit- & Integrationtests), optional Behat für funktionale Tests
- **Datenbank:** PostgreSQL

## Coding-Konventionen

- **Strict Types:** Jede PHP-Datei beginnt mit `declare(strict_types=1);`
- **Typisierung:** Alle Parameter, Rückgabetypen und Eigenschaften vollständig typisieren (inkl. `readonly`, Union Types etc.)
- **Kommentare & Dokumentation:** Auf **Deutsch** – PHPDoc-Blöcke, Inline-Kommentare und README-Inhalte werden auf Deutsch verfasst
- **Namenskonventionen:** Klassen/Interfaces nach PSR-4, Methoden/Variablen in camelCase, Konstanten in UPPER_SNAKE_CASE
- **Symfony Best Practices:**
  - Services über Dependency Injection (autowiring bevorzugt)
  - Controller sind schlank – Logik gehört in Services
  - Konfiguration in `config/` (YAML bevorzugt)
  - Keine direkte Nutzung des Containers im Code (`$this->get(...)` vermeiden)

## Testanforderungen

- Neue Features und Bug-Fixes bekommen PHPUnit-Tests
- Unit-Tests isolieren die zu testende Klasse (Mocking wo nötig)
- Integrationtests nutzen den Symfony-Kernel (`KernelTestCase` / `WebTestCase`)
- Tests liegen unter `tests/` und spiegeln die Struktur von `src/` wider

## Projektstruktur (Symfony-Standard)

```
src/
  Controller/       # Schlanke Controller
  Entity/           # Doctrine-Entitäten
  Repository/       # Doctrine-Repositories
  Service/          # Fachliche Logik
  Form/             # Symfony Form Types
  Twig/             # Twig-Extensions & -Komponenten
templates/          # Twig-Templates
tests/              # PHPUnit-Tests (Struktur spiegelt src/)
config/             # Symfony-Konfiguration
public/             # Web-Root (index.php)
```

## Hinweise für Claude

- Immer `declare(strict_types=1);` an den Anfang neuer PHP-Dateien setzen
- Kommentare, PHPDoc und Erklärungen auf Deutsch verfassen
- Bei neuen Features direkt passende Tests mitliefern
- Symfony-Konventionen und -Verzeichnisstruktur strikt einhalten
- Keine Logik in Controller-Methoden – Services verwenden
- Twig-Templates sauber und wiederverwendbar halten (Blöcke, Makros, Komponenten)
