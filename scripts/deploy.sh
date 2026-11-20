#!/bin/sh
# Pulls the repo and brings the running stack up to date. Dumps the database BEFORE migrating,
# rebuilds images only when Docker files changed, then composer, npm, build, migrate, caches,
# and restarts the scheduler (schedule:work keeps old code loaded).
# Usage: scripts/deploy.sh [--no-pull] [--rebuild]
set -eu

cd "$(dirname "$0")/.."

pull=1
force_build=0
for arg in "$@"; do
    case "$arg" in
        --no-pull) pull=0 ;;
        --rebuild) force_build=1 ;;
        *) echo "Usage: $0 [--no-pull] [--rebuild]" >&2; exit 2 ;;
    esac
done

if [ ! -f .env ]; then
    echo "No .env found in $(pwd)." >&2
    exit 1
fi

app_env=$(grep -m1 '^APP_ENV=' .env | cut -d= -f2- | tr -d '"' || true)

# Paths whose change means the images or service definitions need rebuilding.
BUILD_PATHS="docker docker-compose.yml"

before=$(git rev-parse HEAD)
if [ "$pull" = 1 ]; then
    echo "==> git pull"
    git pull --ff-only
fi
after=$(git rev-parse HEAD)

rebuild=$force_build
# shellcheck disable=SC2086
if [ "$before" != "$after" ] && ! git diff --quiet "$before" "$after" -- $BUILD_PATHS; then
    echo "==> Docker files changed since $(git rev-parse --short "$before")"
    rebuild=1
fi

# Safety net before any migration runs. Skipped on a first deploy when the db isn't up yet.
running_services=$(docker compose ps --status running --services)
if printf '%s\n' "$running_services" | grep -qx db; then
    echo "==> pre-deploy database dump"
    scripts/db-backup.sh
fi

if [ "$rebuild" = 1 ]; then
    echo "==> docker compose up -d --build"
    docker compose up -d --build
else
    echo "==> docker compose up -d"
    docker compose up -d
fi

# The app entrypoint touches this when it has finished its own composer/migrate/optimize pass.
echo "==> waiting for app entrypoint"
tries=0
until docker compose exec -T app test -f storage/framework/cache/.ready; do
    tries=$((tries + 1))
    if [ "$tries" -gt 100 ]; then
        echo "app did not become ready; check: docker compose logs app" >&2
        exit 1
    fi
    sleep 3
done

app() { docker compose exec -T app "$@"; }

echo "==> composer install"
if [ "$app_env" = "production" ]; then
    app composer install --no-dev --optimize-autoloader --no-interaction
else
    app composer install --optimize-autoloader --no-interaction
fi

# node_modules is a bind mount; one installed against another libc (musl vs glibc) lacks
# rolldown's native binding, so reinstall when it can't load.
echo "==> npm ci"
if ! app node -e "require('rolldown')" >/dev/null 2>&1; then
    echo "    rolldown binding missing or wrong platform; reinstalling node_modules"
fi
app npm ci --no-audit --no-fund

echo "==> npm run build"
app npm run build

echo "==> migrate"
app php artisan migrate --force

echo "==> optimize:clear"
app php artisan optimize:clear
if [ "$app_env" = "production" ]; then
    echo "==> optimize"
    app php artisan optimize
fi

# Keep php-fpm workers (www-data) able to write what the root-run steps created.
app sh -c 'chown -R www-data:www-data storage bootstrap/cache' || true

echo "==> restart scheduler"
docker compose restart scheduler

echo "Deployed $(git rev-parse --short HEAD)."
