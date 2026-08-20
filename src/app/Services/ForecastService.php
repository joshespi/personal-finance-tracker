<?php

namespace App\Services;

use App\Models\User;
use App\Support\Finance;

class ForecastService
{
    /** Trailing 3-month average net worth / monthly-savings-rate defaults, used to prefill the form. */
    public function computeDefaults(User $user): array
    {
        $defaultStartNw = round($user->latestPortfolioValue() + $user->totalCash() - $user->totalDebt(), 2);

        $income3m = $user->incomeForTrailingMonths(3);
        $spend3m  = $user->spendForTrailingMonths(3);

        $defaultMonthlySavings = round(max(0, ($income3m - $spend3m) / 3), 2);

        return [$defaultStartNw, $defaultMonthlySavings];
    }

    /**
     * Project a net-worth trajectory from already-resolved inputs (request
     * parsing/clamping stays in the controller, which also merges the display
     * defaults into the view data itself).
     */
    public function compute(
        float $startingNw,
        float $monthlySavings,
        float $annualReturn,
        float $inflationRate,
        int $years,
        ?float $fireTarget,
    ): array {
        [$projection, $standardMilestones, $fireMilestone] = $this->project(
            $startingNw, $monthlySavings, $annualReturn, $inflationRate, $years, $fireTarget
        );

        return compact(
            'projection', 'standardMilestones', 'fireMilestone',
            'startingNw', 'monthlySavings', 'annualReturn', 'inflationRate', 'years', 'fireTarget',
        );
    }

    private function project(
        float $startingNw,
        float $monthlySavings,
        float $annualReturn,
        float $inflationRate,
        int $years,
        ?float $fireTarget
    ): array {
        $r = Finance::monthlyRate($annualReturn);

        $standardThresholds = [500_000, 1_000_000, 2_000_000, 5_000_000];
        $allThresholds      = $standardThresholds;

        // Normalize to an int once, up front: these values become array keys below, and PHP
        // silently truncates a float key (a deprecation since 8.1). Casting at the in_array()
        // check but pushing the raw float — as this did — also meant a $1,000,000.50 target
        // was skipped as "already standard" yet then looked up under the truncated key.
        $fireTarget = $fireTarget !== null ? (int) round($fireTarget) : null;

        if ($fireTarget !== null && ! in_array($fireTarget, $standardThresholds, true)) {
            $allThresholds[] = $fireTarget;
        }

        $hitMap      = array_fill_keys($allThresholds, null);
        $projection  = [];
        $currentYear = now()->year;

        for ($y = 0; $y <= $years; $y++) {
            $t = $y * 12;

            $nominal = Finance::futureValue($startingNw, $monthlySavings, $r, $t);

            $real = $y > 0
                ? $nominal / pow(1 + $inflationRate / 100, $y)
                : $nominal;

            $projection[] = [
                'year'    => $y,
                'label'   => $currentYear + $y,
                'nominal' => round($nominal, 2),
                'real'    => round($real, 2),
            ];

            foreach ($allThresholds as $threshold) {
                if ($hitMap[$threshold] === null && $nominal >= $threshold) {
                    $hitMap[$threshold] = ['year' => $y, 'calendar' => $currentYear + $y];
                }
            }
        }

        $standardMilestones = array_map(
            fn ($t) => ['threshold' => $t, 'hit' => $hitMap[$t]],
            $standardThresholds
        );

        // $hitMap is keyed by $allThresholds, which always contains $fireTarget by here.
        $fireMilestone = $fireTarget !== null ? $hitMap[$fireTarget] : null;

        return [$projection, $standardMilestones, $fireMilestone];
    }
}
