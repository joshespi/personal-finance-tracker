<?php

namespace App\Services;

use App\Models\BenchmarkPrice;

class BenchmarkService
{
    public const TICKERS = ['SPY', 'BTC'];

    public function all(): array
    {
        $result = [];

        foreach (self::TICKERS as $ticker) {
            $prices = BenchmarkPrice::where('ticker', $ticker)
                ->orderBy('recorded_on')
                ->get(['recorded_on', 'close_price'])
                ->map(fn ($p) => ['date' => $p->recorded_on->toDateString(), 'price' => (float) $p->close_price])
                ->values()
                ->all();

            if (! empty($prices)) {
                $result[$ticker] = $prices;
            }
        }

        return $result;
    }
}
