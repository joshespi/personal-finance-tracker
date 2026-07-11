<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\ActivityLog;
use App\Models\Portfolio;
use App\Services\BenchmarkService;
use App\Services\RealizedGainService;
use App\Support\Rebalancing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(Request $request): View
    {
        $all = $request->user()
            ->portfolios()
            ->withCount(['transactions', 'manualAssets'])
            ->orderBy('name')
            ->get();

        $portfolios       = $all->whereNull('closed_at')->values();
        $closedPortfolios = $all->whereNotNull('closed_at')->values();

        return view('portfolios.index', compact('portfolios', 'closedPortfolios'));
    }

    public function create(): View
    {
        return view('portfolios.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $portfolio = $request->user()->portfolios()->create($this->validatePayload($request));

        ActivityLog::record('portfolio.created', $portfolio, ['name' => $portfolio->name]);

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio created.');
    }

    public function show(Request $request, Portfolio $portfolio): View
    {
        $this->authorize('view', $portfolio);

        $portfolio->load(['transactions.asset.latestPrice', 'manualAssets.latestValuation', 'manualAssets.proxyAsset.latestPrice', 'snapshots', 'slices.asset.latestPrice']);

        $holdings = $portfolio->computeHoldings();

        $request->user()->applyAssetClassifications($holdings->pluck('asset'));

        $incomeByAsset = $portfolio->transactions
            ->filter(fn ($t) => $t->type === TransactionType::Dividend)
            ->groupBy('asset_id')
            ->map(fn ($txns) => [
                'asset'        => $txns->first()->asset,
                'total_income' => round($txns->sum(fn ($t) => $t->dividendValue()), 2),
            ])
            ->values();

        $chartData = $portfolio->snapshots
            ->sortBy('recorded_on')
            ->map(fn ($s) => [
                'date'  => $s->recorded_on->format('Y-m-d'),
                'value' => round((float) $s->market_value + (float) $s->manual_value, 2),
                'cost'  => round((float) $s->cost_basis, 2),
            ])->values();

        $gainService   = new RealizedGainService;
        $realizedGains = $gainService->compute($portfolio);
        $twr           = $gainService->computeTwr($portfolio);
        $benchmarkData = (new BenchmarkService)->all();
        $allocation    = $this->buildAllocation($holdings, $portfolio);
        $rebalancing   = $this->buildSliceRebalancing($holdings, $portfolio);

        return view('portfolios.show', compact(
            'portfolio', 'holdings', 'incomeByAsset', 'chartData',
            'realizedGains', 'twr', 'benchmarkData', 'allocation', 'rebalancing'
        ));
    }

    public function edit(Request $request, Portfolio $portfolio): View
    {
        $this->authorize('update', $portfolio);

        return view('portfolios.edit', compact('portfolio'));
    }

    public function update(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->update($this->validatePayload($request));

        ActivityLog::record('portfolio.updated', $portfolio, ['name' => $portfolio->name]);

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio updated.');
    }

    public function destroy(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('delete', $portfolio);

        ActivityLog::record('portfolio.deleted', null, ['name' => $portfolio->name]);

        $portfolio->delete();

        return redirect()->route('portfolios.index')->with('success', 'Portfolio deleted.');
    }

    public function close(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        if (! $portfolio->isClosed()) {
            $portfolio->closed_at = now();
            $portfolio->save();
            ActivityLog::record('portfolio.closed', $portfolio, ['name' => $portfolio->name]);
        }

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio closed.');
    }

    public function reopen(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        if ($portfolio->isClosed()) {
            $portfolio->closed_at = null;
            $portfolio->save();
            ActivityLog::record('portfolio.reopened', $portfolio, ['name' => $portfolio->name]);
        }

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio reopened.');
    }

    public function updateChartVisibility(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $enabled = collect($request->input('include', []))->map('intval')->all();

        $portfolio->manualAssets()->whereIn('id', $enabled)->update(['include_in_chart' => true]);
        $portfolio->manualAssets()->whereNotIn('id', $enabled)->update(['include_in_chart' => false]);

        return redirect()->route('portfolios.show', $portfolio);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'currency'          => ['required', 'string', 'size:3'],
            'is_tax_advantaged' => ['boolean'],
        ]);

        $validated['description']       = $validated['description'] ?? null;
        $validated['is_tax_advantaged'] = $request->boolean('is_tax_advantaged');

        return $validated;
    }

    private function buildAllocation(Collection $holdings, Portfolio $portfolio): array
    {
        $byHolding = $holdings->map(fn ($h) => [
            'symbol' => $h['asset']->symbol,
            'value'  => round($h['effective_value'], 2),
            'type'   => $h['asset']->asset_type,
        ])->sortByDesc('value')->values();

        $manualValue = $portfolio->chartManualValue();

        $total = $byHolding->sum('value') + $manualValue;

        return [
            'holdings'     => $byHolding,
            'manual_value' => round($manualValue, 2),
            'total'        => round($total, 2),
        ];
    }

    private function buildSliceRebalancing(Collection $holdings, Portfolio $portfolio): array
    {
        $slices = $portfolio->slices;

        if ($slices->isEmpty()) {
            return [];
        }

        $holdingsByAssetId = $holdings->keyBy(fn ($h) => $h['asset']->id);

        $total = $holdings->sum('effective_value') + $portfolio->chartManualValue();

        if ($total <= 0) {
            return [];
        }

        $rows = [];
        foreach ($slices as $slice) {
            $holding = $holdingsByAssetId->get($slice->asset_id);

            $rows[] = ['symbol' => $slice->asset->symbol]
                + Rebalancing::driftRow((float) ($holding['effective_value'] ?? 0), (float) $slice->target_pct, $total)
                + ['slice_id' => $slice->id];
        }

        usort($rows, fn ($a, $b) => $b['target_pct'] <=> $a['target_pct']);

        return $rows;
    }
}
