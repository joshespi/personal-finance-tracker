<?php

namespace App\Services;

use App\Models\BenchmarkPrice;
use Illuminate\Support\Facades\Cache;

class BenchmarkService
{
    public const TICKERS = ['SPY', 'BTC'];

    public const CACHE_KEY = 'benchmark-prices.series';

    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHour(), fn () => $this->build());
    }

    private function build(): array
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
