#!/usr/bin/env bash
# Wrapper: führt npm-Befehle im Node-Container aus.
# Beispiel: bin/npm.sh install, bin/npm.sh run dev
set -euo pipefail
docker compose exec -T node npm "$@"
