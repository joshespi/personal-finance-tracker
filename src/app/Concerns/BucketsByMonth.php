<?php

namespace App\Concerns;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Shared month-bucketing helpers for the budgeting calculators (spending trends,
 * emergency fund, cashflow history). Keeps the "build a month series" and "sum rows
 * into Y-m buckets" loops in one place instead of copy-pasted per service.
 */
trait BucketsByMonth
{
    /**
     * Ascending collection of $count month-start Carbon instances, the newest being
     * $end's month. e.g. monthSeries(now(), 6) → [5 months ago … this month].
     */
    protected function monthSeries(CarbonInterface $end, int $count): Collection
    {
        $start = $end->copy()->startOfMonth();

        return collect(range($count - 1, 0))
            ->map(fn ($i) => $start->copy()->subMonths($i));
    }

    /**
     * Sum $rows into ['Y-m' => float] buckets keyed by the $dateAttr month.
     *
     * @param  iterable<object>  $rows
     */
    protected function sumByMonth(iterable $rows, string $dateAttr = 'occurred_at', string $amountAttr = 'amount'): array
    {
        $totals = [];
        foreach ($rows as $row) {
            $key          = $row->{$dateAttr}->format('Y-m');
            $totals[$key] = ($totals[$key] ?? 0.0) + (float) $row->{$amountAttr};
        }

        return $totals;
    }
}
