<?php

namespace App\Services;

use App\Models\Portfolio;

class RealizedGainService
{
    public function compute(Portfolio $portfolio): array
    {
        if (! $portfolio->relationLoaded('transactions')) {
            $portfolio->load('transactions.asset');
        }

        $txns = $portfolio->transactions
            ->filter(fn ($t) => in_array($t->type, ['buy', 'sell', 'transfer_in', 'transfer_out', 'staking_reward']))
            ->sortBy('transacted_at');

        $lots     = collect();
        $openLots = [];

        foreach ($txns as $t) {
            $assetId = $t->asset_id;

            if (in_array($t->type, ['buy', 'transfer_in', 'staking_reward'])) {
                $costPerUnit = (float) $t->price_per_unit + ((float) $t->fees / max(1, (float) $t->quantity));
                $openLots[$assetId][] = [
                    'qty'           => (float) $t->quantity,
                    'cost_per_unit' => $costPerUnit,
                    'date'          => $t->transacted_at,
                    'asset'         => $t->asset,
                ];
            } elseif (in_array($t->type, ['sell', 'transfer_out'])) {
                $remainingToSell = (float) $t->quantity;
                $sellPrice       = (float) $t->price_per_unit;
                $sellDate        = $t->transacted_at;

                while ($remainingToSell > 0.000001 && ! empty($openLots[$assetId])) {
                    $lot = &$openLots[$assetId][0];

                    $matched = min($lot['qty'], $remainingToSell);

                    $lots->push([
                        'asset'          => $t->asset,
                        'quantity'       => $matched,
                        'buy_price'      => $lot['cost_per_unit'],
                        'sell_price'     => $sellPrice,
                        'cost_basis'     => round($matched * $lot['cost_per_unit'], 2),
                        'proceeds'       => round($matched * $sellPrice, 2),
                        'gain'           => round($matched * ($sellPrice - $lot['cost_per_unit']), 2),
                        'buy_date'       => $lot['date'],
                        'sell_date'      => $sellDate,
                        'holding_days'   => (int) $lot['date']->diffInDays($sellDate),
                    ]);

                    $lot['qty'] -= $matched;
                    $remainingToSell -= $matched;

                    if ($lot['qty'] < 0.000001) {
                        array_shift($openLots[$assetId]);
                    }
                }
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
                'asset'       => $group->first()['asset'],
                'total_gain'  => round($group->sum('gain'), 2),
                'total_cost'  => round($group->sum('cost_basis'), 2),
                'total_proceeds' => round($group->sum('proceeds'), 2),
                'lots'        => $group->values(),
            ])
            ->sortByDesc(fn ($g) => abs($g['total_gain']))
            ->values();

        return compact('lots', 'totalGain', 'byYear', 'byAsset');
    }

    public function computeTwr(Portfolio $portfolio): array
    {
        $snapshots = $portfolio->snapshots()
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'market_value', 'manual_value', 'cost_basis']);

        if ($snapshots->count() < 2) {
            return ['total_pct' => null, 'annualized_pct' => null, 'first_date' => null];
        }

        // Grab cash flows from transactions (buys/transfers_in = positive, sells/transfers_out = negative)
        $txns = $portfolio->transactions()
            ->whereIn('type', ['buy', 'sell', 'transfer_in', 'transfer_out'])
            ->orderBy('transacted_at')
            ->get(['type', 'quantity', 'price_per_unit', 'fees', 'transacted_at']);

        // Build a map of date => net cash flow
        $cashflows = [];
        foreach ($txns as $t) {
            $date      = $t->transacted_at->toDateString();
            $amount    = (float) $t->quantity * (float) $t->price_per_unit + (float) $t->fees;
            $sign      = in_array($t->type, ['buy', 'transfer_in']) ? 1 : -1;
            $cashflows[$date] = ($cashflows[$date] ?? 0) + $sign * $amount;
        }

        // Compute sub-period returns and chain-link
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

            $cf = $cashflows[$date] ?? 0;
            $denominator = $prevValue + $cf;

            if ($denominator > 0) {
                $twr *= ($value / $denominator);
            }

            $prevValue = $value;
            $lastDate  = $date;
        }

        $totalPct = round(($twr - 1) * 100, 2);

        // Annualize
        $days          = \Carbon\Carbon::parse($firstDate)->diffInDays(\Carbon\Carbon::parse($lastDate));
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
