<?php

namespace App\Console\Commands;

use App\Models\BenchmarkPrice;
use App\Services\BenchmarkService;
use App\Services\CoinGeckoClient;
use App\Services\FinnhubClient;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FetchBenchmarkPrices extends Command
{
    protected $signature = 'benchmarks:fetch {--from= : Start date (Y-m-d), defaults to 10 years ago} {--to= : End date (Y-m-d), defaults to today}';

    protected $description = 'Fetch historical benchmark prices (SPY, BTC) from Finnhub and CoinGecko';

    private const STOCK_BENCHMARKS = ['SPY'];

    private const CRYPTO_BENCHMARKS = ['BTC'];

    public function __construct(
        private readonly FinnhubClient $finnhub,
        private readonly CoinGeckoClient $coinGecko,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : now()->subYears(10);

        $to = $this->option('to')
            ? Carbon::parse($this->option('to'))
            : now();

        $this->info("Fetching benchmark prices from {$from->toDateString()} to {$to->toDateString()}");

        foreach (self::STOCK_BENCHMARKS as $ticker) {
            $this->fetchStockCandles($ticker, $from, $to);
        }

        foreach (self::CRYPTO_BENCHMARKS as $ticker) {
            $this->fetchCryptoCandles($ticker, $from, $to);
        }

        Cache::forget(BenchmarkService::CACHE_KEY);

        $this->info('Done.');

        return self::SUCCESS;
    }

    private function fetchStockCandles(string $ticker, Carbon $from, Carbon $to): void
    {
        if (! $this->finnhub->configured()) {
            $this->warn("FINNHUB_API_KEY not set — skipping {$ticker}");

            return;
        }

        $this->line("Fetching {$ticker} (Finnhub)...");

        // A connection-level failure throws past retry(..., throw: false) (that flag only
        // covers non-2xx responses) — isolate it so one benchmark's outage doesn't abort
        // the other benchmark's fetch called right after this in handle().
        try {
            $response = $this->finnhub->dailyCandles($ticker, $from->timestamp, $to->timestamp);
        } catch (ConnectionException $e) {
            $this->error("Failed to fetch {$ticker}: connection failed");
            Log::error("Benchmark fetch: connection failed for {$ticker}", ['message' => FinnhubClient::redact($e->getMessage())]);

            return;
        }

        if (! FinnhubClient::hasCandles($response)) {
            $this->error("Failed to fetch {$ticker}: ".$response->status());
            Log::error("Benchmark fetch failed for {$ticker}", ['status' => $response->status()]);

            return;
        }

        $this->storeCloses($ticker, FinnhubClient::closesByDate($response));
    }

    private function fetchCryptoCandles(string $ticker, Carbon $from, Carbon $to): void
    {
        $this->line("Fetching {$ticker} (CoinGecko)...");

        $coingeckoId = match (strtoupper($ticker)) {
            'BTC'   => 'bitcoin',
            'ETH'   => 'ethereum',
            default => strtolower($ticker),
        };

        $days = $from->diffInDays($to);
        $days = max(1, min($days, 3650));

        try {
            $response = $this->coinGecko->marketChart($coingeckoId, $days);
        } catch (ConnectionException $e) {
            $this->error("Failed to fetch {$ticker}: connection failed");
            Log::error("Benchmark fetch: connection failed for {$ticker}", ['message' => $e->getMessage()]);

            return;
        }

        if (! $response->successful()) {
            $this->error("Failed to fetch {$ticker}: ".$response->status());
            Log::error("Benchmark fetch failed for {$ticker}", ['status' => $response->status()]);

            return;
        }

        $this->storeCloses($ticker, CoinGeckoClient::pricesByDate($response, $from->toDateString(), $to->toDateString()));
    }

    /** @param  array<string, float>  $closesByDate  Y-m-d => close price */
    private function storeCloses(string $ticker, array $closesByDate): void
    {
        $rows = [];
        foreach ($closesByDate as $date => $close) {
            $rows[] = ['ticker' => $ticker, 'recorded_on' => $date, 'close_price' => $close];
        }

        $this->storeRows($rows);

        $this->line("  {$ticker}: stored ".count($rows).' days');
    }

    private function storeRows(array $rows): void
    {
        foreach (array_chunk(array_values($rows), 500) as $chunk) {
            BenchmarkPrice::upsert($chunk, ['ticker', 'recorded_on'], ['close_price']);
        }
    }
}
