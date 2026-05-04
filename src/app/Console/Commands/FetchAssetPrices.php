<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchAssetPrices extends Command
{
    protected $signature = 'assets:fetch-prices';
    protected $description = 'Fetch latest prices for all tracked assets from CoinGecko (crypto) and Finnhub (stocks + real_estate)';

    public function handle(): int
    {
        $assets = Asset::whereHas('transactions')->get();

        if ($assets->isEmpty()) {
            $this->info('No assets with transactions found.');
            return self::SUCCESS;
        }

        $cryptos = $assets->where('asset_type', 'crypto');
        $stocks  = $assets->whereIn('asset_type', ['stock', 'real_estate']);

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
        $response = Http::timeout(15)->get('https://api.coingecko.com/api/v3/coins/markets', [
            'vs_currency' => 'usd',
            'order'       => 'market_cap_desc',
            'per_page'    => 250,
            'page'        => 1,
        ]);

        if (! $response->successful()) {
            Log::error('CoinGecko price fetch failed', ['status' => $response->status()]);
            $this->error('CoinGecko API error (HTTP ' . $response->status() . ')');
            return;
        }

        $coinMap = collect($response->json())->keyBy(fn ($c) => strtoupper($c['symbol']));
        $now     = now();

        foreach ($assets as $asset) {
            $coin = $coinMap->get(strtoupper($asset->symbol));

            if (! $coin) {
                $this->warn("  {$asset->symbol}: not found in top 250 coins — set coingecko_id manually if needed");
                continue;
            }

            if (! $asset->coingecko_id) {
                $asset->update(['coingecko_id' => $coin['id']]);
            }

            AssetPrice::create([
                'asset_id'    => $asset->id,
                'price'       => $coin['current_price'],
                'currency'    => 'USD',
                'recorded_at' => $now,
            ]);

            $this->line("  {$asset->symbol}: \${$coin['current_price']}");
        }
    }

    private function fetchStockPrices(Collection $assets): void
    {
        $apiKey = config('services.finnhub.key');

        if (! $apiKey) {
            $this->error('FINNHUB_API_KEY not set in .env — skipping stocks');
            return;
        }

        $now = now();

        foreach ($assets as $asset) {
            $ticker = $asset->polygon_ticker ?: $asset->symbol;

            $response = Http::timeout(10)->get('https://finnhub.io/api/v1/quote', [
                'symbol' => $ticker,
                'token'  => $apiKey,
            ]);

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

            AssetPrice::create([
                'asset_id'    => $asset->id,
                'price'       => $price,
                'currency'    => 'USD',
                'recorded_at' => $now,
            ]);

            $this->line("  {$asset->symbol}: \${$price}");

            usleep(200000); // 200ms between calls — free tier: 60/min
        }
    }
}
