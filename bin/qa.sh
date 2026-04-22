#!/usr/bin/env bash
# Führt alle QA-Werkzeuge nacheinander aus: CS-Fixer (check), PHPStan, PHPUnit.
# Bei einem Fehler wird das Skript sofort abgebrochen (set -e).
set -e

echo "--- CS-Fixer ---"
docker compose exec -T php vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php

echo "--- PHPStan ---"
docker compose exec -T php vendor/bin/phpstan analyse

echo "--- PHPUnit ---"
docker compose exec -T php vendor/bin/phpunit

echo "--- Alle QA-Checks bestanden ---"
