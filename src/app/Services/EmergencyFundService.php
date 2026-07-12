<?php

namespace App\Services;

use App\Concerns\BucketsByMonth;
use App\Enums\Recurrence;
use App\Enums\ScheduledTransactionType;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Illuminate\Support\Collection;

class EmergencyFundService
{
    use BucketsByMonth;

    /**
     * Emergency-fund readiness: the 6-month mandatory-spend baseline (with per-envelope
     * breakdown), 3- and 6-month targets, current savings, and progress bars. Consumed by
     * the emergency-fund tab of PlanningController.
     *
     * @return array{emergencyEnvelope: ?Envelope, mandatoryEnvelopes: Collection, monthlyBreakdown: Collection, monthlyBaseline: float|int, target3: float, target6: float, currentSavings: ?float, bars: array}
     */
    public function compute(User $user): array
    {
        $envelopes = $user->envelopes()
            ->where(fn ($q) => $q->where('is_emergency_fund', true)
                ->orWhere('is_mandatory', true)
                ->orWhere('include_in_emergency_fund', true))
            ->orderBy('sort_order')
            ->get();

        $emergencyEnvelope = $envelopes->firstWhere('is_emergency_fund', true);
        // Necessities are always part of the target; the opt-in flag adds non-mandatory
        // essentials (e.g. groceries, fuel) without reclassifying them as a 50% Need.
        $mandatoryEnvelopes = $envelopes
            ->filter(fn ($e) => $e->is_mandatory || $e->include_in_emergency_fund)
            ->values();

        $monthlyBreakdown = $this->mandatorySpendBreakdown($user, $mandatoryEnvelopes);
        $monthlyBaseline  = round($monthlyBreakdown->sum('avg'), 2);

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

    /**
     * The 6-month mandatory-spend baseline on its own — same figure compute() exposes,
     * but without loading the emergency-fund balance or building the targets/bars.
     * Lets BudgetRuleService share the baseline cheaply on the dashboard hot path.
     */
    public function monthlyBaseline(User $user): float
    {
        $mandatoryEnvelopes = $user->envelopes()
            ->where(fn ($q) => $q->where('is_mandatory', true)
                ->orWhere('include_in_emergency_fund', true))
            ->get();

        return round($this->mandatorySpendBreakdown($user, $mandatoryEnvelopes)->sum('avg'), 2);
    }

    /**
     * Per-envelope monthly mandatory spend, each as ['envelope' => Envelope, 'avg' => float].
     *
     * @return Collection<int, array{envelope: Envelope, avg: float}>
     */
    private function mandatorySpendBreakdown(User $user, Collection $mandatoryEnvelopes): Collection
    {
        if ($mandatoryEnvelopes->isEmpty()) {
            return collect();
        }

        $monthNow  = now()->startOfMonth();
        $monthKeys = $this->monthSeries($monthNow, 6)->map->format('Y-m')->all();

        $spendRows = CashTransaction::whereIn('envelope_id', $mandatoryEnvelopes->pluck('id'))
            ->withdrawals()
            ->whereBetween('occurred_at', [$monthNow->copy()->subMonths(5), $monthNow->copy()->endOfMonth()])
            ->get(['envelope_id', 'occurred_at', 'amount'])
            ->groupBy('envelope_id');

        // Active recurring spends tied to an included envelope, normalized to a
        // monthly amount. This lets a scheduled-but-not-yet-recorded obligation
        // (e.g. a monthly mortgage with only one payment in the actuals) count at
        // its full monthly value instead of being diluted across the 6-month window.
        $scheduledByEnvelope = ScheduledTransaction::where('user_id', $user->id)
            ->where('is_active', true)
            ->whereIn('envelope_id', $mandatoryEnvelopes->pluck('id'))
            ->whereIn('type', [ScheduledTransactionType::EnvelopeSpend, ScheduledTransactionType::LiabilityPayment])
            ->get(['envelope_id', 'amount', 'recurrence'])
            ->groupBy('envelope_id');

        return $mandatoryEnvelopes->map(function ($env) use ($spendRows, $scheduledByEnvelope, $monthKeys) {
            $monthlyTotals = array_fill_keys($monthKeys, 0.0);
            foreach ($spendRows->get($env->id, collect()) as $txn) {
                $key = $txn->occurred_at->format('Y-m');
                if (isset($monthlyTotals[$key])) {
                    $monthlyTotals[$key] += (float) $txn->amount;
                }
            }
            $historicalAvg = array_sum($monthlyTotals) / 6;

            $scheduledMonthly = $scheduledByEnvelope->get($env->id, collect())
                ->sum(fn ($s) => (float) $s->amount * ($s->recurrence ?? Recurrence::Monthly)->monthlyFactor());

            // Take the larger of the two so a recurring obligation isn't double-counted
            // with its own (partial) history, but ad-hoc spend still shows through.
            return ['envelope' => $env, 'avg' => round(max($historicalAvg, $scheduledMonthly), 2)];
        })->values();
    }
}
