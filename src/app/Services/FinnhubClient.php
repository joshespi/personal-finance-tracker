<?php

namespace App\Services;

use App\Concerns\RetriesHttpRequests;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;

class FinnhubClient
{
    use RetriesHttpRequests;

    private readonly ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.finnhub.key');
    }

    public function configured(): bool
    {
        return (bool) $this->apiKey;
    }

    /**
     * Strips the API token from a URL/exception message before logging. The token travels
     * as a query param on every request, so a connection-failure exception message (which
     * embeds the full request URI) would otherwise leak it into application logs.
     */
    public static function redact(string $message): string
    {
        return preg_replace('/token=[^&\s]+/', 'token=***', $message);
    }

    /** Current quote for a ticker. 'c' in the response is the current price. */
    public function quote(string $symbol): Response
    {
        return $this->client(10)->get('https://finnhub.io/api/v1/quote', [
            'symbol' => $symbol,
            'token'  => $this->apiKey,
        ]);
    }

    /** Daily OHLC candles between two unix timestamps. */
    public function dailyCandles(string $symbol, int $from, int $to): Response
    {
        return $this->client(30)->get('https://finnhub.io/api/v1/stock/candle', [
            'symbol'     => $symbol,
            'resolution' => 'D',
            'from'       => $from,
            'to'         => $to,
            'token'      => $this->apiKey,
        ]);
    }

    /** Whether a dailyCandles() response carries candles (Finnhub answers 200 with s=no_data for an empty range). */
    public static function hasCandles(Response $response): bool
    {
        return $response->successful() && ($response->json('s') ?? 'no_data') !== 'no_data';
    }

    /** @return array<string, float> Y-m-d => close price, later candles winning a same-day collision */
    public static function closesByDate(Response $response): array
    {
        $closes = $response->json('c') ?? [];
        $byDate = [];

        foreach ($response->json('t') ?? [] as $i => $ts) {
            $byDate[Carbon::createFromTimestamp($ts)->toDateString()] = $closes[$i];
        }

        return $byDate;
    }

    public function search(string $query): Response
    {
        return $this->client(5)->get('https://finnhub.io/api/v1/search', [
            'q'     => $query,
            'token' => $this->apiKey,
        ]);
    }
}
