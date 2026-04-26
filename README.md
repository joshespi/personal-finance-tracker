# Portfolio Tracker

**Live:** [portfolio.espifam.com](https://portfolio.espifam.com)

A self-hosted Laravel application for tracking investment portfolios across stocks, crypto, and non-standard assets (real estate, vehicles, collectibles — anything with a value you want to timestamp and track). Supports transactions, asset price fetching via Finnhub, manual assets with custom valuations, periodic portfolio snapshots, an aggregated all-holdings view across all portfolios, linked portfolio-to-portfolio transfers, FIFO realized gain/loss tracking, watchlist, time-weighted return, rebalancing suggestions, benchmark comparison, ApexCharts-powered visualizations, and a full admin panel.

## Stack

- **PHP 8.5** / Laravel (FPM)
- **MariaDB 11.8**
- **Nginx**
- **Node 24** / Vite / Tailwind CSS / ApexCharts
- **Alpine.js** for reactive UI (autocomplete, dark mode, etc.)
- Orchestrated with Docker Compose

## Prerequisites

- Docker & Docker Compose
- A [Finnhub](https://finnhub.io) API key

## Getting Started

### 1. Clone and configure environment

```bash
cp src/.env.example src/.env
```

Edit `src/.env` and set your database credentials and `APP_KEY` (or let the setup script handle it).

Create a `.env` file at the project root for Docker Compose secrets:

```bash
# .env (root)
FINNHUB_API_KEY=your_key_here
DB_ROOT_PASSWORD=root
DB_DATABASE=laravel
DB_USERNAME=laravel
DB_PASSWORD=secret
```

### 2. First-time setup

```bash
bash setup.sh
```

This builds the containers, installs Composer and npm dependencies, generates the app key, and builds frontend assets.

### 3. Start the stack

```bash
docker compose up -d
```

The app is available at [http://localhost:8080](http://localhost:8080).

### 4. Run migrations

```bash
docker compose exec app php artisan migrate
```

## Development

```bash
# Tail logs
docker compose logs -f app

# Run artisan commands
docker compose exec app php artisan <command>

# Install a Composer package
docker compose exec app composer require <package>

# Build frontend assets (watch mode)
docker compose exec app npm run dev
```

## Why This Exists

I couldn't find a portfolio tracker that was simple but covered all the asset types I actually own and wanted to track. Most apps handle stocks *or* crypto, sometimes both — and none of them let you track non-standard assets like a property, a card collection, or a vehicle. Everything you own lives on one balance sheet, so your tracker should too — including a real picture of your net worth.

We only ask for the minimum information needed to create an account. All asset data is entered manually and is not linked to any brokerage, exchange, or financial institution — it exists purely for personal tracking and visualization.

## Issues & Feature Requests

Found a bug or have an idea? [Open an issue on GitHub](https://github.com/joshespi/portfolio-tracker/issues). Please include:

- What you expected to happen
- What actually happened (screenshots welcome)
- Steps to reproduce if it's a bug

Feature requests are welcome — describe the use case, not just the feature.

## Features

### Dashboard

- Total cost basis, market value, unrealized P&L, and total assets across all portfolios
- **Summary tiles update with range** — selecting a time range updates the P&L tile to show period gain/loss (e.g. "1Y Gain/Loss") rather than all-time unrealized
- **Portfolio value chart** (ApexCharts area chart) with time range toggles: 5D · 1W · 1M · 3M · 6M · 1Y · YTD · 5Y · 10Y · All
- **Manual assets toggle** — hide/show manual assets in the chart; preference saved in `localStorage`
- **Benchmark comparison chart** normalized to 100% — compare your portfolio return against SPY (S&P 500) and BTC
- **Asset allocation donut chart** — Stocks / Crypto / Manual Assets breakdown
- **Sortable all-holdings table** — click any column header to sort; columns include Symbol, Type, Qty, Cost Basis, Price, Market Value, P&L, % of Total
- **Inline asset reclassification** — click the Stock/Crypto badge in any holdings table to toggle an asset's type (e.g. mark ARKB as Crypto)

### Portfolio page

- Per-portfolio value history chart with the same time range toggle set
- Benchmark comparison overlay on the same period
- **Holdings allocation donut** — per-ticker breakdown
- **Sortable holdings table** — click any column header to sort; inline reclassification badge on each row
- **FIFO Realized Gains/Losses** — closed lot-by-lot detail with buy date, sell date, cost basis, proceeds, gain/loss, days held, and short/long term indicator (LT ≥ 365 days)
  - Annual summary (by-year realized gain)
- **Time-Weighted Return (TWR)** — total and annualized, shown in the header stats
- **Rebalancing suggestions** — set target stock/crypto/manual % in portfolio settings; the page shows current vs. target and buy/sell amounts
- **Dividend income** — total income received per asset; **Income** and **Yield on Cost (YOC)** columns in the holdings table
- Manual assets (real estate, vehicles, etc.)
- **Portfolio Journal** — timestamped, rich-text notes per portfolio; record your investment thesis, decisions, and observations

### Tax Summary

- Cross-portfolio view of all realized gains and losses, broken out by **tax year**
- Each year shows **short-term** (held < 1 year) and **long-term** (held ≥ 1 year) totals separately
- Lot-by-lot detail: symbol, portfolio, quantity, buy date, sell date, days held, cost basis, proceeds, gain/loss
- All-years summary table for a quick multi-year overview
- **Export to CSV** — download realized gains as a CSV ready to hand to an accountant

### All Transactions

- Cross-portfolio transaction log (nav: **Transactions**)
- Filter by portfolio, symbol, transaction type, and date range
- Sortable by Date, Portfolio, Symbol, Type, Quantity
- Edit/delete links navigate to the per-portfolio transaction actions
- **Export to CSV** — download full transaction history
- **Import from CSV** — bulk-import transactions via a CSV template (download template from the transactions list)

### Watchlist

- Add any ticker with an optional target price and notes
- Shows current price, % to target (green = already hit, amber = upside remaining), and asset type badge
- Syncs with the assets table for prices already fetched

### Ticker Autocomplete

- On the "Add Transaction" and "Add to Watchlist" forms, the symbol field queries local assets first, then falls back to Finnhub search (stocks) or CoinGecko search (crypto)
- Keyboard-navigable dropdown; selecting a result auto-fills asset type

### Admin Panel

- **Dashboard** — aggregate stats: total users, portfolios, transactions, holdings value, watchlist items, login events, activity log entries
- **User management** — view, edit, delete users; verified/unverified badge; user detail shows login history and portfolio count
- **Impersonation** — start/stop impersonating any user; amber banner shown while impersonating; action logged to activity log
- **Activity log** — filterable log of all user actions (portfolio created/updated/deleted, transactions, watchlist, login, impersonation events)
- **Settings** — enable/disable new user registration

## Scheduled Commands

| Command                 | Frequency     | Description                                                          |
| ----------------------- | ------------- | -------------------------------------------------------------------- |
| `assets:fetch-prices`   | Every hour    | Fetches latest prices for tracked assets via Finnhub / CoinGecko     |
| `portfolios:snapshot`   | Daily @ 00:05 | Records a point-in-time snapshot of each portfolio's value           |
| `benchmarks:fetch`      | Daily @ 00:10 | Fetches daily SPY and BTC close prices for benchmark comparison      |

### Initial benchmark seeding

Run this once to populate historical benchmark data (up to 10 years):

```bash
docker compose exec app php artisan benchmarks:fetch
```

To add a cron entry for ongoing updates:

```bash
* * * * * docker compose -f /path/to/laravel-app/docker-compose.yml exec -T app php artisan schedule:run >> /dev/null 2>&1
```

## Key Models

| Model                             | Description                                                                                                  |
| --------------------------------- | ------------------------------------------------------------------------------------------------------------ |
| `Portfolio`                       | A named collection of assets belonging to a user                                                             |
| `Transaction`                     | A buy/sell/transfer record for a tracked asset within a portfolio                                            |
| `Asset` / `AssetPrice`            | Market-traded assets and their historical prices; `asset_type` can be changed inline from any holdings table |
| `ManualAsset` / `ManualValuation` | User-defined assets (e.g. real estate) with manual valuations                                                |
| `PortfolioSnapshot`               | Periodic snapshots of portfolio value over time                                                              |
| `BenchmarkPrice`                  | Daily close prices for benchmark tickers (SPY, BTC)                                                          |
| `JournalEntry`                    | Rich-text timestamped note attached to a portfolio for recording investment decisions                        |
| `WatchlistItem`                   | User-watched tickers with optional target price and notes                                                    |
| `ActivityLog`                     | Audit log of user actions                                                                                    |
| `LoginHistory`                    | Per-user login history (IP, user agent, timestamp)                                                           |
| `AppSetting`                      | Key-value application settings (e.g. `registration_open`)                                                    |

## Transaction Types

| Type             | Effect on holdings          |
| ---------------- | --------------------------- |
| `buy`            | Adds quantity and cost      |
| `sell`           | Reduces quantity and cost   |
| `transfer_in`    | Adds quantity and cost      |
| `transfer_out`   | Reduces quantity and cost   |
| `staking_reward` | Adds quantity and cost      |
| `dividend`       | Income only, no holdings    |

### Portfolio Transfers

Use the **Portfolio Transfer** button (dashboard or transactions list) to record a linked pair of `transfer_out` / `transfer_in` across two portfolios in a single step.

### Rebalancing Setup

1. Go to a portfolio → Edit
2. Set target percentages for Stocks %, Crypto %, and Manual Assets % (must sum to 100)
3. The portfolio page will show current vs. target allocation and recommended buy/sell amounts

## Ports

| Service     | Host port |
| ----------- | --------- |
| Nginx (app) | 8080      |
| MariaDB     | 3307      |
