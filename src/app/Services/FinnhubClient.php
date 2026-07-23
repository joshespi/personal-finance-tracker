<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class FinnhubClient
{
    private readonly ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = config('services.finnhub.key');
    }

    public function configured(): bool
    {
        return (bool) $this->apiKey;
    }

    /** Current quote for a ticker. 'c' in the response is the current price. */
    public function quote(string $symbol): Response
    {
        return Http::timeout(10)->retry(3, 200, throw: false)->get('https://finnhub.io/api/v1/quote', [
            'symbol' => $symbol,
            'token'  => $this->apiKey,
        ]);
    }

    /** Daily OHLC candles between two unix timestamps. */
    public function dailyCandles(string $symbol, int $from, int $to): Response
    {
        return Http::timeout(30)->retry(3, 200, throw: false)->get('https://finnhub.io/api/v1/stock/candle', [
            'symbol'     => $symbol,
            'resolution' => 'D',
            'from'       => $from,
            'to'         => $to,
            'token'      => $this->apiKey,
        ]);
    }

    public function search(string $query): Response
    {
        return Http::timeout(5)->retry(3, 200, throw: false)->get('https://finnhub.io/api/v1/search', [
            'q'     => $query,
            'token' => $this->apiKey,
        ]);
    }
}
