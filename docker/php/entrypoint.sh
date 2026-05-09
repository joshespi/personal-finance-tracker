#!/bin/bash
set -e

cd /var/www/html

echo "[deploy] composer install"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

echo "[deploy] migrate"
php artisan migrate --force

echo "[deploy] optimize:clear"
php artisan optimize:clear

echo "[deploy] optimize"
php artisan optimize

echo "[deploy] starting php-fpm"
exec php-fpm
