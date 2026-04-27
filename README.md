# Portfolio Tracker

**Live:** [portfolio.espifam.com](https://portfolio.espifam.com)

Laravel app for tracking investments, manual assets, liabilities, cash, and budgets in one place.

## Stack

- PHP 8.5 / Laravel (FPM)
- MariaDB 11.8
- Nginx
- Node 24 / Vite / Tailwind / Alpine.js / ApexCharts
- Docker Compose

## Prerequisites

- Docker & Docker Compose
- A [Finnhub](https://finnhub.io) API key

## Setup

1. Copy env files:

   ```bash
   cp src/.env.example src/.env
   ```

   Create `.env` at the repo root for compose secrets:

   ```bash
   FINNHUB_API_KEY=your_key_here
   DB_ROOT_PASSWORD=root
   DB_DATABASE=laravel
   DB_USERNAME=laravel
   DB_PASSWORD=secret
   ```

2. First-time build:

   ```bash
   bash setup.sh
   ```

3. Start:

   ```bash
   docker compose up -d
   ```

4. Migrate:

   ```bash
   docker compose exec app php artisan migrate
   ```

   App is at [http://localhost:8080](http://localhost:8080).

## Composer install (dev vs prod)

```bash
# Development — installs everything, including phpunit, faker, etc.
docker compose exec app composer install

# Production — skips dev deps, optimizes autoloader
docker compose exec app composer install --no-dev --optimize-autoloader
```

## Development

```bash
docker compose logs -f app
docker compose exec app php artisan <command>
docker compose exec app npm run dev
```

## Scheduled commands

| Command               | Frequency      | Description                                       |
| --------------------- | -------------- | ------------------------------------------------- |
| `assets:fetch-prices` | Hourly         | Fetches latest prices via Finnhub / CoinGecko     |
| `portfolios:snapshot` | Daily @ 00:05  | Records portfolio value snapshots                 |
| `benchmarks:fetch`    | Daily @ 00:10  | Fetches SPY and BTC close prices                  |

Seed historical benchmarks once:

```bash
docker compose exec app php artisan benchmarks:fetch
```

Cron entry for the scheduler:

```bash
* * * * * docker compose -f /path/to/laravel-app/docker-compose.yml exec -T app php artisan schedule:run >> /dev/null 2>&1
```

## Ports

| Service     | Host port |
| ----------- | --------- |
| Nginx (app) | 8080      |
| MariaDB     | 3307      |
