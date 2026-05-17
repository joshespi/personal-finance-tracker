<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Services\BudgetRuleService;
use App\Services\DebtPayoffService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanningController extends Controller
{
    public function __invoke(Request $request, BudgetRuleService $budgetRule, DebtPayoffService $debtPayoff): View
    {
        return match ($request->input('tab', 'debt-payoff')) {
            'allocator'      => $this->allocator($request, $budgetRule),
            'emergency-fund' => $this->emergencyFund($request),
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

    private function allocator(Request $request, BudgetRuleService $budgetRule): View
    {
        $amount    = null;
        $buckets   = [];
        $remainder = 0.0;

        if ($request->has('amount')) {
            $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:10000000']]);

            $amount    = round((float) $request->input('amount'), 2);
            $remaining = $amount;
            $user      = $request->user();

            $budget = $budgetRule->compute($user);
            if (! $budget['emergency_funded'] && $budget['emergency_target'] > 0) {
                $gap = max(0.0, $budget['emergency_target'] - $budget['emergency_balance']);
                if ($gap > 0.01) {
                    $alloc     = min($remaining, $gap);
                    $buckets[] = [
                        'label'  => $budget['emergency_envelope']?->name ?? 'Emergency Fund',
                        'reason' => $budget['target_months'] . '-month target: $' . number_format($budget['emergency_target'], 2),
                        'amount' => round($alloc, 2),
                        'gap'    => round($gap, 2),
                        'type'   => 'emergency',
                    ];
                    $remaining -= $alloc;
                }
            }

            if ($remaining > 0.01) {
                $liabilities = $user->liabilities()
                    ->with('latestBalance')
                    ->get()
                    ->filter(fn ($l) => $l->isRevolving() && $l->currentBalance() > 0)
                    ->sortByDesc(fn ($l) => (float) ($l->interest_rate ?? 0));

                foreach ($liabilities as $l) {
                    if ($remaining <= 0.01) break;
                    $balance   = $l->currentBalance();
                    $apr       = (float) ($l->interest_rate ?? 0);
                    $alloc     = min($remaining, $balance);
                    $buckets[] = [
                        'label'  => $l->name,
                        'reason' => $apr > 0
                            ? number_format($apr, 2) . '% APR · $' . number_format(round($balance * $apr / 100 / 12, 2), 2) . '/mo interest'
                            : 'No APR recorded',
                        'amount' => round($alloc, 2),
                        'gap'    => round($balance, 2),
                        'type'   => 'debt',
                    ];
                    $remaining -= $alloc;
                }
            }

            if ($remaining > 0.01) {
                $envelopes = $user->envelopes()
                    ->whereNotNull('goal_amount')
                    ->where('is_emergency_fund', false)
                    ->with(['transactions', 'spendTransactions'])
                    ->get()
                    ->sortBy([
                        [fn ($e) => $e->goal_date === null ? 1 : 0, 'asc'],
                        ['goal_date', 'asc'],
                    ]);

                foreach ($envelopes as $env) {
                    if ($remaining <= 0.01) break;
                    $gap = round(max(0.0, (float) $env->goal_amount - $env->balance()), 2);
                    if ($gap <= 0.01) continue;
                    $alloc     = min($remaining, $gap);
                    $buckets[] = [
                        'label'  => $env->name,
                        'reason' => 'Goal $' . number_format((float) $env->goal_amount, 2)
                            . ($env->goal_date ? ' by ' . $env->goal_date->format('M Y') : ''),
                        'amount' => round($alloc, 2),
                        'gap'    => $gap,
                        'type'   => 'savings',
                    ];
                    $remaining -= $alloc;
                }
            }

            $remainder = round(max(0.0, $remaining), 2);
        }

        return view('planning', ['tab' => 'allocator', 'amount' => $amount, 'buckets' => $buckets, 'remainder' => $remainder]);
    }

    private function emergencyFund(Request $request): View
    {
        $user     = $request->user();
        $monthNow = now()->startOfMonth();

        $envelopes = $user->envelopes()
            ->where(fn ($q) => $q->where('is_emergency_fund', true)->orWhere('is_mandatory', true))
            ->orderBy('sort_order')
            ->get();

        $emergencyEnvelope  = $envelopes->firstWhere('is_emergency_fund', true);
        $mandatoryEnvelopes = $envelopes->filter(fn ($e) => $e->is_mandatory)->values();

        $monthlyBaseline  = 0;
        $monthlyBreakdown = collect();

        if ($mandatoryEnvelopes->isNotEmpty()) {
            $monthKeys = array_map(
                fn ($i) => $monthNow->copy()->subMonths($i)->format('Y-m'),
                range(0, 5)
            );

            $spendRows = CashTransaction::whereIn('envelope_id', $mandatoryEnvelopes->pluck('id'))
                ->where('type', 'withdrawal')
                ->whereBetween('occurred_at', [$monthNow->copy()->subMonths(5), $monthNow->copy()->endOfMonth()])
                ->get(['envelope_id', 'occurred_at', 'amount']);

            $byEnvelope = $spendRows->groupBy('envelope_id');

            foreach ($mandatoryEnvelopes as $env) {
                $monthlyTotals = array_fill_keys($monthKeys, 0.0);
                foreach ($byEnvelope->get($env->id, collect()) as $txn) {
                    $key = $txn->occurred_at->format('Y-m');
                    if (isset($monthlyTotals[$key])) {
                        $monthlyTotals[$key] += (float) $txn->amount;
                    }
                }
                $avg = round(array_sum($monthlyTotals) / 6, 2);
                $monthlyBaseline += $avg;
                $monthlyBreakdown->push(['envelope' => $env, 'avg' => $avg]);
            }

            $monthlyBaseline = round($monthlyBaseline, 2);
        }

        $currentSavings = $emergencyEnvelope ? round($emergencyEnvelope->balance(), 2) : null;
        $target3        = round($monthlyBaseline * 3, 2);
        $target6        = round($monthlyBaseline * 6, 2);

        $bars = [];
        if ($currentSavings !== null && $target6 > 0) {
            foreach ([['3-month fund', $target3], ['6-month fund', $target6]] as [$label, $target]) {
                $bars[] = [
                    'label'  => $label,
                    'target' => $target,
                    'pct'    => min(100, round($currentSavings / $target * 100)),
                ];
            }
        }

        return view('planning', [
            'tab'                => 'emergency-fund',
            'emergencyEnvelope'  => $emergencyEnvelope,
            'mandatoryEnvelopes' => $mandatoryEnvelopes,
            'monthlyBreakdown'   => $monthlyBreakdown,
            'monthlyBaseline'    => $monthlyBaseline,
            'target3'            => $target3,
            'target6'            => $target6,
            'currentSavings'     => $currentSavings,
            'bars'               => $bars,
        ]);
    }
}
