<?php

namespace App\Services;

use App\Concerns\RetriesHttpRequests;
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

    public function search(string $query): Response
    {
        return $this->client(5)->get('https://finnhub.io/api/v1/search', [
            'q'     => $query,
            'token' => $this->apiKey,
        ]);
    }
}
