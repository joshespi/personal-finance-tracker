<?php

namespace App\Services;

use App\Concerns\BucketsByMonth;
use App\Models\CashTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CashflowService
{
    use BucketsByMonth;

    /**
     * Shared monthly cashflow figures: per-envelope spend rows, income rows, totals,
     * and a 6-month income/spend history. Consumed by both the standalone
     * CashflowController (which groups the rows by category) and the cashflow tab of
     * AnalysisController (which lists them flat).
     *
     * @return array{month: Carbon, income: float, totalSpent: float, net: float, incomeRows: Collection, envelopeRows: Collection, history: Collection}
     */
    public function compute(User $user, ?string $month = null): array
    {
        $month    = Carbon::parse($month ?? now()->format('Y-m'))->startOfMonth();
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

        $history = $this->history($user, $month, $envelopeIds, $income, $totalSpent);

        return compact('month', 'income', 'totalSpent', 'net', 'incomeRows', 'envelopeRows', 'history');
    }

    /** 6-month income-vs-spend series ending at $month (inclusive). */
    private function history(User $user, Carbon $month, Collection $envelopeIds, float $income, float $totalSpent): Collection
    {
        $currentYm    = $month->format('Y-m');
        $historyStart = $month->copy()->subMonths(5)->startOfMonth();
        $historyEnd   = $month->copy()->subMonth()->endOfMonth();

        $historyIncome = $user->cashDeposits()
            ->whereBetween('cash_transactions.occurred_at', [$historyStart, $historyEnd])
            ->select('cash_transactions.occurred_at', 'cash_transactions.amount')
            ->get();

        $historySpent = CashTransaction::whereIn('envelope_id', $envelopeIds)
            ->withdrawals()
            ->whereBetween('occurred_at', [$historyStart, $historyEnd])
            ->get(['occurred_at', 'amount']);

        // history range excludes the current month, so seeding $currentYm never collides
        $incomeByMonth = [$currentYm => $income] + $this->sumByMonth($historyIncome);
        $spentByMonth  = [$currentYm => $totalSpent] + $this->sumByMonth($historySpent);

        return $this->monthSeries($month, 6)->map(fn ($m) => [
            'month'  => $m->format('M Y'),
            'income' => round($incomeByMonth[$m->format('Y-m')] ?? 0, 2),
            'spent'  => round($spentByMonth[$m->format('Y-m')] ?? 0, 2),
        ])->values();
    }
}
