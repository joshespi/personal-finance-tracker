<?php

namespace App\Services;

use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Support\Collection;

class SpendingTrendsService
{
    /**
     * Per-envelope monthly spend over a clamped 3–12 month lookback, shaped for the
     * trends chart. Consumed by the standalone SpendingTrendsController and the trends
     * tab of AnalysisController.
     *
     * @return array{monthLabels: Collection, datasets: Collection, monthStarts: Collection, monthlyTotals: Collection, lookback: int}
     */
    public function compute(User $user, int|string|null $months = null): array
    {
        $lookback = min(12, max(3, (int) ($months ?? 6)));

        $monthStarts = collect();
        for ($i = $lookback - 1; $i >= 0; $i--) {
            $monthStarts->push(now()->startOfMonth()->subMonths($i));
        }

        $envelopes   = $user->envelopes()->orderBy('sort_order')->get();
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

        return compact('monthLabels', 'datasets', 'monthStarts', 'monthlyTotals', 'lookback');
    }
}
