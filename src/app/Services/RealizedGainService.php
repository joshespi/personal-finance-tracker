<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Support\FifoLots;
use Illuminate\Support\Collection;

/**
 * FIFO cost-basis kernel for a portfolio's position history: realized gains on sales,
 * and open-lot lookups for transfer cost-basis carryover. Time-weighted return lives in
 * PortfolioPerformanceService — a different concern (chart-of-value performance, not
 * lot accounting) that used to live here only because it also needed a Portfolio.
 */
class RealizedGainService
{
    /**
     * IRS long-term capital gains threshold: the holding period must be *more than*
     * one year, so a sale exactly 365 days after the buy is still short-term — hence
     * the strict `>` at the tagging site below, not `>=`.
     */
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

        FifoLots::build($txns, function (array $lot, float $matched, Transaction $t) use ($lots) {
            if ($t->type !== TransactionType::Sell) {
                return; // transfers move cost basis to the destination portfolio — not a taxable event
            }

            $sellPrice = (float) $t->price_per_unit;
            // A cash sell fee reduces what was actually received — mirrors the buy-side
            // fee-into-cost-per-unit fold in FifoLots::build() so proceeds/gain reconcile
            // with the account's actual cash movement.
            $netSellPrice = $sellPrice - $t->usdFeePerUnit();
            $sellDate     = $t->transacted_at;
            $holdingDays  = (int) $lot['date']->diffInDays($sellDate);

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
                'term' => $holdingDays > self::LONG_TERM_HOLDING_DAYS ? 'long' : 'short',
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

        $openLots = FifoLots::build($txns)[$assetId] ?? [];

        return array_values(array_filter($openLots, fn ($l) => $l['qty'] > FifoLots::EPSILON));
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

        $totalCost  = 0.0;
        $matchedQty = 0.0;

        FifoLots::consume($openLots, $qtySent, function (array $lot, float $matched) use (&$totalCost, &$matchedQty) {
            $totalCost += $matched * $lot['cost_per_unit'];
            $matchedQty += $matched;
        });

        if ($qtySent - $matchedQty > FifoLots::EPSILON) {
            return null;
        }

        return $totalCost / $qtyReceived;
    }
}
