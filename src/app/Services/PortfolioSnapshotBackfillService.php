<?php

namespace App\Services;

use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class PortfolioSnapshotBackfillService
{
    /** @param  ?array<int, int>  $ids  null means no filter (load every portfolio); [] filters to none */
    public function resolvePortfolios(?array $ids): Collection
    {
        $portfolios = $ids !== null
            ? Portfolio::whereIn('id', $ids)->get()
            : Portfolio::all();

        $portfolios->load([
            'transactions.asset',
            'manualAssets.valuations',
            'manualAssets.proxyAsset',
        ]);

        return $portfolios;
    }

    public function collectAssets(Collection $portfolios): Collection
    {
        $positionAssets = $portfolios
            ->flatMap(fn ($p) => $p->transactions->map(fn ($t) => $t->asset))
            ->filter()
            ->unique('id')
            ->values();

        $proxyAssets = $portfolios
            ->flatMap(fn ($p) => $p->manualAssets
                ->filter(fn ($ma) => $ma->isProxyTracked() && $ma->proxyAsset)
                ->map(fn ($ma) => $ma->proxyAsset)
            )
            ->unique('id')
            ->values();

        return $positionAssets->concat($proxyAssets)->unique('id')->values();
    }

    /**
     * Writes (or previews) snapshots for every day in [$from, $to] across $portfolios.
     * Callers chunk the date range across multiple invocations to keep each one bounded.
     *
     * @param  ?Carbon  $priceLoadFrom  Lower bound for the price lookback, independent of $from.
     *                                  Chunked callers pass the *whole* backfill request's start
     *                                  date here (not the chunk's), so closestPrice() can still
     *                                  reach back to a price from an earlier chunk — otherwise an
     *                                  asset with a real gap longer than the weekend buffer, or a
     *                                  proxy-ticker manual asset's anchor_date, would silently
     *                                  resolve differently depending on which chunk is running.
     *                                  Defaults to $from for non-chunked (whole-range) callers.
     * @return int number of snapshots written (0 when $dryRun)
     */
    public function writeRange(Collection $portfolios, Collection $allAssets, Carbon $from, Carbon $to, bool $dryRun, ?callable $onDate = null, ?Carbon $priceLoadFrom = null): int
    {
        // Buffer 7 days before the load window so weekend lookback finds Friday prices
        $loadFrom = ($priceLoadFrom ?? $from)->copy()->subDays(7);
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
        $rows    = [];

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

                [$costBasis, $marketValue] = $this->computeHoldingsAsOf($txnsAsOf, $pricesByAssetAndDate, $dateStr);
                $manualValue               = $this->computeManualValueAsOf($chartManualAssets[$portfolio->id], $pricesByAssetAndDate, $dateStr);

                if ($dryRun) {
                    if ($onDate) {
                        $onDate($dateStr, $portfolio, round($marketValue + $manualValue, 2));
                    }

                    continue;
                }

                $rows[] = [
                    'portfolio_id' => $portfolio->id,
                    'recorded_on'  => $dateStr,
                    'cost_basis'   => $costBasis,
                    'market_value' => $marketValue,
                    'manual_value' => $manualValue,
                ];
                $written++;

                if (count($rows) >= 500) {
                    $this->flushSnapshots($rows);
                    $rows = [];
                }
            }
        }

        $this->flushSnapshots($rows);

        return $written;
    }

    private function flushSnapshots(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        PortfolioSnapshot::upsert($rows, ['portfolio_id', 'recorded_on'], ['cost_basis', 'market_value', 'manual_value']);
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
            [$totalQty, $totalCost] = Transaction::accumulateCostBasis($txns);
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
            if ($ma->isProxyTracked()) {
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
