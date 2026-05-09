# Portfolio Tracker

**Live:** [portfolio.espifam.com](https://portfolio.espifam.com)

Laravel app for tracking investments, manual assets, liabilities, cash, and budgets.

## Stack

- PHP 8.5 / Laravel (FPM)
- MariaDB 11.8 / Nginx
- Node 24 / Vite / Tailwind / Alpine.js / Chart.js / EasyMDE
- Docker Compose

## Setup

```bash
cp .env.example .env
cp src/.env.example src/.env
```

Fill in `.env`: set `FINNHUB_API_KEY` and generate an `APP_KEY`:

```bash
docker compose run --rm app php artisan key:generate --show
```

Then build and start:

```bash
docker compose up -d --build
```

Migrations run automatically on container start. App is at [http://localhost:8080](http://localhost:8080).

Seed demo data (dev only):

```bash
docker compose exec app php artisan db:seed
```

| Email               | Password | Role    |
| ------------------- | -------- | ------- |
| `demo@example.com`  | password | regular |
| `admin@example.com` | password | admin   |

## Development

```bash
docker compose logs -f app
docker compose exec app php artisan <command>
docker compose exec app npm run dev
```

## Scheduled commands

| Command               | Frequency     | Description                                   |
| --------------------- | ------------- | --------------------------------------------- |
| `assets:fetch-prices` | Hourly        | Fetches latest prices via Finnhub / CoinGecko |
| `portfolios:snapshot` | Daily @ 00:05 | Records portfolio value snapshots             |
| `benchmarks:fetch`    | Daily @ 00:10 | Fetches SPY and BTC close prices              |

Cron entry:

```bash
* * * * * docker compose -f /path/to/laravel-app/docker-compose.yml exec -T app php artisan schedule:run >> /dev/null 2>&1
```

Seed historical benchmarks once:

```bash
docker compose exec app php artisan benchmarks:fetch
```

## Tests

```bash
docker compose exec app php artisan test
```

## Ports

| Service | Host port |
| ------- | --------- |
| App     | 8080      |
| MariaDB | 3307      |

## Logging

Logs go to `src/storage/logs/laravel.log` and container stderr (`docker compose logs -f app`). PHP-FPM fatals also route to stderr via `docker/php/zzz-laravel.conf`.
