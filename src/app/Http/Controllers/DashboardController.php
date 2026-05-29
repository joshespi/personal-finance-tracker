<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use App\Services\BenchmarkService;
use App\Services\BudgetRuleService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, BudgetRuleService $budgetRule): View
    {
        $user       = $request->user();
        $portfolios = $user->portfolios()
            ->with(['transactions.asset.latestPrice', 'manualAssets.latestValuation', 'manualAssets.proxyAsset.latestPrice'])
            ->get();

        $rawSnapshots = PortfolioSnapshot::whereIn('portfolio_id', $portfolios->pluck('id'))
            ->selectRaw('recorded_on, SUM(market_value) as mv, SUM(manual_value) as manv, SUM(cost_basis) as cb')
            ->groupBy('recorded_on')
            ->orderBy('recorded_on')
            ->get()
            ->mapWithKeys(fn ($s) => [$s->recorded_on->toDateString() => [
                'value'        => round((float) $s->mv + (float) $s->manv, 2),
                'market_value' => round((float) $s->mv, 2),
                'cost'         => round((float) $s->cb, 2),
            ]]);

        $chartData = $rawSnapshots->map(fn ($v, $date) => ['date' => $date, 'value' => $v['value'], 'cost' => $v['cost']])
            ->values();

        $chartDataExManual = $rawSnapshots->map(fn ($v, $date) => ['date' => $date, 'value' => $v['market_value'], 'cost' => $v['cost']])
            ->values();

        $benchmarkData = (new BenchmarkService())->all();

        $portfolioHoldings = $portfolios->map(fn ($p) => [
            'portfolio' => $p,
            'holdings'  => $p->computeHoldings(),
        ]);

        $summaries = $portfolioHoldings->map(function ($ph) {
            $portfolio = $ph['portfolio'];
            $holdings  = $ph['holdings'];

            $costBasis    = $holdings->sum('total_cost') + $portfolio->manualAssets->where('include_in_chart', true)->sum(fn ($ma) => (float) $ma->cost_basis);
            $marketValue  = $holdings->filter(fn ($h) => $h['current_value'] !== null)->sum('current_value');
            $unpricedCost = $holdings->filter(fn ($h) => $h['current_value'] === null)->sum('total_cost');
            $manualValue  = $portfolio->chartManualValue();
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
        })->sortByDesc('total_value')->values();

        $portfolioValue   = round($summaries->sum('total_value'), 2);
        $totalCash        = round($user->totalCash(), 2);
        $totalAssets      = round($portfolioValue + $totalCash, 2);
        $userLiabilities  = $user->liabilities()->with('latestBalance')->get();
        $totalDebt        = round($userLiabilities->sum(fn ($l) => $l->currentBalance()), 2);

        $readyToAssign = $user->readyToAssign();

        [$revolvingBalance, $interestBleedMonthly] = $userLiabilities
            ->filter(fn ($l) => $l->isRevolving() && $l->currentBalance() > 0)
            ->reduce(function ($carry, $l) {
                $bal = $l->currentBalance();
                $carry[0] += $bal;
                $carry[1] += $bal * ((float) ($l->interest_rate ?? 0) / 100 / 12);
                return $carry;
            }, [0.0, 0.0]);
        $revolvingBalance     = round($revolvingBalance, 2);
        $interestBleedMonthly = round($interestBleedMonthly, 2);
        $interestBleedYearly  = round($interestBleedMonthly * 12, 2);

        $totals = [
            'cost_basis'      => round($summaries->sum('cost_basis'), 2),
            'market_value'    => $summaries->contains(fn ($s) => $s['market_value'] !== null)
                ? round($summaries->sum(fn ($s) => $s['market_value'] ?? $s['cost_basis']), 2)
                : null,
            'manual_value'    => round($summaries->sum('manual_value'), 2),
            'unrealized'      => $summaries->contains(fn ($s) => $s['unrealized'] !== null)
                ? round($summaries->sum(fn ($s) => $s['unrealized'] ?? 0), 2)
                : null,
            'portfolio_value' => $portfolioValue,
            'total_value'     => $totalAssets,
            'total_debt'      => $totalDebt,
            'net_worth'       => round($totalAssets - $totalDebt, 2),
            'debt_to_asset'   => $totalAssets > 0 ? round($totalDebt / $totalAssets * 100, 1) : null,
        ];

        $allHoldings = $portfolioHoldings
            ->flatMap(fn ($ph) => $ph['holdings']->map(fn ($h) => $h + ['_portfolio' => $ph['portfolio']]))
            ->groupBy(fn ($h) => $h['asset']->symbol)
            ->map(function ($group) {
                $first      = $group->first();
                $totalQty   = round($group->sum('quantity'), 8);
                $totalCost  = round($group->sum('total_cost'), 2);
                $price      = $group->first(fn ($h) => $h['current_price'] !== null)['current_price'] ?? null;
                $value      = $price !== null ? round($totalQty * $price, 2) : null;
                $unrealized = $value !== null ? round($value - $totalCost, 2) : null;

                $portfolios = $group->map(fn ($h) => [
                    'id'    => $h['_portfolio']->id,
                    'name'  => $h['_portfolio']->name,
                    'qty'   => round((float) $h['quantity'], 8),
                    'cost'  => round((float) $h['total_cost'], 2),
                    'value' => $price !== null ? round((float) $h['quantity'] * $price, 2) : null,
                ])->values()->all();

                return [
                    'asset'           => $first['asset'],
                    'quantity'        => $totalQty,
                    'total_cost'      => $totalCost,
                    'current_price'   => $price,
                    'current_value'   => $value,
                    'effective_value' => $value ?? $totalCost,
                    'unrealized_gain' => $unrealized,
                    'portfolios'      => $portfolios,
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

        $manualBuckets = $this->manualAssetBuckets($portfolios);
        $allocation    = $this->buildAllocation($allHoldings, $manualBuckets);
        $rebalancing   = $this->buildGlobalRebalancing($allHoldings, $manualBuckets, $user);

        $budgetRuleData = $budgetRule->compute($user);

        $monthlySpend = $budgetRuleData['monthly_mandatory'] + $budgetRuleData['monthly_discretionary'];
        $ageOfMoney   = ($budgetRuleData['has_data'] && $monthlySpend > 0)
            ? max(0, (int) round($totalCash / $monthlySpend * 30))
            : null;

        return view('dashboard', compact(
            'summaries', 'totals', 'chartData', 'chartDataExManual', 'allHoldings', 'allocation', 'rebalancing', 'benchmarkData', 'budgetRuleData',
            'revolvingBalance', 'interestBleedMonthly', 'interestBleedYearly',
            'totalCash', 'readyToAssign', 'ageOfMoney'
        ));
    }

    private function manualAssetBuckets(Collection $portfolios): array
    {
        $buckets = ['stock' => 0.0, 'crypto' => 0.0, 'real_estate' => 0.0, 'bond' => 0.0, 'other' => 0.0];

        foreach ($portfolios as $p) {
            foreach ($p->manualAssets->where('include_in_chart', true) as $ma) {
                $key = array_key_exists($ma->asset_class, $buckets) ? $ma->asset_class : 'other';
                $buckets[$key] += $ma->currentValue();
            }
        }

        return $buckets;
    }

    private function buildAllocation(Collection $allHoldings, array $manualBuckets): array
    {
        $stockValue      = 0.0;
        $cryptoValue     = 0.0;
        $realEstateValue = 0.0;
        $bondValue       = 0.0;

        foreach ($allHoldings as $h) {
            $val = $h['effective_value'];
            match (AssetType::tryFrom($h['asset']->asset_type)?->allocationKey() ?? 'stock') {
                'crypto'      => $cryptoValue     += $val,
                'real_estate' => $realEstateValue += $val,
                'bond'        => $bondValue        += $val,
                default       => $stockValue      += $val,
            };
        }

        $stockValue      += $manualBuckets['stock'];
        $cryptoValue     += $manualBuckets['crypto'];
        $realEstateValue += $manualBuckets['real_estate'];
        $bondValue       += $manualBuckets['bond'];
        $otherManualValue = $manualBuckets['other'];

        $total = $stockValue + $cryptoValue + $realEstateValue + $bondValue + $otherManualValue;

        $entries = collect([
            ['label' => 'Stocks',       'color' => '#6366f1', 'value' => round($stockValue, 2)],
            ['label' => 'Crypto',       'color' => '#f97316', 'value' => round($cryptoValue, 2)],
            ['label' => 'Real Estate',  'color' => '#b45309', 'value' => round($realEstateValue, 2)],
            ['label' => 'Bonds',        'color' => '#eab308', 'value' => round($bondValue, 2)],
            ['label' => 'Other Assets', 'color' => '#10b981', 'value' => round($otherManualValue, 2)],
        ])->sortByDesc('value');

        return [
            'labels' => $entries->pluck('label')->values()->all(),
            'colors' => $entries->pluck('color')->values()->all(),
            'values' => $entries->pluck('value')->values()->all(),
            'total'  => round($total, 2),
        ];
    }

    private function buildGlobalRebalancing(Collection $allHoldings, array $manualBuckets, User $user): array
    {
        $targets = [
            'stock'       => (int) $user->target_stock_pct,
            'crypto'      => (int) $user->target_crypto_pct,
            'real_estate' => (int) $user->target_real_estate_pct,
            'bond'        => (int) $user->target_bond_pct,
        ];

        if (array_sum($targets) === 0) {
            return [];
        }

        $current = ['stock' => 0.0, 'crypto' => 0.0, 'real_estate' => 0.0, 'bond' => 0.0, 'other' => 0.0];

        foreach ($allHoldings as $h) {
            $val  = (float) $h['effective_value'];
            $type = AssetType::tryFrom($h['asset']->asset_type)?->allocationKey() ?? 'other';
            $current[$type] += $val;
        }

        foreach ($manualBuckets as $type => $val) {
            $current[array_key_exists($type, $current) ? $type : 'other'] += $val;
        }

        $total = array_sum($current);
        if ($total <= 0) {
            return [];
        }

        $labels = ['stock' => 'Stocks', 'crypto' => 'Crypto', 'real_estate' => 'Real Estate', 'bond' => 'Bonds'];

        $rows = [];
        foreach ($targets as $type => $targetPct) {
            $currentVal  = round($current[$type], 2);
            $targetVal   = round($total * $targetPct / 100, 2);
            $currentPct  = round($currentVal / $total * 100, 1);
            $diff        = round($targetVal - $currentVal, 2);
            $rows[]      = [
                'label'      => $labels[$type],
                'current_pct' => $currentPct,
                'target_pct'  => $targetPct,
                'current_val' => $currentVal,
                'target_val'  => $targetVal,
                'diff'        => $diff,
                'drift_pct'   => round($currentPct - $targetPct, 1),
            ];
        }

        return $rows;
    }
}
