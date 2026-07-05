<?php

namespace App\Http\Controllers;

use App\Models\Pension;
use App\Services\PensionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PensionController extends Controller
{
    public function __construct(private PensionService $pensions) {}

    public function index(Request $request): View
    {
        $user     = $request->user();
        $pensions = $user->pensions()->orderBy('name')->get();

        $overrides = $this->overrides($request);

        $computed = $pensions->map(fn (Pension $p) => [
            'pension' => $p,
            ...$this->pensions->compute($p, $overrides),
        ]);

        $incomeFloor = null;
        if ($computed->isNotEmpty()) {
            $pensionAnnual     = (float) $computed->sum('projectedAnnual');
            $yearsToRetirement = (int) $computed->first()['yearsToDraw'];
            $portfolioBase     = round($user->latestPortfolioValue() + $user->totalCash(), 2);

            $incomeFloor = $this->pensions->incomeFloor($portfolioBase, $pensionAnnual, $yearsToRetirement, $overrides);
        }

        return view('pensions.index', [
            'computed'    => $computed,
            'incomeFloor' => $incomeFloor,
            'overrides'   => $overrides,
        ]);
    }

    public function create(): View
    {
        return view('pensions.create', ['pension' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->pensions()->create($this->validatePayload($request));

        return redirect()->route('pensions.index')->with('success', 'Pension added.');
    }

    public function edit(Request $request, Pension $pension): View
    {
        $this->authorize('update', $pension);

        return view('pensions.edit', ['pension' => $pension]);
    }

    public function update(Request $request, Pension $pension): RedirectResponse
    {
        $this->authorize('update', $pension);

        $pension->update($this->validatePayload($request));

        return redirect()->route('pensions.index')->with('success', 'Pension updated.');
    }

    public function destroy(Request $request, Pension $pension): RedirectResponse
    {
        $this->authorize('delete', $pension);

        $pension->delete();

        return redirect()->route('pensions.index')->with('success', 'Pension deleted.');
    }

    /**
     * Sensitivity overrides read from the query string. Blank/absent inputs fall
     * back to each pension's stored config inside PensionService.
     *
     * @return array<string, mixed>
     */
    private function overrides(Request $request): array
    {
        $keys = [
            'retirement_age', 'life_expectancy', 'discount_rate',
            'expected_return', 'swr', 'annual_expenses', 'monthly_contribution',
        ];

        $overrides = [];
        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $overrides[$key] = (float) $request->input($key);
            }
        }

        return $overrides;
    }

    private function validatePayload(Request $request): array
    {
        $currentYear = now()->year;

        $validated = $request->validate([
            'name'                     => ['required', 'string', 'max:200'],
            'plan_label'               => ['nullable', 'string', 'max:200'],
            'membership_date'          => ['nullable', 'date'],
            'service_credit_years'     => ['required', 'numeric', 'gte:0', 'lte:100'],
            'service_cap_years'        => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'multiplier_pct'           => ['required', 'numeric', 'gt:0', 'lte:10'],
            'final_average_salary'     => ['required', 'numeric', 'gte:0'],
            'salary_growth_pct'        => ['nullable', 'numeric', 'gte:0', 'lte:20'],
            'cola_pct'                 => ['nullable', 'numeric', 'gte:0', 'lte:20'],
            'monthly_benefit_estimate' => ['nullable', 'numeric', 'gte:0'],
            'birth_year'               => ['nullable', 'integer', 'min:1930', 'max:'.($currentYear - 18)],
            'retirement_age'           => ['required', 'integer', 'min:40', 'max:90'],
            'life_expectancy_age'      => ['required', 'integer', 'min:50', 'max:110'],
            'discount_rate_pct'        => ['required', 'numeric', 'gte:0', 'lte:10'],
            'notes'                    => ['nullable', 'string', 'max:1000'],
            'currency'                 => ['required', 'string', 'size:3'],
        ]);

        foreach (['plan_label', 'service_cap_years', 'monthly_benefit_estimate', 'birth_year', 'notes'] as $optional) {
            $validated[$optional] = $request->filled($optional) ? $validated[$optional] : null;
        }

        $validated['salary_growth_pct']    = $request->filled('salary_growth_pct') ? (float) $request->input('salary_growth_pct') : 0;
        $validated['cola_pct']             = $request->filled('cola_pct') ? (float) $request->input('cola_pct') : 0;
        $validated['include_in_net_worth'] = $request->boolean('include_in_net_worth');

        return $validated;
    }
}
