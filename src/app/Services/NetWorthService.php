<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Enums\DashboardWidget;
use App\Models\PortfolioSnapshot;
use App\Models\User;
use App\Support\Rebalancing;
use Illuminate\Support\Collection;

class NetWorthService
{
    /** Synthetic allocation bucket for manual asset classes / asset types outside AssetType. */
    private const OTHER_BUCKET = 'other';

    private const OTHER_COLOR = '#10b981';

    public function __construct(
        private BudgetRuleService $budgetRule,
        private PensionService $pensionService,
        private BenchmarkService $benchmarkService,
    ) {}

    /**
     * Compute the full net-worth/allocation/rebalancing picture for a user —
     * everything the dashboard view needs, keyed the same way as the view's
     * expected variables.
     */
    public function compute(User $user): array
    {
        $portfolios = $user->portfolios()
            ->active()
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

        // Per-widget visibility map (value => bool) so the view can gate each
        // section/tile without re-hitting the model, and so hidden widgets' data
        // is never computed. Missing = visible by default.
        $prefs = $user->dashboard_preferences ?? [];
        $show  = [];
        foreach (DashboardWidget::cases() as $widget) {
            $show[$widget->value] = $prefs[$widget->value] ?? true;
        }

        $benchmarkData = $show[DashboardWidget::Benchmark->value] ? $this->benchmarkService->all() : [];

        $portfolioHoldings = $portfolios->map(fn ($p) => [
            'portfolio' => $p,
            'holdings'  => $p->computeHoldings(),
        ]);

        $summaries = $portfolioHoldings
            ->map(fn ($ph) => ['portfolio' => $ph['portfolio']] + $ph['portfolio']->summarizeHoldings($ph['holdings']))
            ->sortByDesc('total_value')->values();

        $portfolioValue = round($summaries->sum('total_value'), 2);
        $totalCash      = round($user->totalCash(), 2);
        $totalAssets    = round($portfolioValue + $totalCash, 2);

        // Invested = portfolio value minus manual assets flagged out (e.g. primary residence).
        // Only subtract ones already counted in portfolio_value, i.e. include_in_chart=true.
        $excludedFromInvested = $portfolios->flatMap->manualAssets
            ->where('include_in_chart', true)
            ->where('include_in_invested', false)
            ->sum(fn ($ma) => $ma->currentValue());
        $investedValue   = round($portfolioValue - $excludedFromInvested, 2);
        $userLiabilities = $user->liabilities()->with('latestBalance')->get();
        $totalDebt       = round($userLiabilities->sum(fn ($l) => $l->currentBalance()), 2);

        $readyToAssign = $user->readyToAssign();

        $revolving            = $userLiabilities->filter(fn ($l) => $l->isRevolving() && $l->currentBalance() > 0);
        $revolvingBalance     = round($revolving->sum(fn ($l) => $l->currentBalance()), 2);
        $interestBleedMonthly = round($revolving->sum(fn ($l) => $l->monthlyInterest()), 2);
        $interestBleedYearly  = round($interestBleedMonthly * 12, 2);

        // Defined-benefit pensions surface two ways. (1) Accrued present value — a
        // segregated "asset" folded into net worth (only for pensions the user opted
        // in), kept out of total assets, invested value, and the allocation denominator
        // so it never distorts rebalancing. (2) Projected monthly income — informational,
        // shown for every tracked pension, because a pension is really an income stream.
        $pensionValue         = 0.0;
        $pensionMonthlyIncome = 0.0;
        $pensionDrawAge       = null;
        foreach ($user->pensions()->get() as $pension) {
            $computed = $this->pensionService->compute($pension);
            if ($pension->include_in_net_worth) {
                $pensionValue += $computed['pvAccrued'];
            }
            $pensionMonthlyIncome += $computed['projectedMonthly'];
            $pensionDrawAge ??= $computed['retirementAge'];
        }
        $pensionValue         = round($pensionValue, 2);
        $pensionMonthlyIncome = round($pensionMonthlyIncome, 2);

        $totals = [
            'cost_basis'   => round($summaries->sum('cost_basis'), 2),
            'market_value' => $summaries->contains(fn ($s) => $s['market_value'] !== null)
                ? round($summaries->sum(fn ($s) => $s['market_value'] ?? $s['cost_basis']), 2)
                : null,
            'manual_value' => round($summaries->sum('manual_value'), 2),
            'unrealized'   => $summaries->contains(fn ($s) => $s['unrealized'] !== null)
                ? round($summaries->sum(fn ($s) => $s['unrealized'] ?? 0), 2)
                : null,
            'portfolio_value'        => $portfolioValue,
            'invested_value'         => $investedValue,
            'total_value'            => $totalAssets,
            'total_debt'             => $totalDebt,
            'pension_value'          => $pensionValue,
            'pension_monthly_income' => $pensionMonthlyIncome,
            'pension_draw_age'       => $pensionDrawAge,
            'net_worth'              => round($totalAssets - $totalDebt + $pensionValue, 2),
            'debt_to_asset'          => $totalAssets > 0 ? round($totalDebt / $totalAssets * 100, 1) : null,
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
            ->filter(fn ($h) => $h['quantity'] > 0 && round($h['effective_value'], 2) > 0)
            ->sortByDesc('effective_value')
            ->values();

        $allHoldingsTotal = $allHoldings->sum('effective_value');

        $allHoldings = $allHoldings->map(function ($h) use ($allHoldingsTotal) {
            $h['pct'] = $allHoldingsTotal > 0 ? round($h['effective_value'] / $allHoldingsTotal * 100, 2) : 0;

            return $h;
        });

        $user->applyAssetClassifications($allHoldings->pluck('asset'));

        $manualBuckets = $this->manualAssetBuckets($portfolios);
        $allocation    = $this->buildAllocation($allHoldings, $manualBuckets);
        $rebalancing   = $this->buildGlobalRebalancing($allHoldings, $manualBuckets, $user);

        $budgetRuleData = $this->budgetRule->compute($user);

        $monthlySpend = $budgetRuleData['monthly_mandatory'] + $budgetRuleData['monthly_discretionary'];
        $ageOfMoney   = ($budgetRuleData['has_data'] && $monthlySpend > 0)
            ? max(0, (int) round($totalCash / $monthlySpend * 30))
            : null;

        return compact(
            'summaries', 'totals', 'chartData', 'chartDataExManual', 'allHoldings', 'allocation', 'rebalancing', 'benchmarkData', 'budgetRuleData',
            'revolvingBalance', 'interestBleedMonthly', 'interestBleedYearly',
            'totalCash', 'readyToAssign', 'ageOfMoney', 'show'
        );
    }

    private function manualAssetBuckets(Collection $portfolios): array
    {
        $buckets = $this->emptyBuckets();

        foreach ($portfolios as $p) {
            foreach ($p->manualAssets->where('include_in_chart', true) as $ma) {
                $key = AssetType::tryFrom($ma->asset_class)?->allocationKey() ?? self::OTHER_BUCKET;
                $buckets[$key] += $ma->currentValue();
            }
        }

        return $buckets;
    }

    /** Zeroed allocation buckets: one per AssetType plus the synthetic 'other'. */
    private function emptyBuckets(): array
    {
        return array_fill_keys([...AssetType::values(), self::OTHER_BUCKET], 0.0);
    }

    /**
     * Roll holdings + manual-asset buckets up into allocation classes (one per
     * AssetType plus 'other'). Holdings whose asset_type isn't a known enum value
     * fall into 'other'. Feeds both the allocation pie and the rebalancing table.
     *
     * @return array<string, float>
     */
    private function rollupByClass(Collection $allHoldings, array $manualBuckets): array
    {
        $buckets = $this->emptyBuckets();

        foreach ($allHoldings as $h) {
            $key = AssetType::tryFrom($h['asset']->asset_type)?->allocationKey() ?? self::OTHER_BUCKET;
            $buckets[$key] += (float) $h['effective_value'];
        }

        foreach ($manualBuckets as $class => $val) {
            $buckets[array_key_exists($class, $buckets) ? $class : self::OTHER_BUCKET] += $val;
        }

        return $buckets;
    }

    private function buildAllocation(Collection $allHoldings, array $manualBuckets): array
    {
        $buckets = $this->rollupByClass($allHoldings, $manualBuckets);

        $entries = collect(AssetType::cases())
            ->map(fn (AssetType $type) => [
                'label' => $type->allocationLabel(),
                'color' => $type->allocationColor(),
                'value' => round($buckets[$type->value], 2),
            ])
            ->push([
                'label' => 'Other Assets',
                'color' => self::OTHER_COLOR,
                'value' => round($buckets[self::OTHER_BUCKET], 2),
            ])
            ->sortByDesc('value');

        return [
            'labels' => $entries->pluck('label')->values()->all(),
            'colors' => $entries->pluck('color')->values()->all(),
            'values' => $entries->pluck('value')->values()->all(),
            'total'  => round(array_sum($buckets), 2),
        ];
    }

    private function buildGlobalRebalancing(Collection $allHoldings, array $manualBuckets, User $user): array
    {
        $targets = [];
        foreach (AssetType::cases() as $type) {
            $targets[$type->value] = (float) $user->{$type->targetColumn()};
        }

        if (array_sum($targets) == 0) {
            return [];
        }

        $current = $this->rollupByClass($allHoldings, $manualBuckets);

        $total = array_sum($current);
        if ($total <= 0) {
            return [];
        }

        $rows = [];
        foreach ($targets as $type => $targetPct) {
            $currentVal = round($current[$type], 2);

            // Skip classes the user neither targets nor holds (e.g. Bonds at 0% / $0).
            if ($targetPct == 0 && $currentVal == 0) {
                continue;
            }

            $rows[] = ['label' => AssetType::from($type)->allocationLabel()]
                + Rebalancing::driftRow($currentVal, $targetPct, $total);
        }

        return $rows;
    }
}
