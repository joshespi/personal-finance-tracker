<?php

namespace App\Http\Controllers;

use App\Services\AllocatorService;
use App\Services\DebtPayoffService;
use App\Services\EmergencyFundService;
use App\Services\RetirementProjectionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __invoke(
        Request $request,
        DebtPayoffService $debtPayoff,
        EmergencyFundService $emergencyFund,
        AllocatorService $allocator,
        RetirementProjectionService $retirement,
    ): View {
        return match ($request->input('tab', 'debt-payoff')) {
            'allocator'      => $this->allocator($request, $allocator),
            'emergency-fund' => $this->emergencyFund($request, $emergencyFund),
            'retirement'     => $this->retirement($request, $retirement),
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

    private function retirement(Request $request, RetirementProjectionService $retirement): View
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

        $birthYear      = $request->filled('birth_year') ? (int) $request->input('birth_year') : null;
        $retirementAge  = (int) $request->input('retirement_age', 65);
        $currentValue   = (float) $request->input('current_value', $defaultValue);
        $monthlyContrib = (float) $request->input('monthly_contrib', 0);
        $annualReturn   = (float) $request->input('annual_return', 7.0);
        $annualExpenses = $request->filled('annual_expenses') ? (float) $request->input('annual_expenses') : null;

        return view('planning', [
            'tab'          => 'retirement',
            'defaultValue' => $defaultValue,
            ...$retirement->compute(
                $user, $birthYear, $retirementAge, $currentValue, $monthlyContrib, $annualReturn, $annualExpenses
            ),
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
