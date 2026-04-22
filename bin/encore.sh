#!/usr/bin/env bash
# Wrapper: führt Webpack-Encore im Node-Container aus.
# Beispiele:
#   bin/encore.sh dev       (einmaliger Dev-Build)
#   bin/encore.sh dev --watch  (Watch-Mode)
#   bin/encore.sh production --progress
set -euo pipefail
docker compose exec -T node npx encore "$@"
