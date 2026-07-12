<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class CoinGeckoClient
{
    /** Top coins by market cap, with current prices. No API key needed on the free tier. */
    public function markets(int $perPage = 250, int $page = 1): Response
    {
        return Http::timeout(15)->get('https://api.coingecko.com/api/v3/coins/markets', [
            'vs_currency' => 'usd',
            'order'       => 'market_cap_desc',
            'per_page'    => $perPage,
            'page'        => $page,
        ]);
    }

    /** Daily price series for the trailing N days. */
    public function marketChart(string $coinId, int $days): Response
    {
        return Http::timeout(30)->get("https://api.coingecko.com/api/v3/coins/{$coinId}/market_chart", [
            'vs_currency' => 'usd',
            'days'        => $days,
            'interval'    => 'daily',
        ]);
    }

    /** Price series between two unix timestamps. */
    public function marketChartRange(string $coinId, int $from, int $to): Response
    {
        return Http::timeout(30)->get("https://api.coingecko.com/api/v3/coins/{$coinId}/market_chart/range", [
            'vs_currency' => 'usd',
            'from'        => $from,
            'to'          => $to,
        ]);
    }

    public function search(string $query): Response
    {
        return Http::timeout(5)->get('https://api.coingecko.com/api/v3/search', [
            'query' => $query,
        ]);
    }
}
