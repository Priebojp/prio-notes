#!/usr/bin/env bash
# Deploys the Laravel app in server/ on Laravel Forge (standard, non zero-downtime site).
#
# Forge deploy script (Site → Deployments → Deploy script):
#
#   cd /home/forge/note.prieboj.sk
#   git pull origin $FORGE_SITE_BRANCH
#   bash deploy/forge-deploy.sh
#
# Forge exports FORGE_PHP / FORGE_COMPOSER / FORGE_PHP_FPM to this script.
set -euo pipefail

PHP=${FORGE_PHP:-php}
COMPOSER=${FORGE_COMPOSER:-composer}

REPO_ROOT=$(cd "$(dirname "$0")/.." && pwd)
cd "$REPO_ROOT/server"

# Forge's environment editor writes .env either into the repo root or into
# server/ (when the site's root directory is set to /server). The app reads
# server/.env, so link the root one in if that is where it lives.
if [ ! -e .env ] && [ -f "$REPO_ROOT/.env" ]; then
    ln -s ../.env .env
fi

$COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci --no-audit --no-fund
npm run build

$PHP artisan migrate --force
$PHP artisan optimize
$PHP artisan queue:restart

if [ -n "${FORGE_PHP_FPM:-}" ]; then
    ( flock -w 10 9 || exit 1
      echo 'Restarting FPM...'; sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock
fi
