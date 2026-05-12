<?php

namespace App\Services;

use App\Models\EnvelopeTransaction;
use App\Models\User;
use Carbon\Carbon;

class BudgetRuleService
{
    public const MANDATORY_TARGET = 60.0;
    public const DISCRETIONARY_TARGET = 30.0;
    public const SAVINGS_TARGET = 20.0;
    public const WINDOW_MONTHS = 6;

    public function compute(User $user): array
    {
        $monthNow    = Carbon::now()->startOfMonth();
        $windowStart = $monthNow->copy()->subMonths(self::WINDOW_MONTHS - 1);
        $windowEnd   = $monthNow->copy()->endOfMonth();

        $envelopes = $user->envelopes()
            ->select('id', 'user_id', 'name', 'color', 'sort_order', 'is_mandatory', 'is_emergency_fund', 'is_savings')
            ->get();

        $emergencyEnvelope = $envelopes->firstWhere('is_emergency_fund', true);
        $mandatoryIds      = $envelopes->where('is_mandatory', true)->pluck('id');
        $savingsIds        = $envelopes->filter(fn ($e) => $e->is_savings || $e->is_emergency_fund)->pluck('id');
        $otherSavings      = $envelopes
            ->filter(fn ($e) => $e->is_savings && ! $e->is_emergency_fund)
            ->sortBy([['sort_order', 'asc'], ['name', 'asc']])
            ->values();

        $incomeTotal = (float) $user->incomeEntries()
            ->whereBetween('occurred_at', [$windowStart, $windowEnd])
            ->sum('amount');
        $monthlyIncome = round($incomeTotal / self::WINDOW_MONTHS, 2);

        if ($monthlyIncome <= 0 && $envelopes->isEmpty()) {
            return $this->emptyPayload($windowStart, $windowEnd, $user);
        }

        $monthlyMandatory = $mandatoryIds->isEmpty() ? 0.0 : round(
            (float) EnvelopeTransaction::whereIn('envelope_id', $mandatoryIds)
                ->where('type', 'spend')
                ->whereBetween('occurred_at', [$windowStart, $windowEnd])
                ->sum('amount') / self::WINDOW_MONTHS,
            2
        );

        $monthlySavings = 0.0;
        if ($savingsIds->isNotEmpty()) {
            $net = EnvelopeTransaction::whereIn('envelope_id', $savingsIds)
                ->whereBetween('occurred_at', [$windowStart, $windowEnd])
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'fund' THEN amount ELSE -amount END), 0) AS net")
                ->value('net');
            $monthlySavings = round(((float) $net) / self::WINDOW_MONTHS, 2);
        }

        $monthlyDiscretionary = round($monthlyIncome - $monthlyMandatory - $monthlySavings, 2);

        $ratios = [
            'mandatory'     => $monthlyIncome > 0 ? round($monthlyMandatory / $monthlyIncome * 100, 1) : null,
            'discretionary' => $monthlyIncome > 0 ? round($monthlyDiscretionary / $monthlyIncome * 100, 1) : null,
            'savings'       => $monthlyIncome > 0 ? round($monthlySavings / $monthlyIncome * 100, 1) : null,
        ];

        $targetMonths     = (int) ($user->emergency_fund_target_months ?: 6);
        $emergencyTarget  = round($monthlyMandatory * $targetMonths, 2);
        $emergencyBalance = $emergencyEnvelope ? round($emergencyEnvelope->balance(), 2) : 0.0;
        $emergencyFunded  = $emergencyTarget > 0 && $emergencyBalance >= $emergencyTarget;

        $hasData = $monthlyIncome > 0;
        $drift = [
            'mandatory_over' => $hasData && $ratios['mandatory'] !== null && $ratios['mandatory'] > self::MANDATORY_TARGET,
            'savings_under'  => $hasData && $ratios['savings'] !== null && $ratios['savings'] < self::SAVINGS_TARGET,
        ];

        return [
            'has_data'              => $hasData,
            'monthly_income'        => $monthlyIncome,
            'monthly_mandatory'     => $monthlyMandatory,
            'monthly_discretionary' => $monthlyDiscretionary,
            'monthly_savings'       => $monthlySavings,
            'ratios'                => $ratios,
            'targets'               => [
                'mandatory'     => self::MANDATORY_TARGET,
                'discretionary' => self::DISCRETIONARY_TARGET,
                'savings'       => self::SAVINGS_TARGET,
            ],
            'drift'                 => $drift,
            'phase'                 => $emergencyFunded ? 'funded' : 'building',
            'emergency_envelope'    => $emergencyEnvelope,
            'emergency_balance'     => $emergencyBalance,
            'emergency_target'      => $emergencyTarget,
            'emergency_funded'      => $emergencyFunded,
            'target_months'         => $targetMonths,
            'window_start'          => $windowStart->copy(),
            'window_end'            => $windowEnd->copy(),
            'window_months'         => self::WINDOW_MONTHS,
            'other_savings'         => $otherSavings,
        ];
    }

    private function emptyPayload(Carbon $windowStart, Carbon $windowEnd, User $user): array
    {
        return [
            'has_data'              => false,
            'monthly_income'        => 0.0,
            'monthly_mandatory'     => 0.0,
            'monthly_discretionary' => 0.0,
            'monthly_savings'       => 0.0,
            'ratios'                => ['mandatory' => null, 'discretionary' => null, 'savings' => null],
            'targets'               => [
                'mandatory'     => self::MANDATORY_TARGET,
                'discretionary' => self::DISCRETIONARY_TARGET,
                'savings'       => self::SAVINGS_TARGET,
            ],
            'drift'                 => ['mandatory_over' => false, 'savings_under' => false],
            'phase'                 => 'building',
            'emergency_envelope'    => null,
            'emergency_balance'     => 0.0,
            'emergency_target'      => 0.0,
            'emergency_funded'      => false,
            'target_months'         => (int) ($user->emergency_fund_target_months ?: 6),
            'window_start'          => $windowStart->copy(),
            'window_end'            => $windowEnd->copy(),
            'window_months'         => self::WINDOW_MONTHS,
            'other_savings'         => collect(),
        ];
    }
}
