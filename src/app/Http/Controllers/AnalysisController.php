<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Services\BudgetRuleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    public function __invoke(Request $request, BudgetRuleService $budgetRule): View
    {
        return match ($request->input('tab', 'cashflow')) {
            'trends'      => $this->trends($request),
            'budget-rule' => $this->budgetRule($request, $budgetRule),
            default       => $this->cashflow($request),
        };
    }

    private function cashflow(Request $request): View
    {
        $user     = $request->user();
        $month    = Carbon::parse($request->input('month', now()->format('Y-m')))->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $envelopes = $user
            ->envelopes()
            ->withSum(['spendTransactions as spent_amount' => fn ($q) => $q
                ->whereBetween('occurred_at', [$month, $monthEnd]),
            ], 'amount')
            ->orderBy('sort_order')
            ->get();

        $envelopeIds = $envelopes->pluck('id');

        $incomeRows = $user->cashDeposits()
            ->whereBetween('cash_transactions.occurred_at', [$month, $monthEnd])
            ->orderByDesc('cash_transactions.occurred_at')
            ->orderByDesc('cash_transactions.id')
            ->select('cash_transactions.*')
            ->get();

        $income     = round((float) $incomeRows->sum('amount'), 2);
        $totalSpent = round((float) $envelopes->sum('spent_amount'), 2);
        $net        = round($income - $totalSpent, 2);

        $envelopeRows = $envelopes
            ->map(fn ($e) => [
                'envelope' => $e,
                'spent'    => round((float) $e->spent_amount, 2),
                'target'   => round((float) $e->monthly_target, 2),
            ])
            ->filter(fn ($r) => $r['spent'] > 0 || $r['target'] > 0)
            ->sortByDesc('spent')
            ->values();

        $currentYm    = $month->format('Y-m');
        $historyStart = $month->copy()->subMonths(5)->startOfMonth();
        $historyEnd   = $month->copy()->subMonth()->endOfMonth();

        $incomeByMonth = [$currentYm => $income];
        $spentByMonth  = [$currentYm => $totalSpent];

        foreach ($user->cashDeposits()
            ->whereBetween('cash_transactions.occurred_at', [$historyStart, $historyEnd])
            ->select('cash_transactions.occurred_at', 'cash_transactions.amount')
            ->get() as $row) {
            $ym                 = $row->occurred_at->format('Y-m');
            $incomeByMonth[$ym] = ($incomeByMonth[$ym] ?? 0) + (float) $row->amount;
        }

        foreach (CashTransaction::whereIn('envelope_id', $envelopeIds)
            ->where('type', 'withdrawal')
            ->whereBetween('occurred_at', [$historyStart, $historyEnd])
            ->get(['occurred_at', 'amount']) as $row) {
            $ym                = $row->occurred_at->format('Y-m');
            $spentByMonth[$ym] = ($spentByMonth[$ym] ?? 0) + (float) $row->amount;
        }

        $history = collect();
        for ($i = 5; $i >= 0; $i--) {
            $m  = $month->copy()->subMonths($i);
            $ym = $m->format('Y-m');
            $history->push([
                'month'  => $m->format('M Y'),
                'income' => round($incomeByMonth[$ym] ?? 0, 2),
                'spent'  => round($spentByMonth[$ym] ?? 0, 2),
            ]);
        }

        ['prevMonth' => $prevMonthParam, 'nextMonth' => $nextMonthParam, 'isCurrentMonth' => $isCurrentMonth] = $this->monthNav($month);

        return view('analysis', [
            'tab'            => 'cashflow',
            'month'          => $month,
            'income'         => $income,
            'totalSpent'     => $totalSpent,
            'net'            => $net,
            'incomeRows'     => $incomeRows,
            'envelopeRows'   => $envelopeRows,
            'history'        => $history,
            'prevMonthParam' => $prevMonthParam,
            'nextMonthParam' => $nextMonthParam,
            'isCurrentMonth' => $isCurrentMonth,
        ]);
    }

    private function trends(Request $request): View
    {
        $lookback    = min(12, max(3, (int) $request->input('months', 6)));
        $monthStarts = collect();
        for ($i = $lookback - 1; $i >= 0; $i--) {
            $monthStarts->push(now()->startOfMonth()->subMonths($i));
        }

        $envelopes   = $request->user()->envelopes()->orderBy('sort_order')->get();
        $envelopeIds = $envelopes->pluck('id');

        $spendRows = CashTransaction::whereIn('envelope_id', $envelopeIds)
            ->where('type', 'withdrawal')
            ->whereBetween('occurred_at', [$monthStarts->first(), $monthStarts->last()->endOfMonth()])
            ->get(['envelope_id', 'occurred_at', 'amount']);

        $byEnvelopeMonth = [];
        foreach ($spendRows as $row) {
            $key                                      = $row->occurred_at->format('Y-m');
            $byEnvelopeMonth[$row->envelope_id][$key] = ($byEnvelopeMonth[$row->envelope_id][$key] ?? 0) + (float) $row->amount;
        }

        $monthLabels = $monthStarts->map(fn ($m) => $m->format('M Y'));

        $datasets = $envelopes
            ->filter(fn ($env) => isset($byEnvelopeMonth[$env->id]))
            ->map(fn ($env) => [
                'label' => $env->name,
                'color' => $env->color,
                'data'  => $monthStarts->map(
                    fn ($m) => round($byEnvelopeMonth[$env->id][$m->format('Y-m')] ?? 0, 2)
                )->values(),
            ])
            ->values();

        $monthlyTotals = $monthStarts->map(
            fn ($m) => round(
                $envelopes->sum(fn ($env) => $byEnvelopeMonth[$env->id][$m->format('Y-m')] ?? 0),
                2
            )
        );

        return view('analysis', [
            'tab'           => 'trends',
            'monthLabels'   => $monthLabels,
            'datasets'      => $datasets,
            'monthStarts'   => $monthStarts,
            'monthlyTotals' => $monthlyTotals,
            'lookback'      => $lookback,
        ]);
    }

    private function budgetRule(Request $request, BudgetRuleService $service): View
    {
        return view('analysis', [
            'tab'  => 'budget-rule',
            'data' => $service->compute($request->user()),
        ]);
    }
}
