<?php

namespace App\Http\Controllers;

use App\Models\EnvelopeTransaction;
use App\Models\PortfolioSnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        [$defaultStartNw, $defaultMonthlySavings] = $this->computeDefaults($user);

        $startingNw     = (float) $request->input('starting_nw', $defaultStartNw);
        $monthlySavings = (float) $request->input('monthly_savings', $defaultMonthlySavings);
        $annualReturn   = (float) $request->input('annual_return', 7.0);
        $inflationRate  = (float) $request->input('inflation_rate', 2.5);
        $years          = (int)   $request->input('years', 30);
        $fireTarget     = $request->filled('fire_target') ? max(0, (float) $request->input('fire_target')) : null;

        $annualReturn  = max(0, min(30, $annualReturn));
        $inflationRate = max(0, min(20, $inflationRate));
        $years         = in_array($years, [10, 20, 30, 40, 50]) ? $years : 30;

        [$projection, $standardMilestones, $fireMilestone] = $this->project(
            $startingNw, $monthlySavings, $annualReturn, $inflationRate, $years, $fireTarget
        );

        return view('forecast', compact(
            'projection', 'standardMilestones', 'fireMilestone',
            'startingNw', 'monthlySavings', 'annualReturn', 'inflationRate', 'years', 'fireTarget',
            'defaultStartNw', 'defaultMonthlySavings',
        ));
    }

    private function computeDefaults($user): array
    {
        $portfolioIds = $user->portfolios()->pluck('id');

        $snapshotValue = $portfolioIds->isNotEmpty()
            ? PortfolioSnapshot::whereIn('portfolio_id', $portfolioIds)
                ->orderByDesc('recorded_on')
                ->get()
                ->groupBy('portfolio_id')
                ->sum(fn ($snaps) => (float) $snaps->first()->market_value + (float) $snaps->first()->manual_value)
            : 0.0;

        $defaultStartNw = round($snapshotValue + $user->totalCash() - $user->totalDebt(), 2);

        $threeMonthsAgo = now()->subMonths(3)->startOfMonth();
        $lastMonthEnd   = now()->subMonth()->endOfMonth();

        $income3m = (float) $user->incomeEntries()
            ->whereBetween('occurred_at', [$threeMonthsAgo, $lastMonthEnd])
            ->sum('amount');

        $envelopeIds = $user->envelopes()->pluck('id');
        $spend3m = $envelopeIds->isNotEmpty()
            ? (float) EnvelopeTransaction::whereIn('envelope_id', $envelopeIds)
                ->where('type', 'spend')
                ->whereBetween('occurred_at', [$threeMonthsAgo, $lastMonthEnd])
                ->sum('amount')
            : 0.0;

        $defaultMonthlySavings = round(max(0, ($income3m - $spend3m) / 3), 2);

        return [$defaultStartNw, $defaultMonthlySavings];
    }

    private function project(
        float $startingNw,
        float $monthlySavings,
        float $annualReturn,
        float $inflationRate,
        int $years,
        ?float $fireTarget
    ): array {
        $r = pow(1 + $annualReturn / 100, 1 / 12) - 1;

        $standardThresholds = [500_000, 1_000_000, 2_000_000, 5_000_000];
        $allThresholds      = $standardThresholds;
        if ($fireTarget !== null && !in_array((int) $fireTarget, $standardThresholds)) {
            $allThresholds[] = $fireTarget;
        }

        $hitMap      = array_fill_keys($allThresholds, null);
        $projection  = [];
        $currentYear = now()->year;

        for ($y = 0; $y <= $years; $y++) {
            $t = $y * 12;

            $nominal = $r > 0
                ? $startingNw * pow(1 + $r, $t) + $monthlySavings * (pow(1 + $r, $t) - 1) / $r
                : $startingNw + $monthlySavings * $t;

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

        $fireMilestone = ($fireTarget !== null) ? ($hitMap[$fireTarget] ?? null) : null;

        return [$projection, $standardMilestones, $fireMilestone];
    }
}
