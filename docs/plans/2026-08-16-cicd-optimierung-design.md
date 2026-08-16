# CI/CD-Optimierung – Design

**Datum:** 2026-08-16
**Status:** Entwurf abgestimmt, Implementierung ausstehend
**Kontext:** Recherche vergleichbarer Symfony-Projekte + Analyse des Ist-Zustands

---

## 1. Ausgangslage

Das Projekt hat drei GitHub-Actions-Workflows (`tests.yml`, `static-analysis.yml`, `code-style.yml`) mit jeweils genau einem Job und identischem Aufbau. Die Laufzeiten liegen bei 22–50 s pro Job. **Geschwindigkeit ist nicht das Problem – Abdeckung ist es.**

### Festgestellte Lücken

| Bereich | Ist-Zustand |
|---|---|
| Migrationen | `tests.yml` nutzt `doctrine:schema:create`; die fünf Migrationen unter `migrations/` laufen nie in CI |
| Frontend | `webpack.config.js` / `assets/` werden in CI nie gebaut |
| Twig | Kein `lint:twig`, obwohl das Projekt Twig-lastig ist |
| Docker | `Dockerfile` mit `production`-Target wird in CI nie gebaut |
| CD | Nicht vorhanden |
| Composer-Cache | Fehlt – drei vollständige Installs pro Commit |
| `concurrency` | Fehlt – überholte Runs laufen weiter |
| `permissions` | Nicht gesetzt |
| Dependency-Updates | Kein Dependabot/Renovate, kein `composer audit` |

### Zwei konkrete Fehler

1. **Doppelte Runs:** `on: push` und `on: pull_request` stehen beide ohne Branch-Filter. Jeder Commit in einem PR erzeugt dadurch sechs Runs statt drei (verifiziert über `gh run list`).
2. **Postgres-Versions-Drift:** CI fährt `postgres:17`, `docker-compose.yml` und `compose.prod.yaml` fahren `postgres:16`. Es wird gegen eine andere Datenbankversion getestet als entwickelt und deployt wird.

---

## 2. Rechercheergebnisse

Vier vergleichbare Projekte wurden ausgewertet:

**[symfony/demo](https://github.com/symfony/demo)** – Referenz-Scope (Symfony + Twig App). Nur zwei Workflows. Übernommen wird die Kette der Symfony-eigenen Linter (`lint:yaml`, `lint:twig --env=prod`, `lint:container`, `doctrine:schema:validate`, `composer audit`), jeweils mit `if: always()`, sowie PHP-CS-Fixer als Docker-Action ohne `composer install`.

**[kimai/kimai](https://github.com/kimai/kimai)** – dem Gesamt-Setup am nächsten (Symfony + Docker + Assets, self-hosted). Reifste Umsetzung der vier. Übernommen werden: `concurrency` mit `cancel-in-progress`, `permissions: {}` als Default, `persist-credentials: false`, Composer-Cache über `composer config cache-dir`, Migrations-Test in beide Richtungen, sowie der Grundsatz, den Docker-Build **nicht** bei jedem Push laufen zu lassen.

**[Part-DB/Part-DB-server](https://github.com/Part-DB/Part-DB-server)** – der ähnlichste Deployment-Fall (self-hosted Symfony-App, die eigene Images ausliefert). Vorbild für den Docker-Teil: `cache-from/to: type=gha` als Layer-Cache, native ARM-Runner statt QEMU, `docker/metadata-action` für die Tag-Erzeugung, bei PRs bauen ohne pushen.

**[bolt/core](https://github.com/bolt/core)** – CMS-Scope, acht stark granulare Workflows. Dient als Gegenbeispiel: die Granularität erzeugt massive Duplikation von Setup-Steps.

### Wesentliche Abgrenzung

Kimai, Part-DB und Bolt liefern Software an Dritte aus und testen deshalb über PHP- und Datenbank-Matrizen. CookingRecipes betreibt **eine** Installation mit fixiertem PHP 8.4 und PostgreSQL. Eine Matrix wäre hier reine Verschwendung von Runner-Minuten. Die Investition gehört stattdessen in **Deployment-Nähe**: exakt die PHP- und Postgres-Version testen, die auch produktiv läuft.

---

## 3. Getroffene Entscheidungen

| # | Entscheidung | Begründung |
|---|---|---|
| E1 | CI härten **und** CD aufbauen | Der Branch `feature/production-deployment` liegt sonst ungenutzt |
| E2 | Zielumgebung: eigener Server mit SSH-Zugang | Kein PaaS, keine Webhook-Deployments |
| E3 | Zielarchitektur **`linux/arm64`** (Hetzner CAX) | Server existiert noch nicht; Festlegung erfolgt jetzt |
| E4 | Deploy-Auslöser: **`workflow_dispatch`** (manuell) | Build und Release entkoppeln; Kontrolle über den Zeitpunkt |
| E5 | CD-Ansatz: **GHCR + SSH-Pull** | Getestetes Artefakt = deploytes Artefakt; kein Build-Last auf dem VPS; Rollback in Sekunden |
| E6 | Drei Workflows → ein `ci.yml` mit parallelen Jobs | Gemeinsame Trigger, Cache-Strategie und `concurrency`-Gruppe |
| E7 | Keine PHP-/DB-Matrix | Eine Installation, fixierte Versionen (siehe Abgrenzung oben) |

### Verworfene Alternativen

- **Build auf dem Server** (`up -d --build`, wie in `compose.prod.yaml` heute vorgesehen): `npm ci` + Webpack auf einem kleinen VPS ist ein echtes OOM-Risiko, das deployte Artefakt entspricht nicht dem getesteten, und Quellcode samt `.git` läge auf der Produktionsmaschine.
- **Pull-Agent (Watchtower):** vermeidet SSH-Keys in GitHub-Secrets, liefert aber keinen deterministischen Deploy-Zeitpunkt (Widerspruch zu E4) und macht die Migrations-Reihenfolge fummelig.
- **`workflow_run` zur Verkettung von CI und Build:** unübersichtlicher als ein `needs`-Job im selben Workflow.

---

## 4. Zielarchitektur

```
Pull Request                    Push auf main                 Manuell
     │                                │                          │
     ▼                                ▼                          ▼
  ci.yml                           ci.yml                    deploy.yml
  ├─ code-style                    ├─ (fünf Jobs)            ├─ scp compose.prod.yaml
  ├─ static-analysis               │                         ├─ ssh: login + pull
  ├─ lint                          ▼                         ├─ ssh: up -d
  ├─ frontend                   build-images                 ├─ Health-Check
  ├─ tests                      └─ push → GHCR               └─ image prune
  └─ build-images (ohne push)      :main, :sha-<short>
```

### 4.1 `ci.yml` – Rahmen

```yaml
on:
  pull_request:
  push:
    branches: [main]

concurrency:
  group: ${{ github.workflow }}-${{ github.event.pull_request.number || github.ref }}
  cancel-in-progress: true

permissions:
  contents: read
```

Der Branch-Filter auf `push` behebt die verdoppelten Runs (Fehler 1 aus Abschnitt 1).

### 4.2 `ci.yml` – Jobs

| Job | Inhalt | Status |
|---|---|---|
| `code-style` | PHP-CS-Fixer als Docker-Action (`ghcr.io/php-cs-fixer/php-cs-fixer:3-php8.4`), benötigt **kein** `composer install` | umgebaut |
| `static-analysis` | PHPStan Level 8, `--error-format=github`, mit Composer-Cache | erweitert |
| `lint` | `composer validate --strict`, `lint:yaml config`, `lint:twig templates --env=prod`, `lint:container`, `doctrine:schema:validate --skip-sync`, `composer audit` – jeweils mit `if: always()` | **neu** |
| `frontend` | Node 22, `npm ci` (mit `cache: npm`), `npm run build`, `npm audit` | **neu** |
| `tests` | PHPUnit gegen Postgres in Prod-Version, Schema über **Migrationen** | umgebaut |
| `build-images` | Docker-Build, siehe 4.3 | **neu** |

**Wichtig für `static-analysis`:** `phpstan.dist.neon` verweist auf `var/cache/dev/App_KernelDevDebugContainer.xml`. Dieser wird in CI nur deshalb erzeugt, weil `composer install` über `post-install-cmd` → `auto-scripts` → `cache:clear` läuft. Ein zur Beschleunigung ergänztes `--no-scripts` würde die PHPStan-Symfony-Extension still degradieren, ohne dass ein Check rot wird. Die Scripts bleiben deshalb bewusst aktiv; das gehört als Kommentar in den Workflow.

**Wichtig für `tests`:** Ersetzt `doctrine:schema:create` durch

```yaml
- run: php bin/console doctrine:migrations:migrate -n --env=test
- run: php bin/console doctrine:schema:validate --env=test
```

Damit laufen die Migrationen bei jedem PR, und `schema:validate` schlägt an, sobald eine Entity-Änderung ohne passende Migration gemerged werden soll. Das ist der Fehler, der sonst erst beim Produktions-Deploy sichtbar wird – und dort am teuersten ist, weil der `migrations`-Service aus `compose.prod.yaml` den gesamten Stack blockiert.

### 4.3 `build-images` – Build und Push

```yaml
build-images:
  needs: [code-style, static-analysis, lint, frontend, tests]
  runs-on: ubuntu-24.04-arm     # nativer ARM-Runner, kein QEMU
  permissions:
    contents: read
    packages: write
```

Die `needs`-Kette garantiert ohne Zusatzmechanik, dass niemals ein Image entsteht, das die Tests nicht bestanden hat. Der Runner `ubuntu-24.04-arm` ist für öffentliche Repositories kostenlos und baut nativ für `linux/arm64` – deutlich schneller als QEMU-Emulation.

Zwei Images aus demselben Dockerfile, beide mit `cache-from/to: type=gha`:

| Image | Target | Tags |
|---|---|---|
| `ghcr.io/hermanogrimmo/cookingrecipes/php` | `production` | `main`, `sha-<short>` |
| `ghcr.io/hermanogrimmo/cookingrecipes/nginx` | `nginx` | `main`, `sha-<short>` |

**Bei Pull Requests wird gebaut, aber nicht gepusht.** Dank warmem Layer-Cache kostet das kaum Zeit und fängt genau die Fehler ab, die das Dockerfile riskant machen: die `assets`-Stufe mit dem `file:`-Pfad auf `vendor/symfony/ux-live-component` und die `nginx`-Stufe, die aus `production` kopiert. Ein Bruch dort ist heute erst auf dem Server sichtbar.

Der `sha-<short>`-Tag ist gleichzeitig Versionsnummer und Rollback-Ziel.

### 4.4 `deploy.yml` – Deployment

```yaml
on:
  workflow_dispatch:
    inputs:
      image_tag:
        description: 'Zu deployender Tag (z. B. main oder sha-a1b2c3d)'
        default: main

environment: production
concurrency:
  group: deploy-production
  cancel-in-progress: false   # ein laufendes Deployment nie abwürgen
```

Ablauf:

1. `compose.prod.yaml` per `scp` nach `/opt/cooking-recipes/` kopieren
2. Per SSH: `docker login ghcr.io`, dann `docker compose pull` und `up -d --remove-orphans`
3. Warten bis die Healthchecks grün sind, sonst schlägt der Job fehl
4. `docker image prune -f`

`.env.deploy` liegt dauerhaft auf dem Server und wird nie angefasst. Der Server bekommt damit weder Quellcode noch `.git` – nur eine Compose-Datei und Images.

Die Migrationen brauchen keine Sonderbehandlung: der `migrations`-Service läuft über `depends_on: service_completed_successfully` automatisch vor dem `php`-Container.

**Rollback** ist derselbe Workflow mit `image_tag: sha-a1b2c3d`. Kein zweiter Mechanismus und kein Sonderpfad – der Weg zurück ist exakt der Weg nach vorn und damit der einzige Rollback-Pfad, der auch tatsächlich funktioniert, wenn man ihn braucht.

---

## 5. Notwendige Änderungen außerhalb von `.github/`

### 5.1 `compose.prod.yaml` – von `build:` auf `image:`

Der Anchor `x-php-app` baut heute lokal:

```yaml
x-php-app: &php-app
  build:
    context: .
    target: production
  image: cooking-recipes-php:latest
```

wird zu:

```yaml
x-php-app: &php-app
  image: ghcr.io/hermanogrimmo/cookingrecipes/php:${IMAGE_TAG:-main}
```

Analog für den `nginx`-Service. Der Kommentarblock am Dateikopf (`up -d --build`) muss auf den neuen Aufruf angepasst werden.

### 5.2 Postgres-Version vereinheitlichen

Aktuell dreifach uneinheitlich: CI `17`, `docker-compose.yml` `16-alpine`, `compose.prod.yaml` `16-alpine`. Da noch keine Produktionsdaten existieren, wird die Version **jetzt** einmalig festgelegt und an allen Stellen gleichgezogen – inklusive der `serverVersion=`-Parameter in `.env`, `docker-compose.yml` und `compose.prod.yaml`.

> **Offener Punkt:** Zielversion festlegen. Empfehlung: PostgreSQL 17, da produktiv noch nichts läuft und lokal lediglich ein Volume-Reset anfällt.

### 5.3 `feature/production-deployment` nach `main` bringen

Die CD-Workflows setzen das dortige Dockerfile (Stufen `assets`, `production`, `nginx`) sowie `compose.prod.yaml` voraus. Der Branch muss vor oder gemeinsam mit der Pipeline-Umstellung gemerged werden.

### 5.4 `.github/dependabot.yml` – neu

Ökosysteme: `composer`, `npm`, `github-actions`, `docker`.

---

## 6. Secrets und einmalige Einrichtung

| Secret | Zweck |
|---|---|
| `SSH_HOST`, `SSH_USER` | Zielserver |
| `SSH_KEY` | Deploy-Key (privat) |
| `SSH_KNOWN_HOSTS` | Gepinnter Host-Key – **kein** `StrictHostKeyChecking=no` |
| `GHCR_TOKEN` | PAT mit `read:packages`; `GITHUB_TOKEN` gilt auf dem Server nicht |

Dazu ein GitHub-Environment `production`, optional mit Required Reviewer als Bestätigungsstufe.

Auf dem Server einmalig: Docker + Compose-Plugin, Verzeichnis `/opt/cooking-recipes/`, ausgefüllte `.env.deploy` (Vorlage: `.env.deploy.example`).

---

## 7. Bewusst nicht enthalten (YAGNI)

- **PHP-/Datenbank-Matrix** – eine Installation mit fixierten Versionen (E7)
- **Multi-Arch-Images** – der Zielserver ist ARM, amd64 wird nicht gebraucht
- **Code-Coverage-Reporting (Codecov)** – zusätzlicher Dienst und Token ohne konkreten Nutzen bei einem Solo-Projekt
- **Zero-Downtime-Deployment / Blue-Green** – kurze Downtime beim Container-Neustart ist für eine Rezeptseite akzeptabel
- **E2E-Tests (Panther/Cypress)** – separates Thema, erst nach stabiler Pipeline sinnvoll
- **Pinning aller Actions auf SHA** – bei einem öffentlichen Solo-Repo mit Dependabot für `github-actions` ein vertretbarer Kompromiss; kann später nachgezogen werden

---

## 8. Offene Punkte

1. Zielversion PostgreSQL festlegen (Empfehlung: 17, siehe 5.2)
2. Datenbank-Backup-Strategie für die Produktion – bewusst nicht Teil dieses Designs, aber vor dem ersten echten Deploy zu klären
