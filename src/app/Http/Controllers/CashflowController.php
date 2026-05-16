<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\EnvelopeTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashflowController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user     = $request->user();
        $month    = Carbon::parse($request->input('month', now()->format('Y-m')))->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $envelopes = $user
            ->envelopes()
            ->withSum(['spendTransactions as spent_amount' => fn ($q) => $q
                ->whereBetween('occurred_at', [$month, $monthEnd])
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

        $currentYm     = $month->format('Y-m');
        $historyStart  = $month->copy()->subMonths(5)->startOfMonth();
        $historyEnd    = $month->copy()->subMonth()->endOfMonth();

        $incomeByMonth = [$currentYm => $income];
        $spentByMonth  = [$currentYm => $totalSpent];

        $historyIncome = $user->cashDeposits()
            ->whereBetween('cash_transactions.occurred_at', [$historyStart, $historyEnd])
            ->select('cash_transactions.occurred_at', 'cash_transactions.amount')
            ->get();

        $historySpent = CashTransaction::whereIn('envelope_id', $envelopeIds)
            ->where('type', 'withdrawal')
            ->whereBetween('occurred_at', [$historyStart, $historyEnd])
            ->get(['occurred_at', 'amount']);

        foreach ($historyIncome as $row) {
            $ym = $row->occurred_at->format('Y-m');
            $incomeByMonth[$ym] = ($incomeByMonth[$ym] ?? 0) + (float) $row->amount;
        }

        foreach ($historySpent as $row) {
            $ym = $row->occurred_at->format('Y-m');
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

        $prevMonth      = $month->copy()->subMonth()->format('Y-m');
        $nextMonth      = $month->copy()->addMonth()->format('Y-m');
        $isCurrentMonth = $month->isSameMonth(now());

        return view('cashflow', compact(
            'month', 'income', 'totalSpent', 'net',
            'incomeRows', 'envelopeRows', 'history', 'prevMonth', 'nextMonth', 'isCurrentMonth'
        ));
    }
}
