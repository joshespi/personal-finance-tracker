<?php

namespace App\Services;

use App\Enums\PriceFetchOutcome;
use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\AssetPrice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class HistoricalPriceFetchService
{
    public function __construct(
        private FinnhubClient $finnhub,
        private CoinGeckoClient $coinGecko,
    ) {}

    /**
     * Fetch prices for a batch of assets, honoring provider rate limits: once
     * a provider returns 429, the remaining assets sharing that provider are
     * deferred without being requested again this run. $onResult is invoked
     * with (Asset, PriceSource, result) after every attempted fetch so
     * callers can log/report without duplicating the rate-limit bookkeeping.
     *
     * @param  Collection<int, Asset>  $assets
     * @return array{deferred: Collection<int, Asset>}
     */
    public function fetchBatch(Collection $assets, Carbon $from, Carbon $to, callable $onResult): array
    {
        $limitedProviders = [];
        $deferred         = collect();

        foreach ($assets as $asset) {
            $source = $asset->effectivePriceSource();

            if (in_array($source, $limitedProviders, true)) {
                $deferred->push($asset);

                continue;
            }

            $result = $this->fetchForAsset($asset, $from, $to);

            if ($result['outcome'] === PriceFetchOutcome::RateLimited) {
                $limitedProviders[] = $source;
                $deferred->push($asset);
            } else {
                $this->throttle($source);
            }

            $onResult($asset, $source, $result);
        }

        return ['deferred' => $deferred];
    }

    /** @return array{outcome: PriceFetchOutcome, count: int, message: ?string} */
    public function fetchForAsset(Asset $asset, Carbon $from, Carbon $to): array
    {
        return $asset->effectivePriceSource() === PriceSource::Finnhub
            ? $this->fetchFinnhub($asset, $from, $to)
            : $this->fetchCoinGecko($asset, $from, $to);
    }

    private function throttle(PriceSource $source): void
    {
        if ($source === PriceSource::Finnhub) {
            usleep(500_000); // 500ms — Finnhub free tier: 60 req/min
        } else {
            sleep(2); // CoinGecko free tier is more restrictive
        }
    }

    private function fetchFinnhub(Asset $asset, Carbon $from, Carbon $to): array
    {
        if (! $this->finnhub->configured()) {
            return ['outcome' => PriceFetchOutcome::NoData, 'count' => 0, 'message' => 'FINNHUB_API_KEY not set'];
        }

        $ticker = $asset->polygon_ticker ?: $asset->symbol;

        $response = $this->finnhub->dailyCandles($ticker, $from->timestamp, $to->timestamp);

        if ($response->status() === 429) {
            return ['outcome' => PriceFetchOutcome::RateLimited, 'count' => 0, 'message' => 'rate-limited (HTTP 429)'];
        }

        if (! FinnhubClient::hasCandles($response)) {
            Log::warning("Backfill: no Finnhub candles for {$asset->symbol}");

            return ['outcome' => PriceFetchOutcome::NoData, 'count' => 0, 'message' => "no data (HTTP {$response->status()})"];
        }

        return $this->storePrices($asset, FinnhubClient::closesByDate($response));
    }

    private function fetchCoinGecko(Asset $asset, Carbon $from, Carbon $to): array
    {
        $coingeckoId = $asset->coingecko_id ?? strtolower($asset->symbol);

        $response = $this->coinGecko->marketChartRange($coingeckoId, $from->timestamp, $to->timestamp);

        if ($response->status() === 429) {
            return ['outcome' => PriceFetchOutcome::RateLimited, 'count' => 0, 'message' => 'rate-limited (HTTP 429)'];
        }

        if (! $response->successful()) {
            Log::warning("Backfill: no CoinGecko data for {$asset->symbol}");

            return ['outcome' => PriceFetchOutcome::NoData, 'count' => 0, 'message' => "no data (HTTP {$response->status()})"];
        }

        return $this->storePrices($asset, CoinGeckoClient::pricesByDate($response, $from->toDateString(), $to->toDateString()));
    }

    /**
     * Upserts one noon-stamped daily price per date and builds the Fetched result.
     *
     * @param  array<string, float>  $pricesByDate  Y-m-d => price
     */
    private function storePrices(Asset $asset, array $pricesByDate): array
    {
        $rows = [];
        foreach ($pricesByDate as $date => $price) {
            $rows[] = ['asset_id' => $asset->id, 'recorded_at' => $date.' 12:00:00', 'price' => $price, 'currency' => 'USD'];
        }

        if ($rows !== []) {
            AssetPrice::upsert($rows, ['asset_id', 'recorded_at'], ['price', 'currency']);
        }

        return ['outcome' => PriceFetchOutcome::Fetched, 'count' => count($rows), 'message' => null];
    }
}
