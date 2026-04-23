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

        // Aggregate holdings across all portfolios by asset symbol
        $allHoldings = $portfolioHoldings
            ->flatMap(fn ($ph) => $ph['holdings']->all())
            ->groupBy(fn ($h) => $h['asset']->symbol)
            ->map(function ($group) {
                $first      = $group->first();
                $totalQty   = round($group->sum('quantity'), 8);
                $totalCost  = round($group->sum('total_cost'), 2);
                $price      = $group->first(fn ($h) => $h['current_price'] !== null)['current_price'] ?? null;
                $value      = $price !== null ? round($totalQty * $price, 2) : null;
                $unrealized = $value !== null ? round($value - $totalCost, 2) : null;

                return [
                    'asset'           => $first['asset'],
                    'quantity'        => $totalQty,
                    'total_cost'      => $totalCost,
                    'current_price'   => $price,
                    'current_value'   => $value,
                    'unrealized_gain' => $unrealized,
                ];
            })
            ->filter(fn ($h) => $h['quantity'] > 0)
            ->sortByDesc(fn ($h) => $h['current_value'] ?? $h['total_cost'])
            ->values();

        return view('dashboard', compact('summaries', 'totals', 'chartLabels', 'chartData', 'allHoldings'));
    }
}
