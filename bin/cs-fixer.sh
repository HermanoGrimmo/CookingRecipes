#!/usr/bin/env bash

# Führt PHP CS Fixer mit der Projektkonfiguration aus.
# Nutzung:
#   bin/cs-fixer              → Code automatisch korrigieren
#   bin/cs-fixer --dry-run    → nur Fehler anzeigen, nichts ändern

set -euo pipefail

PROJECT_DIR="$(dirname "$(dirname "$(readlink -f "$0")")")"

docker compose -f "$PROJECT_DIR/docker-compose.yml" exec -T php \
    vendor/bin/php-cs-fixer fix \
    --config=.php-cs-fixer.dist.php \
    "$@"
