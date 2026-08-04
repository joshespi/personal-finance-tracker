<?php

namespace App\Concerns;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Shared retrying-HTTP-client builder for market-data providers (Finnhub, CoinGecko).
 */
trait RetriesHttpRequests
{
    /**
     * A retrying client — except a 429 is never retried. Without this, retry(3, ...)'s
     * default "retry any non-2xx response" behavior means a single rate-limited call
     * actually fires 3 requests against a provider that just told us to back off,
     * defeating the point of HistoricalPriceFetchService/FetchAssetPrices stopping
     * immediately on the first 429 — they only ever see the *last* attempt's status.
     */
    private function client(int $timeoutSeconds): PendingRequest
    {
        return Http::timeout($timeoutSeconds)->retry(3, 200, when: fn ($e) => ! ($e instanceof RequestException && $e->response->status() === 429), throw: false);
    }
}
