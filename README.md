# Portfolio Tracker

A Laravel application for tracking investment portfolios. Supports transactions, asset price fetching via Finnhub, manual assets with custom valuations, and periodic portfolio snapshots.

## Stack

- **PHP 8.5** / Laravel (FPM)
- **MariaDB 11.8**
- **Nginx**
- **Node 24** / Vite / Tailwind CSS
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

## Scheduled Commands

The following Artisan commands are intended to run on a schedule (configure via `app/Console/Kernel.php` or a cron job in the container):

| Command                   | Description                                                |
| ------------------------- | ---------------------------------------------------------- |
| `app:fetch-asset-prices`  | Fetches latest prices for tracked assets via Finnhub       |
| `app:snapshot-portfolios` | Records a point-in-time snapshot of each portfolio's value |

## Key Models

| Model                             | Description                                                   |
| --------------------------------- | ------------------------------------------------------------- |
| `Portfolio`                       | A named collection of assets belonging to a user              |
| `Transaction`                     | A buy/sell record for a tracked asset within a portfolio      |
| `Asset` / `AssetPrice`            | Market-traded assets and their historical prices              |
| `ManualAsset` / `ManualValuation` | User-defined assets (e.g. real estate) with manual valuations |
| `PortfolioSnapshot`               | Periodic snapshots of portfolio value over time               |

## Ports

| Service     | Host port |
| ----------- | --------- |
| Nginx (app) | 8080      |
| MariaDB     | 3306      |
