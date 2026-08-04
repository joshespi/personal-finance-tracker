<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * FIFO cost-basis kernel for a portfolio's position history: realized gains on sales,
 * and open-lot lookups for transfer cost-basis carryover. Time-weighted return lives in
 * PortfolioPerformanceService — a different concern (chart-of-value performance, not
 * lot accounting) that used to live here only because it also needed a Portfolio.
 */
class RealizedGainService
{
    /** IRS long-term capital gains threshold: held for more than one year. */
    private const LONG_TERM_HOLDING_DAYS = 365;

    /** Realized-gain lots across every non-tax-advantaged portfolio a user owns, each tagged with its source portfolio. */
    public function allLotsForUser(User $user): Collection
    {
        $portfolios = $user->portfolios()->where('is_tax_advantaged', false)->with('transactions.asset')->get();

        return $portfolios->flatMap(
            fn (Portfolio $portfolio) => $this->compute($portfolio)['lots']
                ->map(fn ($lot) => array_merge($lot, ['portfolio' => $portfolio]))
        );
    }

    public function compute(Portfolio $portfolio): array
    {
        if (! $portfolio->relationLoaded('transactions')) {
            $portfolio->load('transactions.asset');
        }

        $txns = $portfolio->transactions
            ->filter(fn ($t) => $t->type->affectsPosition())
            ->sortBy('transacted_at');

        $lots = collect();

        $this->buildOpenLots($txns, function (array $lot, float $matched, Transaction $t) use ($lots) {
            if ($t->type !== TransactionType::Sell) {
                return; // transfers move cost basis to the destination portfolio — not a taxable event
            }

            $sellPrice = (float) $t->price_per_unit;
            // A cash sell fee reduces what was actually received — mirrors the buy-side
            // fee-into-cost-per-unit fold in buildOpenLots() so proceeds/gain reconcile
            // with the account's actual cash movement. Zero when fee_in_asset is set
            // (that fee left as units, already reflected via quantityWithAssetFee()).
            $sellFeePerUnit = $t->usdFee() / max(1, (float) $t->quantity);
            $netSellPrice   = $sellPrice - $sellFeePerUnit;
            $sellDate       = $t->transacted_at;
            $holdingDays    = (int) $lot['date']->diffInDays($sellDate);

            $lots->push([
                'asset'        => $t->asset,
                'quantity'     => $matched,
                'buy_price'    => $lot['cost_per_unit'],
                'sell_price'   => $sellPrice,
                'cost_basis'   => round($matched * $lot['cost_per_unit'], 2),
                'proceeds'     => round($matched * $netSellPrice, 2),
                'gain'         => round($matched * ($netSellPrice - $lot['cost_per_unit']), 2),
                'buy_date'     => $lot['date'],
                'sell_date'    => $sellDate,
                'holding_days' => $holdingDays,
                // IRS long-term/short-term threshold: held > 1 year. Tagged here so
                // consumers (tax summary, realized-gains export) don't each re-derive
                // the 365-day rule from holding_days themselves.
                'term' => $holdingDays >= self::LONG_TERM_HOLDING_DAYS ? 'long' : 'short',
            ]);
        });

        $totalGain = round($lots->sum('gain'), 2);

        $byYear = $lots
            ->groupBy(fn ($l) => $l['sell_date']->year)
            ->map(fn ($group) => round($group->sum('gain'), 2))
            ->sortKeys()
            ->all();

        $byAsset = $lots
            ->groupBy(fn ($l) => $l['asset']->symbol)
            ->map(fn ($group) => [
                'asset'          => $group->first()['asset'],
                'total_gain'     => round($group->sum('gain'), 2),
                'total_cost'     => round($group->sum('cost_basis'), 2),
                'total_proceeds' => round($group->sum('proceeds'), 2),
                'lots'           => $group->values(),
            ])
            ->sortByDesc(fn ($g) => abs($g['total_gain']))
            ->values();

        return compact('lots', 'totalGain', 'byYear', 'byAsset');
    }

    /**
     * FIFO open lots for one asset in a portfolio, up to and including $date.
     * Returns [['qty' => float, 'cost_per_unit' => float], ...].
     */
    public function openLotsForAsset(Portfolio $portfolio, int $assetId, string $date, ?int $excludeId = null): array
    {
        $txns = $portfolio->transactions()
            ->where('asset_id', $assetId)
            ->whereIn('type', TransactionType::positionValues())
            ->where('transacted_at', '<=', $date)
            ->when($excludeId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->orderBy('transacted_at')
            ->get();

        $openLots = $this->buildOpenLots($txns)[$assetId] ?? [];

        return array_values(array_filter($openLots, fn ($l) => $l['qty'] > 0.000001));
    }

    /**
     * Builds FIFO open lots per asset from a transaction set: inflows push a new lot
     * (qty, cost-per-unit, date, asset), outflows consume existing lots FIFO. Shared by
     * compute() (portfolio-wide, needs the date/asset tags to build realized-gain rows)
     * and openLotsForAsset() (single-asset, for transfer cost-basis carryover) — these
     * used to be two separately maintained copies of the same walk.
     *
     * $onSale, if given, is invoked (lot, matchedQty, transaction) for every FIFO match
     * on an outflow — the hook compute() uses to record a realized-gain row per match,
     * without duplicating the walk itself.
     *
     * @return array<int, array<int, array{qty: float, cost_per_unit: float, date: Carbon, asset: mixed}>> keyed by asset_id
     */
    private function buildOpenLots(Collection $txns, ?callable $onSale = null): array
    {
        $openLots = [];

        foreach ($txns as $t) {
            $assetId = $t->asset_id;
            $openLots[$assetId] ??= [];

            if ($t->type->isInflow()) {
                $costPerUnit          = (float) $t->price_per_unit + ($t->usdFee() / max(1, (float) $t->quantity));
                $openLots[$assetId][] = [
                    'qty'           => (float) $t->quantity,
                    'cost_per_unit' => $costPerUnit,
                    'date'          => $t->transacted_at,
                    'asset'         => $t->asset,
                ];
            } elseif ($t->type->isOutflow()) {
                // fee_in_asset on a transfer_out means fee units also left the wallet
                $remainingToSell = $t->quantityWithAssetFee();

                $this->consumeFifo($openLots[$assetId], $remainingToSell, function (array $lot, float $matched) use ($onSale, $t) {
                    if ($onSale !== null) {
                        $onSale($lot, $matched, $t);
                    }
                });
            }
        }

        return $openLots;
    }

    /**
     * Consume $remaining units FIFO from &$lots (each ['qty' => float, ...]),
     * decrementing qty and shifting fully-consumed lots off the front.
     * $onMatch, if given, is invoked with (lot, matchedQty) — before qty is
     * decremented — for every partial/full match, so callers can record
     * per-match detail (e.g. a realized-gain row) without duplicating the walk.
     */
    private function consumeFifo(array &$lots, float $remaining, ?callable $onMatch = null): void
    {
        while ($remaining > 0.000001 && ! empty($lots)) {
            $matched = min($lots[0]['qty'], $remaining);

            if ($onMatch !== null) {
                $onMatch($lots[0], $matched);
            }

            $lots[0]['qty'] -= $matched;
            $remaining -= $matched;

            if ($lots[0]['qty'] < 0.000001) {
                array_shift($lots);
            }
        }
    }

    /**
     * Walk open lots FIFO to get the total cost for $qtySent units, then
     * divide by $qtyReceived to get cost-per-unit for the destination lot.
     * Returns null when lots don't cover qtySent (caller should fall back).
     */
    public function transferInCostPerUnit(array $openLots, float $qtySent, float $qtyReceived): ?float
    {
        if ($qtyReceived <= 0) {
            return null;
        }

        $remaining = $qtySent;
        $totalCost = 0.0;

        foreach ($openLots as $lot) {
            if ($remaining <= 0.000001) {
                break;
            }
            $matched = min($lot['qty'], $remaining);
            $totalCost += $matched * $lot['cost_per_unit'];
            $remaining -= $matched;
        }

        if ($remaining > 0.000001) {
            return null;
        }

        return $totalCost / $qtyReceived;
    }
}
