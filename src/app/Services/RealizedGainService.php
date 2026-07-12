<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Portfolio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class RealizedGainService
{
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

        $lots     = collect();
        $openLots = [];

        foreach ($txns as $t) {
            $assetId = $t->asset_id;
            $openLots[$assetId] ??= [];

            if ($t->type->isInflow()) {
                $usdFee               = $t->fee_in_asset ? 0.0 : (float) $t->fees;
                $costPerUnit          = (float) $t->price_per_unit + ($usdFee / max(1, (float) $t->quantity));
                $openLots[$assetId][] = [
                    'qty'           => (float) $t->quantity,
                    'cost_per_unit' => $costPerUnit,
                    'date'          => $t->transacted_at,
                    'asset'         => $t->asset,
                ];
            } elseif ($t->type->isOutflow()) {
                // fee_in_asset on a transfer_out means fee units also left the wallet
                $remainingToSell = $t->fee_in_asset
                    ? (float) $t->quantity + (float) $t->fees
                    : (float) $t->quantity;
                $sellPrice = (float) $t->price_per_unit;
                $sellDate  = $t->transacted_at;
                $isSale    = $t->type === TransactionType::Sell;

                // transfers move cost basis to the destination portfolio — not a taxable event
                $this->consumeFifo($openLots[$assetId], $remainingToSell, function (array $lot, float $matched) use ($lots, $isSale, $t, $sellPrice, $sellDate) {
                    if ($isSale) {
                        $lots->push([
                            'asset'        => $t->asset,
                            'quantity'     => $matched,
                            'buy_price'    => $lot['cost_per_unit'],
                            'sell_price'   => $sellPrice,
                            'cost_basis'   => round($matched * $lot['cost_per_unit'], 2),
                            'proceeds'     => round($matched * $sellPrice, 2),
                            'gain'         => round($matched * ($sellPrice - $lot['cost_per_unit']), 2),
                            'buy_date'     => $lot['date'],
                            'sell_date'    => $sellDate,
                            'holding_days' => (int) $lot['date']->diffInDays($sellDate),
                        ]);
                    }
                });
            }
        }

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

        $openLots = [];

        foreach ($txns as $t) {
            if ($t->type->isInflow()) {
                $usdFee     = $t->fee_in_asset ? 0.0 : (float) $t->fees;
                $openLots[] = [
                    'qty'           => (float) $t->quantity,
                    'cost_per_unit' => (float) $t->price_per_unit + ($usdFee / max(1, (float) $t->quantity)),
                ];
            } elseif ($t->type->isOutflow()) {
                $remaining = $t->fee_in_asset
                    ? (float) $t->quantity + (float) $t->fees
                    : (float) $t->quantity;

                $this->consumeFifo($openLots, $remaining);
            }
        }

        return array_values(array_filter($openLots, fn ($l) => $l['qty'] > 0.000001));
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

    public function computeTwr(Portfolio $portfolio): array
    {
        if ($portfolio->relationLoaded('snapshots')) {
            $snapshots = $portfolio->snapshots->sortBy('recorded_on')->values();
        } else {
            $snapshots = $portfolio->snapshots()->orderBy('recorded_on')->get(['recorded_on', 'market_value', 'manual_value', 'cost_basis']);
        }

        if ($snapshots->count() < 2) {
            return ['total_pct' => null, 'annualized_pct' => null, 'first_date' => null];
        }

        // TWR cashflows exclude staking rewards (no cash changed hands).
        if ($portfolio->relationLoaded('transactions')) {
            $txns = $portfolio->transactions
                ->filter(fn ($t) => $t->type->isCashflow())
                ->sortBy('transacted_at')
                ->values();
        } else {
            $txns = $portfolio->transactions()
                ->whereIn('type', TransactionType::cashflowValues())
                ->orderBy('transacted_at')
                ->get(['type', 'quantity', 'price_per_unit', 'fees', 'transacted_at']);
        }

        $cashflows = [];
        foreach ($txns as $t) {
            $date             = $t->transacted_at->toDateString();
            $amount           = (float) $t->quantity * (float) $t->price_per_unit + (float) $t->fees;
            $sign             = $t->type->isInflow() ? 1 : -1;
            $cashflows[$date] = ($cashflows[$date] ?? 0) + $sign * $amount;
        }

        $twr       = 1.0;
        $prevValue = null;
        $firstDate = null;
        $lastDate  = null;

        foreach ($snapshots as $snap) {
            $date  = $snap->recorded_on->toDateString();
            $value = (float) $snap->market_value + (float) $snap->manual_value;

            if ($prevValue === null) {
                $prevValue = $value;
                $firstDate = $date;
                $lastDate  = $date;

                continue;
            }

            $cf          = $cashflows[$date] ?? 0;
            $denominator = $prevValue + $cf;

            if ($denominator > 0) {
                $twr *= ($value / $denominator);
            }

            $prevValue = $value;
            $lastDate  = $date;
        }

        $totalPct = round(($twr - 1) * 100, 2);

        $days          = Carbon::parse($firstDate)->diffInDays(Carbon::parse($lastDate));
        $annualizedPct = $days > 0
            ? round((pow($twr, 365 / $days) - 1) * 100, 2)
            : null;

        return [
            'total_pct'      => $totalPct,
            'annualized_pct' => $annualizedPct,
            'first_date'     => $firstDate,
        ];
    }
}
