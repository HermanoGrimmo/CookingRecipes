# CI/CD-Optimierung – Implementierungsplan

**Datum:** 2026-08-16
**Design:** [2026-08-16-cicd-optimierung-design.md](2026-08-16-cicd-optimierung-design.md)
**Branch:** `feature/cicd-optimierung`

Der Plan ist in fünf Phasen gegliedert. Phase 0–2 und 4 sind vollständig verifizierbar. **Phase 3 (Deployment) ist nur vorbereitend**, da noch kein Server existiert – sie wird geschrieben, aber nicht scharf geschaltet.

---

## Vorab: zwei Befunde aus der Code-Prüfung

### B1 – Die Migration bricht ohne `ADMIN_INITIAL_PASSWORD` ab

`migrations/Version20260409222000.php` wirft in `up()`:

```php
if (empty($_ENV['ADMIN_INITIAL_PASSWORD'])) {
    throw new \RuntimeException('Die Umgebungsvariable ADMIN_INITIAL_PASSWORD muss gesetzt sein, ...');
}
```

Die Umstellung von `doctrine:schema:create` auf `doctrine:migrations:migrate` (Design §4.2) macht die CI damit sofort rot. **Gegenmaßnahme:** Der `tests`-Job setzt `ADMIN_INITIAL_PASSWORD` auf einen Dummy-Wert. Das ist unkritisch, da die Test-Datenbank pro Run neu entsteht – und hat den Nebeneffekt, dass die Migration selbst mitgetestet wird.

### B2 – Abweichung vom Design: PHP-CS-Fixer nicht als Docker-Action

Das Design (§4.2, ursprüngliche Fassung) sah PHP-CS-Fixer als Docker-Action nach dem Vorbild von symfony/demo vor, um `composer install` zu sparen. Bei genauerem Hinsehen ist das hier die schlechtere Wahl: `composer.json` pinnt `friendsofphp/php-cs-fixer: ^3.94.2`, und `bin/cs-fixer.sh` nutzt lokal die Version aus `vendor/`. Das Docker-Image liefert dagegen immer die neueste 3.x. Sobald dort eine neue Regel in `@Symfony` landet, schlägt CI bei Code fehl, der lokal sauber durchläuft.

Ein Linter, dem man nicht trauen kann, wird ignoriert. Die eingesparten ~15 s (bei warmem Composer-Cache) sind das nicht wert. **Entscheidung E9** im Design wurde entsprechend nachgetragen.

---

## Phase 0 – Vorarbeiten

### 0.1 `feature/production-deployment` nach `main` mergen

Die Phasen 2 und 3 setzen die dortigen Dockerfile-Stufen (`assets`, `production`, `nginx`) und `compose.prod.yaml` voraus.

### 0.2 PostgreSQL auf 17 vereinheitlichen (E8)

| Datei | Änderung |
|---|---|
| `.env` (Zeile 50) | `serverVersion=16` → `serverVersion=17` |
| `docker-compose.yml` | `postgres:16-alpine` → `postgres:17-alpine`; `serverVersion=16` → `17` in `DATABASE_URL` |
| `compose.prod.yaml` | `postgres:16-alpine` → `postgres:17-alpine`; `serverVersion=16` → `17` in `x-php-env` |
| `config/packages/doctrine.yaml` | Kommentar `#server_version: '16'` → `'17'` |

**Lokal erforderlich:** Das bestehende Volume ist nicht in-place aufwärtskompatibel.

```bash
docker compose down -v
docker compose up -d
docker compose exec php php bin/console doctrine:migrations:migrate
```

**Verifikation:** `docker compose exec postgres psql -U cooking -d cooking_recipes -c "SELECT version();"` meldet 17.x, `bin/qa.sh` läuft grün durch.

---

## Phase 1 – CI konsolidieren

### 1.1 Composite Action `.github/actions/php-setup/action.yml`

Vier der fünf Jobs brauchen dieselbe PHP-Einrichtung. Statt den Block viermal zu duplizieren (das Problem, das bolt/core hat), wird er einmal gekapselt – so ist auch die PHP-Version an genau einer Stelle definiert.

```yaml
name: 'PHP einrichten'
description: 'Installiert PHP inklusive Composer-Abhängigkeiten mit Cache.'

inputs:
  extensions:
    description: 'Zusätzliche PHP-Erweiterungen'
    required: false
    default: ''

runs:
  using: composite
  steps:
    - uses: shivammathur/setup-php@v2
      with:
        php-version: '8.4'
        coverage: none
        extensions: ${{ inputs.extensions }}
        tools: cs2pr

    - name: Composer-Cache-Verzeichnis ermitteln
      id: composer-cache
      shell: bash
      run: echo "dir=$(composer config cache-files-dir)" >> "$GITHUB_OUTPUT"

    - uses: actions/cache@v4
      with:
        path: ${{ steps.composer-cache.outputs.dir }}
        key: ${{ runner.os }}-composer-${{ hashFiles('composer.lock') }}
        restore-keys: ${{ runner.os }}-composer-

    # WICHTIG: kein --no-scripts. Die auto-scripts erzeugen über cache:clear die
    # Datei var/cache/dev/App_KernelDevDebugContainer.xml, auf die phpstan.dist.neon
    # verweist. Ohne sie degradiert die PHPStan-Symfony-Extension still.
    - name: Composer-Abhängigkeiten installieren
      shell: bash
      run: composer install --no-progress --prefer-dist --no-interaction
```

### 1.2 `.github/workflows/ci.yml` anlegen

```yaml
name: CI

on:
  pull_request:
  push:
    branches: [main]

concurrency:
  group: ${{ github.workflow }}-${{ github.event.pull_request.number || github.ref }}
  cancel-in-progress: true

permissions:
  contents: read

jobs:
  code-style:
    name: Code Style
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          persist-credentials: false
      - uses: ./.github/actions/php-setup
      - name: PHP CS Fixer
        run: vendor/bin/php-cs-fixer check --config=.php-cs-fixer.dist.php --diff --format=checkstyle | cs2pr

  static-analysis:
    name: Static Analysis
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          persist-credentials: false
      - uses: ./.github/actions/php-setup
      - name: PHPStan
        run: vendor/bin/phpstan analyse --no-progress --error-format=github

  lint:
    name: Lint
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          persist-credentials: false
      - uses: ./.github/actions/php-setup
        with:
          extensions: intl

      # if: always() sorgt dafür, dass ein Fehler die restlichen Prüfungen nicht
      # verschluckt – man sieht alle Probleme in einem Durchlauf.
      - name: Composer-Konfiguration prüfen
        if: always()
        run: composer validate --no-check-publish --strict

      - name: YAML-Dateien prüfen
        if: always()
        run: php bin/console lint:yaml config --parse-tags

      - name: Twig-Templates prüfen
        if: always()
        run: php bin/console lint:twig templates --env=prod

      - name: Service-Container prüfen
        if: always()
        run: php bin/console lint:container --no-debug

      - name: Doctrine-Mapping prüfen
        if: always()
        run: php bin/console doctrine:schema:validate --skip-sync -vvv --no-interaction

      - name: Sicherheitshinweise zu Abhängigkeiten
        if: always()
        run: composer audit

  frontend:
    name: Frontend
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          persist-credentials: false

      # package.json referenziert @symfony/ux-live-component über einen file:-Pfad
      # in vendor/. Ohne composer install schlägt npm ci deshalb fehl.
      - uses: ./.github/actions/php-setup

      - uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: npm

      - run: npm ci
      - name: Assets bauen
        run: npm run build
      - name: Sicherheitshinweise zu npm-Paketen
        run: npm audit --audit-level=high

  tests:
    name: Tests
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:17-alpine
        env:
          POSTGRES_USER: app
          POSTGRES_PASSWORD: app
          # doctrine.yaml hängt im Test-Env über dbname_suffix "_test" an.
          POSTGRES_DB: app_test
        ports:
          - 5432:5432
        options: >-
          --health-cmd=pg_isready
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    env:
      APP_ENV: test
      DATABASE_URL: postgresql://app:app@localhost:5432/app?serverVersion=17&charset=utf8
      # Version20260409222000 bricht ohne diese Variable mit einer RuntimeException ab.
      # Der Wert ist beliebig – die Test-Datenbank entsteht pro Lauf neu.
      ADMIN_INITIAL_PASSWORD: ci-dummy-password

    steps:
      - uses: actions/checkout@v4
        with:
          persist-credentials: false
      - uses: ./.github/actions/php-setup
        with:
          extensions: pdo_pgsql

      - name: Migrationen ausführen
        run: php bin/console doctrine:migrations:migrate --no-interaction

      # Schlägt an, sobald eine Entity-Änderung ohne passende Migration gemerged wird.
      - name: Schema gegen Mapping prüfen
        run: php bin/console doctrine:schema:validate

      - name: PHPUnit
        run: vendor/bin/phpunit
```

### 1.3 Alte Workflows löschen

`.github/workflows/tests.yml`, `static-analysis.yml`, `code-style.yml` entfernen.

### 1.4 Branch-Protection anpassen

Die Required-Status-Checks in den Repository-Einstellungen zeigen auf die alten Job-Namen (`PHPUnit`, `PHPStan`, `PHP CS Fixer`) und müssen auf `Code Style`, `Static Analysis`, `Lint`, `Frontend`, `Tests` umgestellt werden. **Wird das vergessen, blockiert `main` auf Checks, die nie mehr laufen.**

**Verifikation Phase 1:** PR gegen `main` öffnen. Erwartet: fünf Checks, jeweils genau **ein** Run (keine Verdopplung mehr). Zusätzlich einen Wegwerf-Commit mit einer Entity-Änderung ohne Migration pushen und prüfen, dass `tests` an `schema:validate` scheitert – damit ist die neue Absicherung nachweislich wirksam.

---

## Phase 2 – Docker-Images bauen und pushen

### 2.1 Job `build-images` in `ci.yml` ergänzen

```yaml
  build-images:
    name: Docker-Images
    needs: [code-style, static-analysis, lint, frontend, tests]
    runs-on: ubuntu-24.04-arm    # nativer ARM64-Runner, kostenlos für öffentliche Repos
    permissions:
      contents: read
      packages: write

    steps:
      - uses: actions/checkout@v4
        with:
          persist-credentials: false

      # GHCR akzeptiert ausschließlich Kleinbuchstaben, github.repository liefert
      # aber "HermanoGrimmo/CookingRecipes".
      - name: Repository-Name in Kleinbuchstaben
        id: repo
        run: echo "name=${GITHUB_REPOSITORY,,}" >> "$GITHUB_OUTPUT"

      - uses: docker/setup-buildx-action@v3

      - name: An GHCR anmelden
        if: github.event_name == 'push'
        uses: docker/login-action@v3
        with:
          registry: ghcr.io
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Metadaten (php)
        id: meta-php
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/${{ steps.repo.outputs.name }}/php
          tags: |
            type=ref,event=branch
            type=sha,prefix=sha-

      - name: Image php bauen
        uses: docker/build-push-action@v6
        with:
          context: .
          target: production
          platforms: linux/arm64
          push: ${{ github.event_name == 'push' }}
          tags: ${{ steps.meta-php.outputs.tags }}
          labels: ${{ steps.meta-php.outputs.labels }}
          cache-from: type=gha,scope=arm64
          cache-to: type=gha,mode=max,scope=arm64

      - name: Metadaten (nginx)
        id: meta-nginx
        uses: docker/metadata-action@v5
        with:
          images: ghcr.io/${{ steps.repo.outputs.name }}/nginx
          tags: |
            type=ref,event=branch
            type=sha,prefix=sha-

      # Nutzt denselben Cache-Scope: die nginx-Stufe kopiert aus production,
      # die Layer sind zu diesem Zeitpunkt bereits gebaut.
      - name: Image nginx bauen
        uses: docker/build-push-action@v6
        with:
          context: .
          target: nginx
          platforms: linux/arm64
          push: ${{ github.event_name == 'push' }}
          tags: ${{ steps.meta-nginx.outputs.tags }}
          labels: ${{ steps.meta-nginx.outputs.labels }}
          cache-from: type=gha,scope=arm64
          cache-to: type=gha,mode=max,scope=arm64
```

Bei Pull Requests wird gebaut, aber nicht gepusht – das fängt Brüche in der `assets`- und `nginx`-Stufe ab, die heute erst auf dem Server sichtbar würden.

### 2.2 Paket-Sichtbarkeit

Nach dem ersten erfolgreichen Push sind die GHCR-Pakete zunächst privat. Für Phase 3 entweder auf öffentlich stellen (dann entfällt `GHCR_TOKEN` komplett) oder privat lassen und den PAT einrichten.

**Verifikation Phase 2:** Nach Merge nach `main` prüfen:

```bash
docker manifest inspect ghcr.io/hermanogrimmo/cookingrecipes/php:main
```

Erwartet: `"architecture": "arm64"`. Ein Build auf falscher Architektur fällt sonst erst beim ersten Serverstart auf.

---

## Phase 3 – Deployment vorbereiten (nicht scharf)

> Ohne Zielserver nicht end-to-end testbar. Die Dateien werden geschrieben und committet, der Workflow lässt sich mangels Secrets nicht erfolgreich ausführen. Das ist beabsichtigt.

### 3.1 `compose.prod.yaml` von `build:` auf `image:` umstellen

```yaml
x-php-app: &php-app
  image: ghcr.io/hermanogrimmo/cookingrecipes/php:${IMAGE_TAG:-main}
  networks:
    - cooking
```

Analog der `nginx`-Service. Der Kommentarblock am Dateikopf beschreibt derzeit `up -d --build` und muss auf den Pull-basierten Ablauf angepasst werden.

### 3.2 `.github/workflows/deploy.yml`

```yaml
name: Deploy

on:
  workflow_dispatch:
    inputs:
      image_tag:
        description: 'Zu deployender Tag (z. B. main oder sha-a1b2c3d)'
        required: true
        default: main

concurrency:
  group: deploy-production
  cancel-in-progress: false    # ein laufendes Deployment nie abbrechen

permissions:
  contents: read

jobs:
  deploy:
    name: Produktion
    runs-on: ubuntu-latest
    environment: production
    steps:
      - uses: actions/checkout@v4
        with:
          persist-credentials: false

      - name: SSH einrichten
        run: |
          mkdir -p ~/.ssh
          echo "${{ secrets.SSH_KEY }}" > ~/.ssh/id_ed25519
          chmod 600 ~/.ssh/id_ed25519
          echo "${{ secrets.SSH_KNOWN_HOSTS }}" > ~/.ssh/known_hosts

      - name: Compose-Datei übertragen
        run: scp compose.prod.yaml ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}:/opt/cooking-recipes/

      - name: Deployment ausführen
        run: |
          ssh ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }} bash -eu <<'REMOTE'
            cd /opt/cooking-recipes
            echo "${{ secrets.GHCR_TOKEN }}" | docker login ghcr.io -u ${{ github.actor }} --password-stdin
            export IMAGE_TAG="${{ inputs.image_tag }}"
            docker compose --env-file .env.deploy -f compose.prod.yaml pull
            docker compose --env-file .env.deploy -f compose.prod.yaml up -d --remove-orphans
            docker image prune -f
          REMOTE

      - name: Health prüfen
        run: |
          ssh ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }} \
            'cd /opt/cooking-recipes && docker compose -f compose.prod.yaml ps --format json' \
            | tee /dev/stderr | grep -q '"Health":"healthy"'
```

Rollback ist derselbe Workflow mit `image_tag: sha-a1b2c3d`.

### 3.3 Checkliste für die spätere Server-Einrichtung

- [ ] Docker + Compose-Plugin installieren
- [ ] Verzeichnis `/opt/cooking-recipes/` anlegen
- [ ] `.env.deploy` aus `.env.deploy.example` befüllen (`APP_SECRET`, `POSTGRES_PASSWORD`, `MAILER_DSN`, `DEFAULT_URI`, `ADMIN_INITIAL_PASSWORD`)
- [ ] Deploy-Key erzeugen, öffentlichen Teil in `~/.ssh/authorized_keys`, privaten als `SSH_KEY`-Secret
- [ ] `SSH_KNOWN_HOSTS` über `ssh-keyscan <host>` füllen – **kein** `StrictHostKeyChecking=no`
- [ ] `GHCR_TOKEN` (PAT, `read:packages`) anlegen, falls die Pakete privat bleiben
- [ ] GitHub-Environment `production` anlegen, optional mit Required Reviewer
- [ ] TLS-Proxy davorschalten (`HTTP_BIND=127.0.0.1` bleibt gesetzt)
- [ ] `ADMIN_INITIAL_PASSWORD` nach dem ersten Deploy wieder aus `.env.deploy` entfernen

---

## Phase 4 – `.github/dependabot.yml`

```yaml
version: 2
updates:
  - package-ecosystem: composer
    directory: /
    schedule:
      interval: weekly
  - package-ecosystem: npm
    directory: /
    schedule:
      interval: weekly
  - package-ecosystem: github-actions
    directory: /
    schedule:
      interval: weekly
  - package-ecosystem: docker
    directory: /
    schedule:
      interval: weekly
```

Gruppierung der Minor-/Patch-Updates kann später ergänzt werden, falls das PR-Aufkommen stört.

---

## Risiken

| Risiko | Auswirkung | Gegenmaßnahme |
|---|---|---|
| `ADMIN_INITIAL_PASSWORD` fehlt (B1) | `tests` bricht ab | Dummy-Wert im Job-`env`, siehe 1.2 |
| `lint:twig --env=prod` bootet den Prod-Container | Job scheitert an fehlenden Prod-Variablen | Bei Fehlschlag auf `--env=dev` wechseln; `entrypoints.json` wird nicht benötigt, da nur geparst wird |
| Branch-Protection zeigt auf alte Check-Namen | `main` dauerhaft blockiert | Schritt 1.4 nicht überspringen |
| GHCR-Pfad mit Großbuchstaben | Push wird abgelehnt | `${GITHUB_REPOSITORY,,}`, siehe 2.1 |
| Postgres-Volume nicht aufwärtskompatibel | Lokale DB startet nicht | `docker compose down -v` in 0.2 |
| `npm audit` schlägt bei Dev-Advisories an | `frontend` unnötig rot | `--audit-level=high`; notfalls `continue-on-error: true` |

---

## Umsetzungsnotizen (Phase 0, 1 und 4 – erledigt am 2026-08-16)

Alle Schritte der neuen Jobs wurden vor dem Commit lokal im PHP-Container ausgeführt. Drei Dinge kamen dabei anders als geplant:

**B1 bestätigt.** `doctrine:migrations:migrate` bricht ohne `ADMIN_INITIAL_PASSWORD` tatsächlich ab – nicht nur laut Quelltext, sondern verifiziert. Mit dem Dummy-Wert laufen die vier Migrationen durch, `doctrine:schema:validate` meldet Schema und Mapping als synchron, PHPUnit ist mit 62 Tests grün.

**B3 – `composer validate --strict` schlug fehl (Exit 1).** Ursache waren drei unbegrenzte Constraints (`doctrine/doctrine-bundle: >=3.2.2`, `doctrine/doctrine-migrations-bundle: >=4`, `doctrine/orm: >=3.6.3`). Unbegrenzt heißt: ein `composer update` hätte ohne Weiteres ORM 4.0 mit Breaking Changes eingezogen. Statt den neuen Check abzuschwächen wurden die Constraints auf Caret-Form umgestellt. Da alle drei Pakete exakt auf ihrer Untergrenze gelockt waren, änderte sich an der Auflösung nichts – `composer update --lock` hat lediglich den Content-Hash erneuert.

**B4 – beide Audit-Schritte schlugen an.**

- `composer audit`: 42 Advisories in den gelockten Symfony-8.0-Paketen, darunter CVE-2026-45075 (high, HEAD-Request umgeht `methods: ['GET']` in `#[IsGranted]` / `#[IsCsrfTokenValid]`) und CRLF-Injection in `symfony/mime`. **Produktionsrelevant.** Behoben durch `composer update "symfony/*" --with-all-dependencies`; danach meldet Composer keine Advisories mehr. Der Schritt bleibt blockierend. Nebeneffekt: `bump-after-update: true` hat die Untergrenzen einiger Constraints auf die installierten Versionen angehoben – die `8.0.*`-Constraints der Symfony-Pakete blieben unberührt.
- `npm audit`: 10 Funde (9 high), ausschließlich in `devDependencies` der Webpack-/Babel-Toolchain. Dieser Code läuft nie in Produktion, das Build-Ergebnis sind statische Assets. Der Schritt bekam `continue-on-error: true` mit Begründung im Workflow; das kann entfallen, sobald Dependabot den Bestand abgeräumt hat.

**B5 – `variables_order` unterschied sich zwischen CI und Produktion.** Der erste CI-Lauf ließ den `tests`-Job an genau der Migration aus B1 scheitern, obwohl `ADMIN_INITIAL_PASSWORD` im Job-`env` gesetzt war. Ursache: Die Migration liest `$_ENV`, und ob echte Umgebungsvariablen dort landen, steuert die PHP-Direktive `variables_order`. Das offizielle `php`-Image lädt gar keine `php.ini` und nutzt damit PHPs eingebauten Default `EGPCS` – `$_ENV` ist befüllt. `shivammathur/setup-php` installiert dagegen eine `php.ini` mit `GPCS`; ohne `E` bleibt `$_ENV` leer.

Die Composite Action setzt deshalb `ini-values: variables_order=EGPCS` und gleicht die CI an die Konfiguration des Produktions-Images an. Lokal reproduziert: mit `php -d variables_order=GPCS` scheitert die Migration exakt wie in CI, mit `EGPCS` läuft sie durch.

> **Anmerkung:** Dass die Migration auf `$_ENV` statt auf `$_SERVER` oder `getenv()` zugreift, ist die eigentliche Fragilität. Der Zugriff funktioniert nur, solange `variables_order` ein `E` enthält. Eine Umstellung der Migration wäre robuster, wurde hier aber bewusst nicht vorgenommen – sie ist bereits produktiv gelaufen und gehört nicht in einen CI-Umbau.

### Phase 2 (erledigt am 2026-08-16)

Der Job `build-images` wurde wie geplant ergänzt. Da der Entwicklungsrechner ein Apple-Silicon-Mac ist, konnten beide Images vorab **nativ in der Zielarchitektur** gebaut werden – dieselbe `linux/arm64`, die später auf der CAX-Instanz läuft:

| Image | Größe | Architektur |
|---|---|---|
| `production` | 66 MB | `arm64/linux` |
| `nginx` | 22 MB | `arm64/linux` |

Inhaltlich geprüft: `public/build/entrypoints.json` vorhanden, `vendor/` vorhanden, `tests/` korrekt über `.dockerignore` ausgeschlossen, Composer-Binary aus dem Produktions-Image entfernt, Prozessbenutzer `www-data`. Der `cache:warmup` in der `production`-Stufe läuft durch.

`nginx -t` schlägt außerhalb des Compose-Netzes mit `host not found in upstream "php"` fehl – das ist erwartetes Verhalten und kein Defekt, da der Upstream erst zur Laufzeit im gemeinsamen Netz existiert.

**Nicht erledigt und nicht aus dem Repo heraus erledigbar:** Schritt 1.4 (Branch-Protection auf die neuen Check-Namen umstellen) und Schritt 2.2 (Sichtbarkeit der GHCR-Pakete nach dem ersten Push).

---

## Empfohlene Reihenfolge

Phase 0 → 1 → 4 in einem PR (vollständig verifizierbar), danach Phase 2 in einem zweiten PR (braucht einen `main`-Push für den ersten echten GHCR-Test). Phase 3 erst, wenn der Server existiert – sonst liegt ungetesteter Code als Attrappe im Repo.
