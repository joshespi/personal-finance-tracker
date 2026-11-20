#!/bin/bash
set -e

cd /var/www/html

rm -f storage/framework/cache/.ready

# In production we install a lean, optimized dependency set. Anywhere else
# (APP_ENV=local etc.) we keep dev dependencies so PHPUnit/Pint survive
# container restarts and tests can run without a manual reinstall.
if [ "${APP_ENV}" = "production" ]; then
    echo "[deploy] composer install (production — no dev deps)"
    composer install --no-dev --optimize-autoloader --no-interaction --quiet
else
    echo "[deploy] composer install (dev deps included — APP_ENV=${APP_ENV:-unset})"
    composer install --optimize-autoloader --no-interaction --quiet
fi

echo "[deploy] migrate"
php artisan migrate --force

echo "[deploy] optimize:clear"
php artisan optimize:clear

echo "[deploy] optimize"
php artisan optimize

# scripts/deploy.sh waits on this marker.
touch storage/framework/cache/.ready

echo "[deploy] starting php-fpm"
exec php-fpm
