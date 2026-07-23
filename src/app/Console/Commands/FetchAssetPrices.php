<?php

namespace App\Console\Commands;

use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\ManualAsset;
use App\Services\CoinGeckoClient;
use App\Services\FinnhubClient;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FetchAssetPrices extends Command
{
    protected $signature = 'assets:fetch-prices';

    protected $description = 'Fetch latest prices for all tracked assets from CoinGecko (crypto) and Finnhub (stocks, bonds + real estate)';

    public function __construct(
        private readonly FinnhubClient $finnhub,
        private readonly CoinGeckoClient $coinGecko,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $proxyIds = ManualAsset::whereNotNull('proxy_asset_id')->pluck('proxy_asset_id');

        $assets = Asset::where(function ($q) use ($proxyIds) {
            $q->whereHas('transactions')->orWhereIn('id', $proxyIds);
        })->get();

        if ($assets->isEmpty()) {
            $this->info('No assets with transactions found.');

            return self::SUCCESS;
        }

        $cryptos = $assets->filter(fn ($a) => $a->effectivePriceSource() === PriceSource::CoinGecko);
        $stocks  = $assets->filter(fn ($a) => $a->effectivePriceSource() === PriceSource::Finnhub);

        $this->info('Fetching prices...');

        if ($cryptos->isNotEmpty()) {
            $this->line('');
            $this->line('Crypto (CoinGecko)');
            $this->fetchCryptoPrices($cryptos);
        }

        if ($stocks->isNotEmpty()) {
            $this->line('');
            $this->line('Stocks (Finnhub)');
            $this->fetchStockPrices($stocks);
        }

        $this->line('');
        $this->info('Done.');

        return self::SUCCESS;
    }

    private function fetchCryptoPrices(Collection $assets): void
    {
        $response = $this->coinGecko->markets();

        if (! $response->successful()) {
            Log::error('CoinGecko price fetch failed', ['status' => $response->status()]);
            $this->error('CoinGecko API error (HTTP '.$response->status().')');

            return;
        }

        $coinMap     = collect($response->json())->keyBy(fn ($c) => strtoupper($c['symbol']));
        $now         = now();
        $newGeckoIds = [];
        $rows        = [];

        foreach ($assets as $asset) {
            $coin = $coinMap->get(strtoupper($asset->symbol));

            if (! $coin) {
                $this->warn("  {$asset->symbol}: not found in top 250 coins — set coingecko_id manually if needed");

                continue;
            }

            if (! $asset->coingecko_id) {
                $newGeckoIds[] = ['id' => $asset->id, 'coingecko_id' => $coin['id']];
            }

            $rows[] = [
                'asset_id'    => $asset->id,
                'price'       => $coin['current_price'],
                'currency'    => 'USD',
                'recorded_at' => $now,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            $this->line("  {$asset->symbol}: \${$coin['current_price']}");
        }

        if ($rows !== []) {
            AssetPrice::insert($rows);
        }

        if ($newGeckoIds !== []) {
            Asset::upsert($newGeckoIds, ['id'], ['coingecko_id']);
        }
    }

    private function fetchStockPrices(Collection $assets): void
    {
        if (! $this->finnhub->configured()) {
            $this->error('FINNHUB_API_KEY not set in .env — skipping stocks');

            return;
        }

        $now  = now();
        $rows = [];

        foreach ($assets as $asset) {
            $ticker = $asset->polygon_ticker ?: $asset->symbol;

            $response = $this->finnhub->quote($ticker);

            // The client already retries transient failures; a surviving 429 means we're
            // sustainedly over the free-tier limit, so stop rather than hammer the rest away.
            if ($response->status() === 429) {
                $this->warn("  {$asset->symbol}: rate limited (HTTP 429) — stopping this run");

                break;
            }

            if (! $response->successful()) {
                $this->warn("  {$asset->symbol}: request failed (HTTP {$response->status()})");

                continue;
            }

            $data  = $response->json();
            $price = $data['c'] ?? null; // 'c' = current price

            if (! $price || $price <= 0) {
                $this->warn("  {$asset->symbol}: no valid price returned");

                continue;
            }

            $rows[] = [
                'asset_id'    => $asset->id,
                'price'       => $price,
                'currency'    => 'USD',
                'recorded_at' => $now,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];

            $this->line("  {$asset->symbol}: \${$price}");

            usleep(1000000); // 1s between calls — free tier: 60/min
        }

        if ($rows !== []) {
            AssetPrice::insert($rows);
        }
    }
}
