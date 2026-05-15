<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Portfolio;
use App\Services\BenchmarkService;
use App\Services\RealizedGainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            'name'               => ['required', 'string', 'max:100'],
            'description'        => ['nullable', 'string', 'max:1000'],
            'currency'           => ['required', 'string', 'size:3'],
            'is_tax_advantaged'  => ['boolean'],
        ]);

        $validated['is_tax_advantaged'] = $request->boolean('is_tax_advantaged');

        $portfolio = $request->user()->portfolios()->create($validated);

        ActivityLog::record('portfolio.created', $portfolio, ['name' => $portfolio->name]);

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio created.');
    }

    public function show(Request $request, Portfolio $portfolio): View
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $portfolio->load(['transactions.asset.latestPrice', 'manualAssets.latestValuation', 'snapshots']);

        $holdings = $portfolio->computeHoldings();

        $incomeByAsset = $portfolio->transactions
            ->filter(fn ($t) => $t->type === 'dividend')
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

        $gainService   = new RealizedGainService();
        $realizedGains = $gainService->compute($portfolio);
        $twr           = $gainService->computeTwr($portfolio);
        $benchmarkData = (new BenchmarkService())->all();
        $allocation    = $this->buildAllocation($holdings, $portfolio);
        $rebalancing   = $this->buildRebalancing($holdings, $portfolio);

        return view('portfolios.show', compact(
            'portfolio', 'holdings', 'incomeByAsset', 'chartData',
            'realizedGains', 'twr', 'benchmarkData', 'allocation', 'rebalancing'
        ));
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
            'name'                   => ['required', 'string', 'max:100'],
            'description'            => ['nullable', 'string', 'max:1000'],
            'currency'               => ['required', 'string', 'size:3'],
            'is_tax_advantaged'      => ['boolean'],
            'target_stock_pct'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'target_crypto_pct'      => ['nullable', 'integer', 'min:0', 'max:100'],
            'target_real_estate_pct' => ['nullable', 'integer', 'min:0', 'max:100'],
            'target_bond_pct'        => ['nullable', 'integer', 'min:0', 'max:100'],
            'target_manual_pct'      => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $portfolio->update([
            'name'                   => $validated['name'],
            'description'            => $validated['description'] ?? null,
            'currency'               => $validated['currency'],
            'is_tax_advantaged'      => $request->boolean('is_tax_advantaged'),
            'target_stock_pct'       => $validated['target_stock_pct'] ?? 0,
            'target_crypto_pct'      => $validated['target_crypto_pct'] ?? 0,
            'target_real_estate_pct' => $validated['target_real_estate_pct'] ?? 0,
            'target_bond_pct'        => $validated['target_bond_pct'] ?? 0,
            'target_manual_pct'      => $validated['target_manual_pct'] ?? 0,
        ]);

        ActivityLog::record('portfolio.updated', $portfolio, ['name' => $portfolio->name]);

        return redirect()->route('portfolios.show', $portfolio)->with('success', 'Portfolio updated.');
    }

    public function destroy(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        ActivityLog::record('portfolio.deleted', null, ['name' => $portfolio->name]);

        $portfolio->delete();

        return redirect()->route('portfolios.index')->with('success', 'Portfolio deleted.');
    }

    private function buildAllocation(Collection $holdings, Portfolio $portfolio): array
    {
        $byHolding = $holdings->map(fn ($h) => [
            'symbol' => $h['asset']->symbol,
            'value'  => round($h['effective_value'], 2),
            'type'   => $h['asset']->asset_type,
        ])->sortByDesc('value')->values();

        $manualValue = $portfolio->manualAssets->sum(fn ($ma) => $ma->currentValue());

        $total = $byHolding->sum('value') + $manualValue;

        return [
            'holdings'     => $byHolding,
            'manual_value' => round($manualValue, 2),
            'total'        => round($total, 2),
        ];
    }

    private function buildRebalancing(Collection $holdings, Portfolio $portfolio): array
    {
        $targets = [
            'stock'       => $portfolio->target_stock_pct,
            'crypto'      => $portfolio->target_crypto_pct,
            'real_estate' => $portfolio->target_real_estate_pct,
            'bond'        => $portfolio->target_bond_pct,
            'manual'      => $portfolio->target_manual_pct,
        ];

        $totalTargetPct = array_sum($targets);
        if ($totalTargetPct === 0) {
            return [];
        }

        $stockValue      = $holdings->where(fn ($h) => $h['asset']->asset_type === 'stock')->sum('effective_value');
        $cryptoValue     = $holdings->where(fn ($h) => $h['asset']->asset_type === 'crypto')->sum('effective_value');
        $realEstateValue = $holdings->where(fn ($h) => $h['asset']->asset_type === 'real_estate')->sum('effective_value');
        $bondValue       = $holdings->where(fn ($h) => $h['asset']->asset_type === 'bond')->sum('effective_value');
        $manualValue     = $portfolio->manualAssets->sum(fn ($ma) => $ma->currentValue());

        $current = [
            'stock'       => round($stockValue, 2),
            'crypto'      => round($cryptoValue, 2),
            'real_estate' => round($realEstateValue, 2),
            'bond'        => round($bondValue, 2),
            'manual'      => round($manualValue, 2),
        ];

        $total = array_sum($current);
        if ($total <= 0) {
            return [];
        }

        $currentPct = array_map(fn ($v) => round($v / $total * 100, 1), $current);

        $labels = [
            'stock'       => 'Stocks',
            'crypto'      => 'Crypto',
            'real_estate' => 'Real Estate',
            'bond'        => 'Bonds',
            'manual'      => 'Manual Assets',
        ];

        $rows = [];
        foreach (array_keys($targets) as $type) {
            $targetValue = round($total * $targets[$type] / 100, 2);
            $diff        = round($targetValue - $current[$type], 2);
            $rows[]      = [
                'type'         => $type,
                'label'        => $labels[$type],
                'current_pct'  => $currentPct[$type],
                'target_pct'   => $targets[$type],
                'current_val'  => $current[$type],
                'target_val'   => $targetValue,
                'diff'         => $diff,
            ];
        }

        return $rows;
    }
}
