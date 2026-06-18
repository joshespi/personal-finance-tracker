<?php

namespace App\Console\Commands;

use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackfillPortfolioSnapshots extends Command
{
    protected $signature = 'portfolios:backfill-snapshots
                            {--portfolio=  : Comma-separated portfolio IDs; all if omitted}
                            {--from=       : Start date Y-m-d; defaults to earliest transaction}
                            {--to=         : End date Y-m-d; defaults to yesterday}
                            {--skip-fetch  : Skip API calls; use existing AssetPrice records}
                            {--dry-run     : Preview without writing to the database}';

    protected $description = 'Backfill portfolio_snapshots for past dates using historical asset prices';

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $skipFetch = $this->option('skip-fetch');

        $portfolios = $this->resolvePortfolios();
        if ($portfolios->isEmpty()) {
            $this->warn('No portfolios found.');
            return self::SUCCESS;
        }

        $portfolios->load([
            'transactions.asset',
            'manualAssets.valuations',
            'manualAssets.proxyAsset',
        ]);

        $from = $this->resolveFrom($portfolios);
        $to   = $this->resolveTo();

        if ($from->gt($to)) {
            $this->warn('No date range to backfill (from > to).');
            return self::SUCCESS;
        }

        $this->info("Range: {$from->toDateString()} → {$to->toDateString()}, {$portfolios->count()} portfolio(s)");

        $positionAssets = $portfolios
            ->flatMap(fn ($p) => $p->transactions->map(fn ($t) => $t->asset))
            ->filter()
            ->unique('id')
            ->values();

        $proxyAssets = $portfolios
            ->flatMap(fn ($p) => $p->manualAssets
                ->filter(fn ($ma) => $ma->tracking_method === 'proxy_ticker' && $ma->proxyAsset)
                ->map(fn ($ma) => $ma->proxyAsset)
            )
            ->unique('id')
            ->values();

        $allAssets = $positionAssets->concat($proxyAssets)->unique('id')->values();

        if (! $skipFetch) {
            $this->fetchHistoricalPrices($allAssets, $from, $to);
        }

        // Buffer 7 days before $from so weekend lookback finds Friday prices
        $loadFrom = $from->copy()->subDays(7);
        $assetIds = $allAssets->pluck('id');

        $pricesByAssetAndDate = AssetPrice::whereIn('asset_id', $assetIds)
            ->where('recorded_at', '>=', $loadFrom->toDateString())
            ->where('recorded_at', '<=', $to->copy()->addDay()->toDateString())
            ->get()
            ->groupBy('asset_id')
            ->map(fn ($prices) => $prices
                ->groupBy(fn ($p) => Carbon::parse($p->recorded_at)->toDateString())
                ->map(fn ($day) => $day->sortByDesc('recorded_at')->first())
            );

        $period  = CarbonPeriod::create($from, $to);
        $written = 0;

        // Chart-eligible manual assets don't change across the date range — filter once per portfolio.
        $chartManualAssets = $portfolios->mapWithKeys(fn ($p) => [
            $p->id => $p->manualAssets->where('include_in_chart', true)->values(),
        ]);

        foreach ($period as $date) {
            $dateStr = $date->toDateString();

            foreach ($portfolios as $portfolio) {
                $txnsAsOf = $portfolio->transactions->filter(
                    fn ($t) => $t->transacted_at->toDateString() <= $dateStr
                );

                [$costBasis, $marketValue] = $this->computeHoldingsAsOf(
                    $txnsAsOf, $pricesByAssetAndDate, $dateStr
                );

                $manualValue = $this->computeManualValueAsOf(
                    $chartManualAssets[$portfolio->id],
                    $pricesByAssetAndDate,
                    $dateStr
                );

                if ($dryRun) {
                    $total = round($marketValue + $manualValue, 2);
                    $this->line("  [{$dateStr}] {$portfolio->name}: \${$total}");
                } else {
                    PortfolioSnapshot::updateOrCreate(
                        ['portfolio_id' => $portfolio->id, 'recorded_on' => $dateStr],
                        [
                            'cost_basis'   => $costBasis,
                            'market_value' => $marketValue,
                            'manual_value' => $manualValue,
                        ]
                    );
                    $written++;
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry run — no snapshots written.');
        } else {
            $this->info("Done. Wrote/updated {$written} snapshot(s).");
        }

        return self::SUCCESS;
    }

    private function resolvePortfolios(): Collection
    {
        $ids = $this->option('portfolio');
        if ($ids) {
            $idList = array_map('intval', explode(',', $ids));
            return Portfolio::whereIn('id', $idList)->get();
        }
        return Portfolio::all();
    }

    private function resolveFrom(Collection $portfolios): Carbon
    {
        if ($this->option('from')) {
            return Carbon::parse($this->option('from'))->startOfDay();
        }

        $earliest = $portfolios
            ->flatMap(fn ($p) => $p->transactions)
            ->map(fn ($t) => $t->transacted_at->toDateString())
            ->filter()
            ->min();

        return $earliest
            ? Carbon::parse($earliest)->startOfDay()
            : now()->subYear()->startOfDay();
    }

    private function resolveTo(): Carbon
    {
        if ($this->option('to')) {
            return Carbon::parse($this->option('to'))->startOfDay();
        }
        return now()->subDay()->startOfDay();
    }

    private function fetchHistoricalPrices(Collection $assets, Carbon $from, Carbon $to): void
    {
        $apiKey = config('services.finnhub.key');
        $this->line('Fetching historical prices...');

        foreach ($assets as $asset) {
            if ($asset->effectivePriceSource() === PriceSource::Finnhub) {
                $this->fetchFinnhubCandles($asset, $from, $to, $apiKey);
                usleep(500_000); // 500ms — Finnhub free tier: 60 req/min
            } else {
                $this->fetchCoinGeckoRange($asset, $from, $to);
                sleep(2); // CoinGecko free tier is more restrictive
            }
        }
    }

    private function fetchFinnhubCandles(Asset $asset, Carbon $from, Carbon $to, ?string $apiKey): void
    {
        if (! $apiKey) {
            $this->warn("  {$asset->symbol}: FINNHUB_API_KEY not set, skipping");
            return;
        }

        $ticker = $asset->polygon_ticker ?: $asset->symbol;
        $this->line("  {$asset->symbol} (Finnhub)...");

        $response = Http::timeout(30)->get('https://finnhub.io/api/v1/stock/candle', [
            'symbol'     => $ticker,
            'resolution' => 'D',
            'from'       => $from->timestamp,
            'to'         => $to->timestamp,
            'token'      => $apiKey,
        ]);

        if (! $response->successful() || ($response->json('s') ?? 'no_data') === 'no_data') {
            $this->warn("  {$asset->symbol}: no data (HTTP {$response->status()})");
            Log::warning("Backfill: no Finnhub candles for {$asset->symbol}");
            return;
        }

        $timestamps = $response->json('t') ?? [];
        $closes     = $response->json('c') ?? [];
        $count      = 0;

        foreach ($timestamps as $i => $ts) {
            $recordedAt = Carbon::createFromTimestamp($ts)->toDateString() . ' 12:00:00';
            AssetPrice::updateOrCreate(
                ['asset_id' => $asset->id, 'recorded_at' => $recordedAt],
                ['price' => $closes[$i], 'currency' => 'USD']
            );
            $count++;
        }

        $this->line("    → {$count} day(s)");
    }

    private function fetchCoinGeckoRange(Asset $asset, Carbon $from, Carbon $to): void
    {
        $coingeckoId = $asset->coingecko_id ?? strtolower($asset->symbol);
        $this->line("  {$asset->symbol} (CoinGecko, id={$coingeckoId})...");

        $response = Http::timeout(30)->get(
            "https://api.coingecko.com/api/v3/coins/{$coingeckoId}/market_chart/range",
            [
                'vs_currency' => 'usd',
                'from'        => $from->timestamp,
                'to'          => $to->timestamp,
            ]
        );

        if (! $response->successful()) {
            $this->warn("  {$asset->symbol}: no data (HTTP {$response->status()})");
            Log::warning("Backfill: no CoinGecko data for {$asset->symbol}");
            return;
        }

        $prices = $response->json('prices') ?? [];
        $count  = 0;

        foreach ($prices as [$ts, $price]) {
            $date = Carbon::createFromTimestampMs($ts)->toDateString();
            if ($date < $from->toDateString() || $date > $to->toDateString()) {
                continue;
            }
            AssetPrice::updateOrCreate(
                ['asset_id' => $asset->id, 'recorded_at' => $date . ' 12:00:00'],
                ['price' => $price, 'currency' => 'USD']
            );
            $count++;
        }

        $this->line("    → {$count} day(s)");
    }

    /** @return array{0: float, 1: float} [cost_basis, market_value] */
    private function computeHoldingsAsOf(Collection $transactions, Collection $pricesByAssetAndDate, string $date): array
    {
        $costBasis   = 0.0;
        $marketValue = 0.0;

        $groups = $transactions
            ->filter(fn ($t) => in_array($t->type, Transaction::POSITION_TYPES))
            ->groupBy('asset_id');

        foreach ($groups as $assetId => $txns) {
            $totalQty  = 0.0;
            $totalCost = 0.0;

            foreach ($txns->sortBy('transacted_at') as $t) {
                $qty = (float) $t->quantity;
                if (in_array($t->type, Transaction::INFLOW_TYPES)) {
                    $usdFee     = $t->fee_in_asset ? 0.0 : (float) $t->fees;
                    $totalCost += $qty * (float) $t->price_per_unit + $usdFee;
                    $totalQty  += $qty;
                } elseif (in_array($t->type, Transaction::OUTFLOW_TYPES)) {
                    $deduct = $t->fee_in_asset ? $qty + (float) $t->fees : $qty;
                    if ($totalQty > 0) {
                        $totalCost -= ($totalCost / $totalQty) * min($deduct, $totalQty);
                    }
                    $totalQty -= $deduct;
                }
            }

            $totalQty  = max(0.0, round($totalQty, 8));
            $totalCost = max(0.0, $totalCost);
            $costBasis += $totalCost;

            $price        = $this->closestPrice($pricesByAssetAndDate->get($assetId, collect()), $date);
            $marketValue += $price !== null ? round($totalQty * $price, 2) : $totalCost;
        }

        return [round($costBasis, 2), round($marketValue, 2)];
    }

    private function computeManualValueAsOf(Collection $manualAssets, Collection $pricesByAssetAndDate, string $date): float
    {
        $total = 0.0;

        foreach ($manualAssets as $ma) {
            if ($ma->tracking_method === 'proxy_ticker') {
                $anchorDate = $ma->anchor_date?->toDateString();
                if (! $anchorDate || $anchorDate > $date) {
                    continue;
                }
                $price = $this->closestPrice($pricesByAssetAndDate->get($ma->proxy_asset_id, collect()), $date);

                if ($ma->anchor_synthetic_shares !== null) {
                    $shares = (float) $ma->anchor_synthetic_shares;
                } else {
                    // anchor_synthetic_shares wasn't computed at save time (proxy price unavailable then);
                    // derive it now from the anchor-date price so the backfill scales correctly.
                    $anchorPrice = $this->closestPrice($pricesByAssetAndDate->get($ma->proxy_asset_id, collect()), $anchorDate);
                    $shares      = ($anchorPrice && $anchorPrice > 0) ? (float) $ma->anchor_value / $anchorPrice : 0;
                }

                $total += ($price !== null && $shares > 0)
                    ? round($shares * $price, 2)
                    : (float) ($ma->anchor_value ?? 0);
            } else {
                $val = $ma->valuations
                    ->filter(fn ($v) => $v->valued_at->toDateString() <= $date)
                    ->sortByDesc('valued_at')
                    ->first();
                if ($val) {
                    $total += (float) $val->value;
                }
            }
        }

        return round($total, 2);
    }

    private function closestPrice(Collection $pricesByDate, string $date): ?float
    {
        $closest = $pricesByDate->keys()
            ->filter(fn ($d) => $d <= $date)
            ->sort()
            ->last();

        return $closest !== null ? (float) $pricesByDate[$closest]->price : null;
    }
}
