<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSnapshot;
use App\Services\BenchmarkService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $portfolios = $request->user()
            ->portfolios()
            ->with(['transactions.asset.latestPrice', 'manualAssets.latestValuation'])
            ->get();

        // All snapshot history (no date limit — let the frontend filter by range)
        $rawSnapshots = PortfolioSnapshot::whereIn('portfolio_id', $portfolios->pluck('id'))
            ->orderBy('recorded_on')
            ->get(['portfolio_id', 'recorded_on', 'market_value', 'manual_value', 'cost_basis'])
            ->groupBy(fn ($s) => $s->recorded_on->toDateString())
            ->map(fn ($group) => [
                'value'        => round($group->sum(fn ($s) => (float) $s->market_value + (float) $s->manual_value), 2),
                'market_value' => round($group->sum(fn ($s) => (float) $s->market_value), 2),
                'cost'         => round($group->sum(fn ($s) => (float) $s->cost_basis), 2),
            ])
            ->sortKeys();

        $chartData = $rawSnapshots->map(fn ($v, $date) => ['date' => $date, 'value' => $v['value'], 'cost' => $v['cost']])
            ->values();

        $chartDataExManual = $rawSnapshots->map(fn ($v, $date) => ['date' => $date, 'value' => $v['market_value'], 'cost' => $v['cost']])
            ->values();

        $benchmarkData = (new BenchmarkService())->all();

        // Compute per-portfolio holdings once and reuse
        $portfolioHoldings = $portfolios->map(fn ($p) => [
            'portfolio' => $p,
            'holdings'  => $p->computeHoldings(),
        ]);

        $summaries = $portfolioHoldings->map(function ($ph) {
            $portfolio = $ph['portfolio'];
            $holdings  = $ph['holdings'];

            $costBasis    = $holdings->sum('total_cost');
            $marketValue  = $holdings->filter(fn ($h) => $h['current_value'] !== null)->sum('current_value');
            $unpricedCost = $holdings->filter(fn ($h) => $h['current_value'] === null)->sum('total_cost');
            $manualValue  = $portfolio->manualAssets->sum(fn ($ma) => $ma->currentValue());
            $unrealized = $holdings->filter(fn ($h) => $h['unrealized_gain'] !== null)->sum('unrealized_gain');
            $hasPrice   = $holdings->contains(fn ($h) => $h['current_value'] !== null);

            return [
                'portfolio'    => $portfolio,
                'cost_basis'   => round($costBasis, 2),
                'market_value' => $hasPrice ? round($marketValue + $unpricedCost, 2) : null,
                'manual_value' => round($manualValue, 2),
                'unrealized'   => $hasPrice ? round($unrealized, 2) : null,
                'total_value'  => round(($hasPrice ? $marketValue + $unpricedCost : $costBasis) + $manualValue, 2),
            ];
        });

        $totalCash   = round($request->user()->totalCash(), 2);
        $totalAssets = round($summaries->sum('total_value') + $totalCash, 2);
        $totalDebt   = round($request->user()->totalDebt(), 2);

        $totals = [
            'cost_basis'   => round($summaries->sum('cost_basis'), 2),
            'market_value' => $summaries->contains(fn ($s) => $s['market_value'] !== null)
                ? round($summaries->sum(fn ($s) => $s['market_value'] ?? $s['cost_basis']), 2)
                : null,
            'manual_value' => round($summaries->sum('manual_value'), 2),
            'unrealized'   => $summaries->contains(fn ($s) => $s['unrealized'] !== null)
                ? round($summaries->sum(fn ($s) => $s['unrealized'] ?? 0), 2)
                : null,
            'total_value'  => $totalAssets,
            'total_debt'   => $totalDebt,
            'net_worth'    => round($totalAssets - $totalDebt, 2),
        ];

        // Aggregate holdings across all portfolios by asset symbol
        $allHoldings = $portfolioHoldings
            ->flatMap(fn ($ph) => $ph['holdings']->all())
            ->groupBy(fn ($h) => $h['asset']->symbol)
            ->map(function ($group) {
                $first     = $group->first();
                $totalQty  = round($group->sum('quantity'), 8);
                $totalCost = round($group->sum('total_cost'), 2);
                $price     = $group->first(fn ($h) => $h['current_price'] !== null)['current_price'] ?? null;
                $value     = $price !== null ? round($totalQty * $price, 2) : null;
                $unrealized = $value !== null ? round($value - $totalCost, 2) : null;

                return [
                    'asset'           => $first['asset'],
                    'quantity'        => $totalQty,
                    'total_cost'      => $totalCost,
                    'current_price'   => $price,
                    'current_value'   => $value,
                    'effective_value' => $value ?? $totalCost,
                    'unrealized_gain' => $unrealized,
                ];
            })
            ->filter(fn ($h) => $h['quantity'] > 0)
            ->sortByDesc('effective_value')
            ->values();

        $allHoldingsTotal = $allHoldings->sum('effective_value');

        $allHoldings = $allHoldings->map(function ($h) use ($allHoldingsTotal) {
            $h['pct'] = $allHoldingsTotal > 0 ? round($h['effective_value'] / $allHoldingsTotal * 100, 2) : 0;
            return $h;
        });

        // Asset allocation breakdown for donut chart
        $allocation = $this->buildAllocation($allHoldings, $portfolios);

        return view('dashboard', compact(
            'summaries', 'totals', 'chartData', 'chartDataExManual', 'allHoldings', 'allocation', 'benchmarkData'
        ));
    }

    private function buildAllocation($allHoldings, $portfolios): array
    {
        $stockValue      = 0.0;
        $cryptoValue     = 0.0;
        $realEstateValue = 0.0;

        foreach ($allHoldings as $h) {
            $val = $h['effective_value'];
            match ($h['asset']->asset_type) {
                'crypto'      => $cryptoValue     += $val,
                'real_estate' => $realEstateValue += $val,
                default       => $stockValue      += $val,
            };
        }

        $otherManualValue = 0.0;
        foreach ($portfolios as $p) {
            foreach ($p->manualAssets as $ma) {
                $val = $ma->currentValue();
                if ($ma->asset_class === 'real_estate') {
                    $realEstateValue += $val;
                } else {
                    $otherManualValue += $val;
                }
            }
        }

        $total = $stockValue + $cryptoValue + $realEstateValue + $otherManualValue;

        return [
            'labels' => ['Stocks', 'Crypto', 'Real Estate', 'Other Assets'],
            'colors' => ['#6366f1', '#f97316', '#8b5cf6', '#10b981'],
            'values' => [
                round($stockValue, 2),
                round($cryptoValue, 2),
                round($realEstateValue, 2),
                round($otherManualValue, 2),
            ],
            'total'  => round($total, 2),
        ];
    }
}
