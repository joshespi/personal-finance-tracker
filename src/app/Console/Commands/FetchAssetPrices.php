<?php

namespace App\Console\Commands;

use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\ManualAsset;
use App\Services\CoinGeckoClient;
use App\Services\FinnhubClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
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
        // A genuine connection failure (timeout/DNS/refused) throws past retry(..., throw:
        // false) — that flag only suppresses the exception for a non-2xx *response*, not a
        // transport-level failure. Catch it here so a CoinGecko outage doesn't also skip the
        // stocks fetch that runs after this in handle().
        try {
            $response = $this->coinGecko->markets();
        } catch (ConnectionException $e) {
            Log::error('CoinGecko price fetch: connection failed', ['message' => $e->getMessage()]);
            $this->error('CoinGecko connection failed: '.$e->getMessage());

            return;
        }

        if (! $response->successful()) {
            Log::error('CoinGecko price fetch failed', ['status' => $response->status()]);
            $this->error('CoinGecko API error (HTTP '.$response->status().')');

            return;
        }

        $coins = collect($response->json());

        // Two lookups, deliberately in this order. coingecko_id is the provider's own unique
        // key and is what the symbol match below records the first time it succeeds; symbols
        // are *not* unique across the top 250 (several tokens share a ticker) and keyBy keeps
        // only the last of a collision, so resolving by symbol alone can lock an asset onto
        // the wrong coin's price forever. Once an id is stored, it wins.
        $byGeckoId = $coins->keyBy('id');
        $bySymbol  = $coins->keyBy(fn ($c) => strtoupper($c['symbol']));

        $now  = now();
        $rows = [];

        foreach ($assets as $asset) {
            $coin = $asset->coingecko_id
                ? $byGeckoId->get($asset->coingecko_id)
                : $bySymbol->get(strtoupper($asset->symbol));

            if (! $coin) {
                $this->warn("  {$asset->symbol}: not found in top 250 coins — check coingecko_id if set, or set it manually");
                Log::warning('CoinGecko price fetch: symbol not found', ['symbol' => $asset->symbol]);

                continue;
            }

            if (! $asset->coingecko_id) {
                // Record the provider's unique id so the next run resolves by id, not symbol.
                // Plain update(), not upsert(): upsert() compiles to INSERT ... ON CONFLICT and
                // a payload of just id + coingecko_id trips the NOT NULL on assets.symbol under
                // SQLite before the conflict clause is reached. The row already exists — it was
                // read above — so there is nothing to insert.
                Asset::whereKey($asset->id)->update(['coingecko_id' => $coin['id']]);
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
    }

    private function fetchStockPrices(Collection $assets): void
    {
        if (! $this->finnhub->configured()) {
            $this->error('FINNHUB_API_KEY not set in .env — skipping stocks');

            return;
        }

        $now  = now();
        $rows = [];
        $last = $assets->last();

        foreach ($assets as $asset) {
            $ticker = $asset->polygon_ticker ?: $asset->symbol;

            // A connection-level failure (timeout/DNS/refused) throws past retry(...,
            // throw: false) — that flag only covers non-2xx *responses*. Isolate it per
            // asset so one flaky call doesn't abort every asset later in this loop.
            try {
                $response = $this->finnhub->quote($ticker);
            } catch (ConnectionException $e) {
                $message = FinnhubClient::redact($e->getMessage());
                $this->warn("  {$asset->symbol}: connection failed — {$message}");
                Log::warning('Finnhub price fetch: connection failed', ['symbol' => $asset->symbol, 'message' => $message]);

                continue;
            }

            // The client already retries transient failures; a surviving 429 means we're
            // sustainedly over the free-tier limit, so stop rather than hammer the rest away.
            if ($response->status() === 429) {
                $this->warn("  {$asset->symbol}: rate limited (HTTP 429) — stopping this run");
                Log::warning('Finnhub price fetch: rate limited, stopping run', ['symbol' => $asset->symbol]);

                break;
            }

            if (! $response->successful()) {
                $this->warn("  {$asset->symbol}: request failed (HTTP {$response->status()})");
                Log::warning('Finnhub price fetch failed', ['symbol' => $asset->symbol, 'status' => $response->status()]);

                continue;
            }

            $data  = $response->json();
            $price = $data['c'] ?? null; // 'c' = current price

            if (! $price || $price <= 0) {
                $this->warn("  {$asset->symbol}: no valid price returned");
                Log::warning('Finnhub price fetch: no valid price', ['symbol' => $asset->symbol]);

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

            // 1s between calls — free tier: 60/min. No call follows the last asset, so
            // sleeping after it was a free second on every run.
            if (! $asset->is($last)) {
                usleep(1000000);
            }
        }

        if ($rows !== []) {
            AssetPrice::insert($rows);
        }
    }
}
