<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $portfolios = $request->user()
            ->portfolios()
            ->withCount('transactions')
            ->with('manualAssets.latestValuation')
            ->latest()
            ->get();

        return view('portfolios.index', compact('portfolios'));
    }

    public function create(): View
    {
        return view('portfolios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'currency'    => ['required', 'string', 'size:3'],
        ]);

        $portfolio = $request->user()->portfolios()->create($validated);

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio created.');
    }

    public function show(Request $request, Portfolio $portfolio): View
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $portfolio->load(['transactions.asset.latestPrice', 'manualAssets.latestValuation']);

        $holdings = $portfolio->computeHoldings();

        $incomeByAsset = $portfolio->transactions
            ->filter(fn ($t) => $t->type === 'dividend')
            ->groupBy('asset_id')
            ->map(fn ($txns) => [
                'asset'        => $txns->first()->asset,
                'total_income' => round($txns->sum(fn ($t) => (float) $t->quantity * (float) $t->price_per_unit), 2),
            ])
            ->values();

        $snapshots = $portfolio->snapshots()
            ->orderBy('recorded_on')
            ->get(['recorded_on', 'market_value', 'manual_value', 'cost_basis']);

        $chartData = $snapshots->map(fn ($s) => [
            'date'  => $s->recorded_on->format('Y-m-d'),
            'value' => round((float) $s->market_value + (float) $s->manual_value, 2),
            'cost'  => round((float) $s->cost_basis, 2),
        ])->values();

        return view('portfolios.show', compact('portfolio', 'holdings', 'incomeByAsset', 'chartData'));
    }

    public function edit(Request $request, Portfolio $portfolio): View
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        return view('portfolios.edit', compact('portfolio'));
    }

    public function update(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'currency'    => ['required', 'string', 'size:3'],
        ]);

        $portfolio->update($validated);

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio updated.');
    }

    public function destroy(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $portfolio->delete();

        return redirect()->route('portfolios.index')->with('success', 'Portfolio deleted.');
    }
}
