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
        $month = Carbon::parse($request->input('month', now()->format('Y-m')))->startOfMonth();

        $cashAccountIds = $request->user()->cashAccounts()->pluck('id');
        $envelopeIds    = $request->user()->envelopes()->pluck('id');

        [$income, $totalSpent] = $this->totalsForMonth($month, $cashAccountIds, $envelopeIds);

        $net = round($income - $totalSpent, 2);

        $envelopes = $request->user()
            ->envelopes()
            ->with(['transactions' => fn ($q) => $q
                ->where('type', 'spend')
                ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ])
            ->orderBy('sort_order')
            ->get();

        $envelopeRows = $envelopes
            ->map(fn ($e) => [
                'envelope' => $e,
                'spent'    => round((float) $e->transactions->sum('amount'), 2),
                'target'   => round((float) $e->monthly_target, 2),
            ])
            ->filter(fn ($r) => $r['spent'] > 0 || $r['target'] > 0)
            ->sortByDesc('spent')
            ->values();

        $history = collect();
        for ($i = 5; $i >= 0; $i--) {
            $m = $month->copy()->subMonths($i);
            [$mIncome, $mSpent] = $this->totalsForMonth($m, $cashAccountIds, $envelopeIds);
            $history->push([
                'month'  => $m->format('M Y'),
                'income' => $mIncome,
                'spent'  => $mSpent,
            ]);
        }

        $prevMonth      = $month->copy()->subMonth()->format('Y-m');
        $nextMonth      = $month->copy()->addMonth()->format('Y-m');
        $isCurrentMonth = $month->isSameMonth(now());

        return view('cashflow', compact(
            'month', 'income', 'totalSpent', 'net',
            'envelopeRows', 'history', 'prevMonth', 'nextMonth', 'isCurrentMonth'
        ));
    }

    private function totalsForMonth(Carbon $month, $cashAccountIds, $envelopeIds): array
    {
        $income = round((float) CashTransaction::whereIn('cash_account_id', $cashAccountIds)
            ->where('type', 'deposit')
            ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->sum('amount'), 2);

        $spent = round((float) EnvelopeTransaction::whereIn('envelope_id', $envelopeIds)
            ->where('type', 'spend')
            ->whereBetween('occurred_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->sum('amount'), 2);

        return [$income, $spent];
    }
}
