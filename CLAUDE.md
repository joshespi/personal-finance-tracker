# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Self-hosted personal finance app ("Personal Finance Tracker", repo formerly `portfolio-tracker`) covering two domains in one Laravel app:

- **Investing** — portfolios, transactions, market-priced assets (Finnhub stocks/bonds/real-estate, CoinGecko crypto), manual assets, tax/realized-gain reporting, snapshots & benchmarks.
- **Budgeting** — envelopes, cash accounts, income, "ready to assign", scheduled/recurring transactions, and a family of planning calculators (cashflow, spending trends, emergency fund, debt payoff, allocator, 50/30/20 budget rule, retirement/FIRE).
- **Import/export** — per-portfolio CSV transaction import (`TransactionImportController` + `TransactionCsvImportService`), a multi-step generic CSV importer for cash transactions (`CsvImportController` + `CsvImportService`: upload → preview → column-mapping → commit/cancel) driven by named column presets in `Support\ImportPresets` (YNAB is one preset among several, no longer a hard-coded parser), and CSV/full-backup exports (`ExportController`).

The Laravel project lives in `src/`. Everything below assumes `src/` is the app root.

## Runtime & how to run commands

The app runs in Docker Compose (PHP 8.5-FPM `app`, MariaDB 11.8 `db`, Nginx, and a separate `node` service — Node is **not** in the PHP container). **The live app runs inside the user's own container; the host checkout has no `vendor/` or `node_modules/`.** Do not assume you can execute artisan/composer/npm directly — surface the command for the user to run, e.g.:

```bash
docker compose exec app php artisan <command>     # artisan/composer in the PHP container
docker compose run --rm node npm run build         # build assets after any JS/CSS change
docker compose run --rm node npm install           # after package.json changes
docker compose exec app composer install           # manual dep restore (rarely needed locally now)
```

The `app` entrypoint runs `composer install`, `migrate --force`, and `optimize` on every start. It installs **with** dev dependencies unless `APP_ENV=production` (which uses `--no-dev`), so local restarts keep PHPUnit/Pint and tests run without a manual reinstall. Editing `docker/php/entrypoint.sh` requires an image rebuild (`docker compose build app`) to take effect.

## Tests

```bash
docker compose exec app php vendor/bin/phpunit                       # full suite (~640 tests, SQLite :memory:)
docker compose exec app php vendor/bin/phpunit --filter EnvelopeTest  # single test class/method
```

`phpunit.xml` forces `sqlite`/`:memory:`, `array` cache/mail, `sync` queue via `tests/bootstrap.php` (which also deletes any cached `bootstrap/cache/config.php`, since a cached config bakes in the container's real `DB_CONNECTION` and would otherwise mask the override). Prefer `vendor/bin/phpunit` directly over `php artisan test` — the latter boots the framework against the live config before handing off to PHPUnit, and has been observed to run against the real MariaDB dev database instead of sqlite (harmless — `RefreshDatabase` still rolls back per test — but produces spurious failures from pre-existing data). Tests live in `tests/Feature` (one file per domain feature — mostly HTTP-level) and `tests/Unit` (model/enum logic). The CLI image sets `memory_limit = 512M` via `docker/php/zz-app.ini`; the full run OOMs at PHP's 128M default (the CSV import tests are the heavy ones). Lint/format with `docker compose exec app ./vendor/bin/pint`.

## Architecture

**Request flow:** thin resource controllers (`app/Http/Controllers`) → domain **Services** (`app/Services`) for anything non-trivial. Services hold the real logic and are the right place to add/read business rules: `ScheduledTransactionService` (recurrence materialization), `RealizedGainService` (FIFO cost-basis/realized-gain lots), `PortfolioPerformanceService` (time-weighted return), `PortfolioAllocationService`, `BudgetRuleService`, `DebtPayoffService`, `ForecastService` (FIRE trajectory) + `RetirementProjectionService` (age-based 4%-rule target) — both power the Retirement tab's two modes, `CsvImportService` (generic column-mapped import; YNAB is one of several `Support\ImportPresets`, not its own service), `BenchmarkService`, `AssetService`, `DemoMode`.

**Routing & auth:** all routes in `routes/web.php` (auth scaffolding in `auth.php`, both wired through `bootstrap/app.php`). Authorization is via **Laravel Policies** (`app/Policies`, one per owned model) — not ad-hoc `abort_unless` checks. Admin area is the `admin` middleware group (`EnsureUserIsAdmin`). Three custom web-group middlewares: `HandleImpersonation` (admin "log in as user"), `ShareDemoMode` (anonymized display mode for screenshots), and `MaterializeDueScheduledTransactions` (see Scheduling below).

**Pricing model (investing core):**
- `Asset` is a globally-shared instrument. `Asset::effectivePriceSource()` resolves the feed: explicit `price_source`, else crypto→CoinGecko / everything else→Finnhub.
- `AssetPrice` rows are timestamped; `latestPrice()` uses `latestOfMany`.
- `UserAssetClassification` lets a user override an asset's *type* (allocation bucket) for themselves without touching the shared price feed. Price-source changes are admin-only.
- `ManualAsset` with `tracking_method = 'proxy_ticker'` derives its value from a `proxyAsset` (e.g. a 401k priced via VOO) — `FetchAssetPrices` deliberately includes proxy targets even when they have no transactions.
- Enums `AssetType` and `PriceSource` (`app/Enums`) are the source of truth; use `AssetType::values()` for validation and `allocationKey()` for net-worth rollups.

**Scheduling (two distinct mechanisms — don't conflate):**
- Cron-style commands registered in `bootstrap/app.php` `withSchedule`: `assets:fetch-prices` (hourly), `portfolios:snapshot` (daily 00:05), `transactions:materialize` (daily 00:10). These need a host cron running `schedule:run`.
- Recurring `ScheduledTransaction`s are **materialized lazily** by `ScheduledTransactionService`, triggered app-wide by the `MaterializeDueScheduledTransactions` web-group middleware on every authenticated request — due entries are created and `next_due_at` advanced (capped at 24 catch-up cycles). The daily `transactions:materialize` command is a backstop for accounts that never open the app, and is deliberately the *only* trigger for the `ScheduledTransactionsSummary` email (an active user materializing on every page load would otherwise get emailed about things they're already looking at).

**Frontend:** Blade + Alpine.js + Tailwind, built by Vite (`node` service). Charts via Chart.js (shared helpers have vitest unit tests — `npm run test`), markdown via EasyMDE, QR via `qrcode`. Two Livewire components (`app/Livewire/TransactionList.php`, single-account ledger; `AllTransactions.php`, the cross-account ledger — sharing form/CRUD logic via `Concerns\ManagesCashTransactionForm`); the app is otherwise server-rendered.

**Conventions:** `Model::preventLazyLoading()` is on outside production — N+1s throw in dev/test, so eager-load relations. Production forces HTTPS. Login events are recorded to `LoginHistory` via a listener.

## Money & email

Market data needs `FINNHUB_API_KEY` in the root `.env`. Mail (password reset, email verification, opt-in summaries) uses Brevo SMTP; set `MAIL_MAILER=log` locally to write to `storage/logs/laravel.log`. App on host port 8080, MariaDB on 3307.
