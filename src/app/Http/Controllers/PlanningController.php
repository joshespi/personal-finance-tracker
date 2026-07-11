<?php

namespace App\Http\Controllers;

use App\Services\AllocatorService;
use App\Services\DebtPayoffService;
use App\Services\EmergencyFundService;
use App\Support\Finance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __invoke(Request $request, DebtPayoffService $debtPayoff, EmergencyFundService $emergencyFund, AllocatorService $allocator): View
    {
        return match ($request->input('tab', 'debt-payoff')) {
            'allocator'      => $this->allocator($request, $allocator),
            'emergency-fund' => $this->emergencyFund($request, $emergencyFund),
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

    private function allocator(Request $request, AllocatorService $allocator): View
    {
        $amount = null;
        if ($request->has('amount')) {
            $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:10000000']]);
            $amount = round((float) $request->input('amount'), 2);
        }

        return view('planning', [
            'tab' => 'allocator',
            ...$allocator->compute($request->user(), $amount),
        ]);
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

        $defaultValue = round($user->latestPortfolioValue(), 2);

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
            $r            = Finance::monthlyRate($annualReturn);
            $growthFactor = pow(1 + $r, $monthsLeft);

            $projectedFv = Finance::futureValue($currentValue, $monthlyContrib, $r, $monthsLeft);

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
                    $proj         = Finance::futureValue($currentValue, $monthlyContrib, $r, $months);
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

    private function emergencyFund(Request $request, EmergencyFundService $emergencyFund): View
    {
        return view('planning', [
            'tab' => 'emergency-fund',
            ...$emergencyFund->compute($request->user()),
        ]);
    }
}
