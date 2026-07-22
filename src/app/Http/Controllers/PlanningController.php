<?php

namespace App\Http\Controllers;

use App\Services\AllocatorService;
use App\Services\DebtPayoffService;
use App\Services\EmergencyFundService;
use App\Services\ForecastService;
use App\Services\RetirementProjectionService;
use Illuminate\Http\JsonResponse;
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
        ForecastService $forecast,
    ): View {
        return match ($request->input('tab', 'debt-payoff')) {
            'allocator'      => $this->allocator($request, $allocator),
            'emergency-fund' => $this->emergencyFund($request, $emergencyFund),
            'retirement'     => $this->retirement($request, $retirement, $forecast),
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

    /**
     * Backs the debt-payoff tab's "extra payment" what-if slider — re-runs
     * DebtPayoffService::simulate() at the requested extra payment so the live figures
     * come from the same simulation as the initial page render, instead of a separate
     * client-side reimplementation.
     */
    public function resimulateDebtPayoff(Request $request, DebtPayoffService $service): JsonResponse
    {
        $request->validate(['extra_payment' => ['nullable', 'numeric', 'min:0', 'max:10000000']]);

        return response()->json($service->resimulate($request->user(), (float) $request->input('extra_payment', 0)));
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

    /**
     * "Retirement" tab has two lenses on the same "will my money be enough" question,
     * toggled by ?mode=: the age-based 4%-rule target (default) and the FIRE Forecast
     * wealth trajectory (formerly the standalone /forecast page — see the redirect in
     * routes/web.php). Both are computed on every request regardless of the active
     * mode — cheap, in-memory projections — so switching modes client-side never needs
     * a second round-trip, and each keeps its own contextually-correct starting-value
     * default (portfolio value for the retirement target; full net worth for the
     * trajectory) rather than being forced onto one shared number.
     */
    private function retirement(Request $request, RetirementProjectionService $retirement, ForecastService $forecast): View
    {
        $mode        = $request->input('mode') === 'trajectory' ? 'trajectory' : 'target';
        $currentYear = now()->year;

        $request->validate([
            'birth_year'      => ['nullable', 'integer', 'min:1930', 'max:'.($currentYear - 18)],
            'retirement_age'  => ['nullable', 'integer', 'min:40', 'max:90'],
            'current_value'   => ['nullable', 'numeric', 'min:0'],
            'monthly_contrib' => ['nullable', 'numeric', 'min:0'],
            'annual_return'   => ['nullable', 'numeric', 'min:0', 'max:30'],
            'annual_expenses' => ['nullable', 'numeric', 'min:0'],
            'starting_nw'     => ['nullable', 'numeric'],
            'monthly_savings' => ['nullable', 'numeric', 'min:0'],
            'fire_target'     => ['nullable', 'numeric', 'min:0'],
            'inflation_rate'  => ['nullable', 'numeric', 'min:0', 'max:20'],
            'years'           => ['nullable', 'integer'],
        ]);

        $user = $request->user();

        $defaultValue = round($user->latestPortfolioValue(), 2);

        $birthYear      = $request->filled('birth_year') ? (int) $request->input('birth_year') : null;
        $retirementAge  = (int) $request->input('retirement_age', 65);
        $currentValue   = (float) $request->input('current_value', $defaultValue);
        $monthlyContrib = (float) $request->input('monthly_contrib', 0);
        // Expected annual return is one shared assumption across both lenses (same
        // real-world question), so it's a single query param/variable rather than two.
        $annualReturn   = max(0, min(30, (float) $request->input('annual_return', 7.0)));
        $annualExpenses = $request->filled('annual_expenses') ? (float) $request->input('annual_expenses') : null;

        [$defaultStartNw, $defaultMonthlySavings] = $forecast->computeDefaults($user);

        $startingNw     = (float) $request->input('starting_nw', $defaultStartNw);
        $monthlySavings = (float) $request->input('monthly_savings', $defaultMonthlySavings);
        $inflationRate  = max(0, min(20, (float) $request->input('inflation_rate', 2.5)));
        $requestedYears = (int) $request->input('years', 30);
        $years          = in_array($requestedYears, [10, 20, 30, 40, 50]) ? $requestedYears : 30;
        $fireTarget     = $request->filled('fire_target') ? max(0, (float) $request->input('fire_target')) : null;

        return view('planning', [
            'tab'          => 'retirement',
            'mode'         => $mode,
            'defaultValue' => $defaultValue,
            ...$retirement->compute(
                $user, $birthYear, $retirementAge, $currentValue, $monthlyContrib, $annualReturn, $annualExpenses
            ),
            'defaultStartNw'        => $defaultStartNw,
            'defaultMonthlySavings' => $defaultMonthlySavings,
            ...$forecast->compute($startingNw, $monthlySavings, $annualReturn, $inflationRate, $years, $fireTarget),
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
