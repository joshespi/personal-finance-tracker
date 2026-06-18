<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\PortfolioSnapshot;
use App\Services\BudgetRuleService;
use App\Services\DebtPayoffService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __invoke(Request $request, BudgetRuleService $budgetRule, DebtPayoffService $debtPayoff): View
    {
        return match ($request->input('tab', 'debt-payoff')) {
            'allocator'      => $this->allocator($request, $budgetRule),
            'emergency-fund' => $this->emergencyFund($request),
            'retirement'     => $this->retirement($request),
            default          => $this->debtPayoff($request, $debtPayoff),
        };
    }

    private function debtPayoff(Request $request, DebtPayoffService $service): View
    {
        return view('planning', [
            'tab'  => 'debt-payoff',
            'data' => $service->compute($request->user()),
        ]);
    }

    private function allocator(Request $request, BudgetRuleService $budgetRule): View
    {
        $amount    = null;
        $buckets   = [];
        $remainder = 0.0;

        if ($request->has('amount')) {
            $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:10000000']]);

            $amount    = round((float) $request->input('amount'), 2);
            $remaining = $amount;
            $user      = $request->user();

            $budget = $budgetRule->compute($user);
            if (! $budget['emergency_funded'] && $budget['emergency_target'] > 0) {
                $gap = max(0.0, $budget['emergency_target'] - $budget['emergency_balance']);
                if ($gap > 0.01) {
                    $alloc     = min($remaining, $gap);
                    $buckets[] = [
                        'label'  => $budget['emergency_envelope']?->name ?? 'Emergency Fund',
                        'reason' => $budget['target_months'].'-month target: $'.number_format($budget['emergency_target'], 2),
                        'amount' => round($alloc, 2),
                        'gap'    => round($gap, 2),
                        'type'   => 'emergency',
                    ];
                    $remaining -= $alloc;
                }
            }

            if ($remaining > 0.01) {
                $liabilities = $user->liabilities()
                    ->with('latestBalance')
                    ->get()
                    ->filter(fn ($l) => $l->isRevolving() && $l->currentBalance() > 0)
                    ->sortByDesc(fn ($l) => (float) ($l->interest_rate ?? 0));

                foreach ($liabilities as $l) {
                    if ($remaining <= 0.01) {
                        break;
                    }
                    $balance   = $l->currentBalance();
                    $apr       = (float) ($l->interest_rate ?? 0);
                    $alloc     = min($remaining, $balance);
                    $buckets[] = [
                        'label'  => $l->name,
                        'reason' => $apr > 0
                            ? number_format($apr, 2).'% APR · $'.number_format(round($balance * $apr / 100 / 12, 2), 2).'/mo interest'
                            : 'No APR recorded',
                        'amount' => round($alloc, 2),
                        'gap'    => round($balance, 2),
                        'type'   => 'debt',
                    ];
                    $remaining -= $alloc;
                }
            }

            if ($remaining > 0.01) {
                $envelopes = $user->envelopes()
                    ->whereNotNull('goal_amount')
                    ->where('is_emergency_fund', false)
                    ->with(['transactions', 'spendTransactions'])
                    ->get()
                    ->sortBy([
                        [fn ($e) => $e->goal_date === null ? 1 : 0, 'asc'],
                        ['goal_date', 'asc'],
                    ]);

                foreach ($envelopes as $env) {
                    if ($remaining <= 0.01) {
                        break;
                    }
                    $gap = round(max(0.0, (float) $env->goal_amount - $env->balance()), 2);
                    if ($gap <= 0.01) {
                        continue;
                    }
                    $alloc     = min($remaining, $gap);
                    $buckets[] = [
                        'label'  => $env->name,
                        'reason' => 'Goal $'.number_format((float) $env->goal_amount, 2)
                            .($env->goal_date ? ' by '.$env->goal_date->format('M Y') : ''),
                        'amount' => round($alloc, 2),
                        'gap'    => $gap,
                        'type'   => 'savings',
                    ];
                    $remaining -= $alloc;
                }
            }

            $remainder = round(max(0.0, $remaining), 2);
        }

        return view('planning', ['tab' => 'allocator', 'amount' => $amount, 'buckets' => $buckets, 'remainder' => $remainder]);
    }

    private function retirement(Request $request): View
    {
        $currentYear = now()->year;
        $request->validate([
            'birth_year'      => ['nullable', 'integer', 'min:1930', 'max:'.($currentYear - 18)],
            'retirement_age'  => ['nullable', 'integer', 'min:40', 'max:90'],
            'current_value'   => ['nullable', 'numeric', 'min:0'],
            'monthly_contrib' => ['nullable', 'numeric', 'min:0'],
            'annual_return'   => ['nullable', 'numeric', 'min:0', 'max:30'],
            'annual_expenses' => ['nullable', 'numeric', 'min:0'],
        ]);

        $user = $request->user();

        $portfolioIds = $user->portfolios()->pluck('id');
        if ($portfolioIds->isNotEmpty()) {
            $latestDates = PortfolioSnapshot::whereIn('portfolio_id', $portfolioIds)
                ->selectRaw('portfolio_id, MAX(recorded_on) as max_date')
                ->groupBy('portfolio_id');
            $defaultValue = round((float) PortfolioSnapshot::joinSub($latestDates, 'latest', fn ($j) => $j->on('portfolio_snapshots.portfolio_id', '=', 'latest.portfolio_id')
                ->on('portfolio_snapshots.recorded_on', '=', 'latest.max_date')
            )->selectRaw('SUM(portfolio_snapshots.market_value + portfolio_snapshots.manual_value) as total')
                ->value('total'), 2);
        } else {
            $defaultValue = 0.0;
        }

        $sixMonthsAgo = now()->subMonths(6)->startOfMonth();
        $lastMonthEnd = now()->subMonth()->endOfMonth();
        $income6m     = (float) $user->cashDeposits()
            ->whereBetween('cash_transactions.occurred_at', [$sixMonthsAgo, $lastMonthEnd])
            ->sum('cash_transactions.amount');
        $annualIncome = round($income6m / 6 * 12, 2);

        $birthYear      = $request->filled('birth_year') ? (int) $request->input('birth_year') : null;
        $age            = $birthYear !== null ? ($currentYear - $birthYear) : null;
        $retirementAge  = (int) $request->input('retirement_age', 65);
        $currentValue   = (float) $request->input('current_value', $defaultValue);
        $monthlyContrib = (float) $request->input('monthly_contrib', 0);
        $annualReturn   = (float) $request->input('annual_return', 7.0);
        $annualExpenses = $request->filled('annual_expenses') ? (float) $request->input('annual_expenses') : null;

        $result = null;

        if ($age !== null && $age < $retirementAge) {
            $monthsLeft   = ($retirementAge - $age) * 12;
            $r            = pow(1 + $annualReturn / 100, 1 / 12) - 1;
            $growthFactor = pow(1 + $r, $monthsLeft);

            $projectedFv = $this->futureValue($currentValue, $monthlyContrib, $r, $monthsLeft);

            $incomeBase = $annualExpenses ?? ($annualIncome > 0 ? $annualIncome : null);
            $target     = $incomeBase ? round($incomeBase * 25, 2) : null;

            $gap             = $target !== null ? round($target - $projectedFv, 2) : null;
            $requiredContrib = null;

            if ($target !== null && $gap > 0) {
                $needed = $target - $currentValue * $growthFactor;
                if ($r > 0 && $growthFactor > 1) {
                    $requiredContrib = round(max(0, $needed * $r / ($growthFactor - 1)), 2);
                } elseif ($monthsLeft > 0) {
                    $requiredContrib = round(max(0, $needed / $monthsLeft), 2);
                }
            } elseif ($target !== null) {
                $requiredContrib = 0;
            }

            $benchmarks = [];
            if ($annualIncome > 0) {
                foreach ([[30, 1], [35, 2], [40, 3], [45, 4], [50, 6], [55, 7], [60, 8], [67, 10]] as [$benchAge, $mult]) {
                    if ($benchAge < $age) {
                        continue;
                    }
                    $months       = ($benchAge - $age) * 12;
                    $proj         = $this->futureValue($currentValue, $monthlyContrib, $r, $months);
                    $benchmarks[] = [
                        'age'       => $benchAge,
                        'multiple'  => $mult,
                        'target'    => round($annualIncome * $mult, 2),
                        'projected' => round($proj, 2),
                        'on_track'  => $proj >= $annualIncome * $mult,
                    ];
                }
            }

            $result = [
                'years_left'       => $retirementAge - $age,
                'projected_fv'     => round($projectedFv, 2),
                'target'           => $target,
                'gap'              => $gap,
                'required_contrib' => $requiredContrib,
                'benchmarks'       => $benchmarks,
                'on_track'         => $target !== null ? ($projectedFv >= $target) : null,
            ];
        }

        return view('planning', [
            'tab'            => 'retirement',
            'birthYear'      => $birthYear,
            'age'            => $age,
            'retirementAge'  => $retirementAge,
            'currentValue'   => $currentValue,
            'defaultValue'   => $defaultValue,
            'monthlyContrib' => $monthlyContrib,
            'annualReturn'   => $annualReturn,
            'annualExpenses' => $annualExpenses,
            'annualIncome'   => $annualIncome,
            'result'         => $result,
        ]);
    }

    private function futureValue(float $pv, float $pmt, float $r, int $months): float
    {
        if ($r > 0) {
            $gf = pow(1 + $r, $months);

            return $pv * $gf + $pmt * ($gf - 1) / $r;
        }

        return $pv + $pmt * $months;
    }

    private function emergencyFund(Request $request): View
    {
        $user     = $request->user();
        $monthNow = now()->startOfMonth();

        $envelopes = $user->envelopes()
            ->where(fn ($q) => $q->where('is_emergency_fund', true)->orWhere('is_mandatory', true))
            ->orderBy('sort_order')
            ->get();

        $emergencyEnvelope  = $envelopes->firstWhere('is_emergency_fund', true);
        $mandatoryEnvelopes = $envelopes->filter(fn ($e) => $e->is_mandatory)->values();

        $monthlyBaseline  = 0;
        $monthlyBreakdown = collect();

        if ($mandatoryEnvelopes->isNotEmpty()) {
            $monthKeys = array_map(
                fn ($i) => $monthNow->copy()->subMonths($i)->format('Y-m'),
                range(0, 5)
            );

            $spendRows = CashTransaction::whereIn('envelope_id', $mandatoryEnvelopes->pluck('id'))
                ->where('type', 'withdrawal')
                ->whereBetween('occurred_at', [$monthNow->copy()->subMonths(5), $monthNow->copy()->endOfMonth()])
                ->get(['envelope_id', 'occurred_at', 'amount']);

            $byEnvelope = $spendRows->groupBy('envelope_id');

            foreach ($mandatoryEnvelopes as $env) {
                $monthlyTotals = array_fill_keys($monthKeys, 0.0);
                foreach ($byEnvelope->get($env->id, collect()) as $txn) {
                    $key = $txn->occurred_at->format('Y-m');
                    if (isset($monthlyTotals[$key])) {
                        $monthlyTotals[$key] += (float) $txn->amount;
                    }
                }
                $avg = round(array_sum($monthlyTotals) / 6, 2);
                $monthlyBaseline += $avg;
                $monthlyBreakdown->push(['envelope' => $env, 'avg' => $avg]);
            }

            $monthlyBaseline = round($monthlyBaseline, 2);
        }

        $currentSavings = $emergencyEnvelope ? round($emergencyEnvelope->balance(), 2) : null;
        $target3        = round($monthlyBaseline * 3, 2);
        $target6        = round($monthlyBaseline * 6, 2);

        $bars = [];
        if ($currentSavings !== null && $target6 > 0) {
            foreach ([['3-month fund', $target3], ['6-month fund', $target6]] as [$label, $target]) {
                $bars[] = [
                    'label'  => $label,
                    'target' => $target,
                    'pct'    => min(100, round($currentSavings / $target * 100)),
                ];
            }
        }

        return view('planning', [
            'tab'                => 'emergency-fund',
            'emergencyEnvelope'  => $emergencyEnvelope,
            'mandatoryEnvelopes' => $mandatoryEnvelopes,
            'monthlyBreakdown'   => $monthlyBreakdown,
            'monthlyBaseline'    => $monthlyBaseline,
            'target3'            => $target3,
            'target6'            => $target6,
            'currentSavings'     => $currentSavings,
            'bars'               => $bars,
        ]);
    }
}
