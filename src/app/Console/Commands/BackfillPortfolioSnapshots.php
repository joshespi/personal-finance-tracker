<?php

namespace App\Console\Commands;

use App\Enums\BackfillStatus;
use App\Enums\PriceFetchOutcome;
use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BackfillRequest;
use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use App\Services\HistoricalPriceFetchService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackfillPortfolioSnapshots extends Command
{
    protected $signature = 'portfolios:backfill-snapshots
                            {--portfolio=  : Comma-separated portfolio IDs; all if omitted}
                            {--from=       : Start date Y-m-d; defaults to earliest transaction}
                            {--to=         : End date Y-m-d; defaults to yesterday}
                            {--skip-fetch  : Skip API calls; use existing AssetPrice records}
                            {--dry-run     : Preview without writing to the database}
                            {--queue       : Queue price fetching instead of running it inline (drained hourly by assets:process-backfill-queue)}';

    protected $description = 'Backfill portfolio_snapshots for past dates using historical asset prices';

    public function __construct(private HistoricalPriceFetchService $priceFetchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun    = $this->option('dry-run');
        $skipFetch = $this->option('skip-fetch');
        $queue     = $this->option('queue') && ! $dryRun;

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
            if ($queue) {
                $this->queueBackfill($portfolios, $allAssets, $from, $to);

                return self::SUCCESS;
            }

            $deferred = $this->fetchHistoricalPrices($allAssets, $from, $to);
            if ($deferred->isNotEmpty()) {
                $this->queueBackfill($portfolios, $deferred, $from, $to, 'Deferred after hitting a provider rate limit');
            }
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

    /** @return Collection<int, Asset> assets whose fetch was deferred due to a rate limit */
    private function fetchHistoricalPrices(Collection $assets, Carbon $from, Carbon $to): Collection
    {
        $this->line('Fetching historical prices...');

        $result = $this->priceFetchService->fetchBatch($assets, $from, $to,
            function (Asset $asset, PriceSource $source, array $r) {
                if ($r['outcome'] === PriceFetchOutcome::RateLimited) {
                    $this->warn("  {$asset->symbol}: {$r['message']} — deferring remaining {$source->label()} assets to a queued backfill");
                } elseif ($r['outcome'] === PriceFetchOutcome::NoData) {
                    $this->warn("  {$asset->symbol}: {$r['message']}");
                } else {
                    $this->line("  {$asset->symbol} ({$source->label()}): {$r['count']} day(s)");
                }
            }
        );

        return $result['deferred'];
    }

    private function queueBackfill(Collection $portfolios, Collection $assets, Carbon $from, Carbon $to, ?string $note = null): void
    {
        if ($assets->isEmpty()) {
            return;
        }

        $request = BackfillRequest::create([
            'portfolio_ids'     => $portfolios->pluck('id')->all(),
            'from_date'         => $from->toDateString(),
            'to_date'           => $to->toDateString(),
            'status'            => BackfillStatus::Pending->value,
            'total_assets'      => $assets->count(),
            'pending_asset_ids' => $assets->pluck('id')->all(),
            'last_note'         => $note,
        ]);

        $this->info("Queued backfill request #{$request->id} for {$assets->count()} asset(s) — will resume automatically via the hourly assets:process-backfill-queue job.");
    }

    /** @return array{0: float, 1: float} [cost_basis, market_value] */
    private function computeHoldingsAsOf(Collection $transactions, Collection $pricesByAssetAndDate, string $date): array
    {
        $costBasis   = 0.0;
        $marketValue = 0.0;

        $groups = $transactions
            ->filter(fn ($t) => $t->type->affectsPosition())
            ->groupBy('asset_id');

        foreach ($groups as $assetId => $txns) {
            $totalQty  = 0.0;
            $totalCost = 0.0;

            foreach ($txns->sortBy('transacted_at') as $t) {
                $qty = (float) $t->quantity;
                if ($t->type->isInflow()) {
                    $usdFee = $t->fee_in_asset ? 0.0 : (float) $t->fees;
                    $totalCost += $qty * (float) $t->price_per_unit + $usdFee;
                    $totalQty += $qty;
                } elseif ($t->type->isOutflow()) {
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

            $price = $this->closestPrice($pricesByAssetAndDate->get($assetId, collect()), $date);
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
