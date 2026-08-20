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
            $date = $t->transacted_at->toDateString();
            $sign = $t->type->isInflow() ? 1 : -1;
            // The fee always leaves the account, whichever way the trade goes: a buy costs
            // (qty x price + fee), a sale returns (qty x price - fee). Signing the fee with
            // the flow instead of always adding it keeps a sale's cashflow equal to the cash
            // actually received — adding it made every sale overstate the outflow by 2x the fee.
            $gross            = (float) $t->quantity * (float) $t->price_per_unit;
            $cashflows[$date] = ($cashflows[$date] ?? 0) + $sign * $gross + $t->usdFee();
        }
        ksort($cashflows);
        $cashflowDates = array_keys($cashflows);
        $cfIndex       = 0;
        $cfCount       = count($cashflowDates);

        $twr       = 1.0;
        $prevValue = null;
        $firstDate = null;
        $lastDate  = null;

        foreach ($snapshots as $snap) {
            $date  = $snap->recorded_on->toDateString();
            $value = (float) $snap->market_value + (float) $snap->manual_value;

            // Every cashflow up through this snapshot's date that hasn't already been
            // attributed to an earlier sub-period — not just one landing on an exact
            // snapshot date. A deposit/withdrawal falling between two snapshots (a missed
            // scheduler run, or any gap) would otherwise be silently dropped from the
            // denominator and misread as investment performance.
            $cf = 0.0;
            while ($cfIndex < $cfCount && $cashflowDates[$cfIndex] <= $date) {
                $cf += $cashflows[$cashflowDates[$cfIndex]];
                $cfIndex++;
            }

            if ($prevValue === null) {
                // Cashflows before the first snapshot have no prior sub-period to belong to.
                $prevValue = $value;
                $firstDate = $date;
                $lastDate  = $date;

                continue;
            }

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
