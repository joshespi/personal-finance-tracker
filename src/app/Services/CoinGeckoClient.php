<?php

namespace App\Services;

use App\Concerns\RetriesHttpRequests;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;

class CoinGeckoClient
{
    use RetriesHttpRequests;

    /** Top coins by market cap, with current prices. No API key needed on the free tier. */
    public function markets(int $perPage = 250, int $page = 1): Response
    {
        return $this->client(15)->get('https://api.coingecko.com/api/v3/coins/markets', [
            'vs_currency' => 'usd',
            'order'       => 'market_cap_desc',
            'per_page'    => $perPage,
            'page'        => $page,
        ]);
    }

    /** Daily price series for the trailing N days. */
    public function marketChart(string $coinId, int $days): Response
    {
        return $this->client(30)->get("https://api.coingecko.com/api/v3/coins/{$coinId}/market_chart", [
            'vs_currency' => 'usd',
            'days'        => $days,
            'interval'    => 'daily',
        ]);
    }

    /** Price series between two unix timestamps. */
    public function marketChartRange(string $coinId, int $from, int $to): Response
    {
        return $this->client(30)->get("https://api.coingecko.com/api/v3/coins/{$coinId}/market_chart/range", [
            'vs_currency' => 'usd',
            'from'        => $from,
            'to'          => $to,
        ]);
    }

    /**
     * Flattens a market_chart response's [ms-timestamp, price] pairs to one price per day,
     * keeping only days within [$from, $to] (Y-m-d) — CoinGecko pads ranges with the
     * adjacent day's point.
     *
     * @return array<string, float> Y-m-d => price, later points winning a same-day collision
     */
    public static function pricesByDate(Response $response, string $from, string $to): array
    {
        $byDate = [];

        foreach ($response->json('prices') ?? [] as [$ts, $price]) {
            $date = Carbon::createFromTimestampMs($ts)->toDateString();

            if ($date >= $from && $date <= $to) {
                $byDate[$date] = $price;
            }
        }

        return $byDate;
    }

    public function search(string $query): Response
    {
        return $this->client(5)->get('https://api.coingecko.com/api/v3/search', [
            'query' => $query,
        ]);
    }
}
