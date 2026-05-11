# Personal Finance Tracker

**Live:** [portfolio.espifam.com](https://portfolio.espifam.com) (planned move to `finance.espifam.com` — see ROADMAP)

Self-hosted Laravel app covering both halves of personal finance:

- **Investing** — portfolios, transactions, market-priced assets (Finnhub/CoinGecko), manual assets (incl. proxy-ticker auto-pricing), tax summary, realized gains, dashboards
- **Budgeting / YNAB-replacement** — envelopes (monthly target + savings goals), cash accounts, income entries, "ready to assign", scheduled/recurring transactions, cashflow report, spending trends, emergency-fund calculator, FIRE/net-worth forecast

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

The seeder creates a representative dataset: two portfolios (one tax-advantaged), bond/stock/crypto/real-estate transactions, a proxy-ticker manual asset (401k tracked via VOO), checking + savings cash accounts, six envelopes (mandatory/discretionary/emergency/savings-goal), six months of paycheck income entries, and two scheduled transactions.

| Email               | Password | Role    |
| ------------------- | -------- | ------- |
| `demo@example.com`  | password | regular |
| `admin@example.com` | password | admin   |

## Development

```bash
docker compose logs -f app
docker compose exec app php artisan <command>
docker compose exec app npm run dev
docker compose exec app npm run build       # production assets
```

## Scheduled commands

| Command               | Frequency     | Description                                   |
| --------------------- | ------------- | --------------------------------------------- |
| `assets:fetch-prices` | Hourly        | Fetches latest prices via Finnhub / CoinGecko |
| `portfolios:snapshot` | Daily @ 00:05 | Records portfolio value snapshots             |
| `benchmarks:fetch`    | Daily @ 00:10 | Fetches SPY and BTC close prices              |

Recurring transactions (`ScheduledTransaction`) are materialised lazily — when a user visits `/scheduled-transactions`, any due entries are created and `next_due_at` is advanced. No cron entry needed for that path today.

Cron entry:

```bash
* * * * * docker compose -f /path/to/laravel-app/docker-compose.yml exec -T app php artisan schedule:run >> /dev/null 2>&1
```

Seed historical benchmarks once:

```bash
docker compose exec app php artisan benchmarks:fetch
```

## Email (Brevo SMTP)

Fill in the `MAIL_*` vars in `src/.env` (see `src/.env.example` for the full list). Set `MAIL_MAILER=log` locally to keep emails in `storage/logs/laravel.log`.

Prod checklist:

- Add Brevo SPF + DKIM DNS records for your sending domain (verify with `mail-tester.com`)
- Disable open/click tracking in the Brevo dashboard (privacy — this app does not use tracking pixels)

Email is used for password reset and email verification on new registrations.

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
