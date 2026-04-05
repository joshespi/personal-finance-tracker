<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSnapshot;
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

        // 90-day snapshot history for the chart (total value across all portfolios per day)
        $snapshots = PortfolioSnapshot::whereIn('portfolio_id', $portfolios->pluck('id'))
            ->where('recorded_on', '>=', now()->subDays(90)->toDateString())
            ->orderBy('recorded_on')
            ->get()
            ->groupBy(fn ($s) => $s->recorded_on->toDateString())
            ->map(fn ($group) => round($group->sum(fn ($s) => (float) $s->market_value + (float) $s->manual_value), 2))
            ->sortKeys();

        $chartLabels = $snapshots->keys()->values();
        $chartData   = $snapshots->values();

        $summaries = $portfolios->map(function ($portfolio) {
            $holdings = $portfolio->computeHoldings();

            $costBasis    = $holdings->sum('total_cost');
            $marketValue  = $holdings->filter(fn ($h) => $h['current_value'] !== null)->sum('current_value');
            $unpricedCost = $holdings->filter(fn ($h) => $h['current_value'] === null)->sum('total_cost');
            $manualValue  = $portfolio->manualAssets->sum(
                fn ($ma) => $ma->latestValuation ? (float) $ma->latestValuation->value : 0
            );
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

        $totals = [
            'cost_basis'   => round($summaries->sum('cost_basis'), 2),
            'market_value' => $summaries->contains(fn ($s) => $s['market_value'] !== null)
                ? round($summaries->sum(fn ($s) => $s['market_value'] ?? $s['cost_basis']), 2)
                : null,
            'manual_value' => round($summaries->sum('manual_value'), 2),
            'unrealized'   => $summaries->contains(fn ($s) => $s['unrealized'] !== null)
                ? round($summaries->sum(fn ($s) => $s['unrealized'] ?? 0), 2)
                : null,
            'total_value'  => round($summaries->sum('total_value'), 2),
        ];

        return view('dashboard', compact('summaries', 'totals', 'chartLabels', 'chartData'));
    }
}
