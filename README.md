# Personal Finance Tracker

**Live:** [finance.espifam.com](https://finance.espifam.com)

Self-hosted Laravel app covering both halves of personal finance:

- **Investing** — portfolios, transactions, market-priced assets (Finnhub/CoinGecko), manual assets (incl. proxy-ticker auto-pricing), tax summary, realized gains, dashboards
- **Budgeting** — envelopes (monthly target + savings goals), cash accounts, income entries, "ready to assign", scheduled/recurring transactions, cashflow report, spending trends, emergency-fund calculator, 60/30/20 budget-rule calculator with dashboard drift banner, FIRE/net-worth forecast

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

The seeder creates a representative dataset: two portfolios (one tax-advantaged), bond/stock/crypto/real-estate transactions, a proxy-ticker manual asset (401k tracked via VOO), checking + savings cash accounts, six envelopes (mandatory/discretionary/emergency/savings-goal), six months of paycheck income entries, and two scheduled transactions.

| Email               | Password | Role    |
| ------------------- | -------- | ------- |
| `demo@example.com`  | password | regular |
| `admin@example.com` | password | admin   |

## Development

```bash
docker compose logs -f app
docker compose exec app php artisan <command>
```

Assets are built via the `node` service (Node is not in the PHP container):

```bash
# First time or after package.json changes
docker compose run --rm node npm install

# After any JS/CSS change
docker compose run --rm node npm run build
```

## Scheduled commands

| Command                         | Frequency     | Description                                       |
| ------------------------------- | ------------- | ------------------------------------------------- |
| `assets:fetch-prices`           | Hourly        | Fetches latest prices via Finnhub / CoinGecko     |
| `assets:process-backfill-queue` | Hourly        | Drains queued historical-price backfill requests  |
| `portfolios:snapshot`           | Daily @ 00:05 | Records portfolio value snapshots                 |
| `transactions:materialize`      | Daily @ 00:10 | Creates due recurring transactions                |
| `email:send-summaries`          | Daily @ 06:00 | Sends opted-in account-summary emails             |

Recurring transactions (`ScheduledTransaction`) are also materialised lazily on any authenticated request — due entries are created and `next_due_at` advanced. The `transactions:materialize` command above is the backstop for accounts that never open the app, and is the only trigger for the recurring-transaction summary email.

**No host crontab is needed.** The scheduler runs in-stack as the `scheduler` compose service
(`php artisan schedule:work`). Do **not** also add a host cron running `schedule:run` against
the same stack — two schedulers fire each command twice, which has previously caused duplicate
summary emails. If you have a leftover line from an older install, remove it:

```bash
crontab -l | grep -v 'artisan schedule:run' | crontab -
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
