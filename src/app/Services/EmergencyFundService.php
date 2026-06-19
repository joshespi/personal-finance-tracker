<?php

namespace App\Services;

use App\Concerns\BucketsByMonth;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\User;
use Illuminate\Support\Collection;

class EmergencyFundService
{
    use BucketsByMonth;

    /**
     * Emergency-fund readiness: the 6-month mandatory-spend baseline (with per-envelope
     * breakdown), 3- and 6-month targets, current savings, and progress bars. Consumed by
     * the standalone EmergencyFundController and the emergency-fund tab of PlanningController.
     *
     * @return array{emergencyEnvelope: ?Envelope, mandatoryEnvelopes: Collection, monthlyBreakdown: Collection, monthlyBaseline: float|int, target3: float, target6: float, currentSavings: ?float, bars: array}
     */
    public function compute(User $user): array
    {
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
            $monthKeys = $this->monthSeries($monthNow, 6)->map->format('Y-m')->all();

            $spendRows = CashTransaction::whereIn('envelope_id', $mandatoryEnvelopes->pluck('id'))
                ->withdrawals()
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

        return compact(
            'emergencyEnvelope', 'mandatoryEnvelopes', 'monthlyBreakdown',
            'monthlyBaseline', 'target3', 'target6', 'currentSavings', 'bars'
        );
    }
}
