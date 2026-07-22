<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Portfolio;
use Carbon\Carbon;

/**
 * Time-weighted return (TWR) for a portfolio, computed from its snapshot history and
 * cashflow transactions. Split out of RealizedGainService — TWR is a value-performance
 * calculation, unrelated to that service's FIFO cost-basis/realized-gain accounting;
 * the two were only ever colocated because both take a Portfolio.
 */
class PortfolioPerformanceService
{
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
