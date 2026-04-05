#!/bin/bash
set -e

echo "Building containers..."
docker compose build

echo "Creating Laravel project in ./src ..."
docker compose run --rm app composer create-project laravel/laravel . --prefer-dist

echo "Configuring .env..."
cp src/.env.example src/.env
sed -i 's/DB_CONNECTION=.*/DB_CONNECTION=mysql/' src/.env
sed -i 's/DB_HOST=.*/DB_HOST=db/' src/.env
sed -i 's/DB_PORT=.*/DB_PORT=3306/' src/.env
sed -i 's/DB_DATABASE=.*/DB_DATABASE=laravel/' src/.env
sed -i 's/DB_USERNAME=.*/DB_USERNAME=laravel/' src/.env
sed -i 's/DB_PASSWORD=.*/DB_PASSWORD=secret/' src/.env

echo "Generating app key..."
docker compose run --rm app php artisan key:generate

echo "Installing npm dependencies and building assets..."
docker compose run --rm app npm install
docker compose run --rm app npm run build

echo ""
echo "Done! Start the stack with:"
echo "  cd laravel-app && docker compose up -d"
echo "  Then visit http://localhost:8080"
