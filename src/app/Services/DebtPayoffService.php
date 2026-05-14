<?php

namespace App\Services;

use App\Models\User;

class DebtPayoffService
{
    private const MAX_MONTHS      = 600;
    private const DEFAULT_MIN_PCT = 0.02; // 2% of balance when min_payment not set

    public function compute(User $user): array
    {
        $liabilities = $user->liabilities()->with('latestBalance')->orderBy('name')->get();
        $withBalance = $liabilities->filter(fn ($l) => $l->currentBalance() > 0);

        $mortgages = $withBalance->filter(fn ($l) => ! $l->isRevolving())->values();
        $debts     = $withBalance->filter(fn ($l) => $l->isRevolving())->values();

        if ($debts->isEmpty() && $mortgages->isEmpty()) {
            return ['has_data' => false, 'debts' => [], 'mortgages' => []];
        }

        $debtData = $debts->map(function ($l) {
            $balance       = $l->currentBalance();
            $apr           = (float) ($l->interest_rate ?? 0);
            $monthlyRate   = $apr / 100 / 12;
            $monthlyInterest = round($balance * $monthlyRate, 2);
            $minPaymentSet = $l->minimum_payment !== null;
            $minPayment    = $minPaymentSet
                ? (float) $l->minimum_payment
                : round(max(25.0, $balance * self::DEFAULT_MIN_PCT), 2);
            $negAmort = $monthlyInterest > 0 && $minPayment < $monthlyInterest;

            return [
                'id'                    => $l->id,
                'name'                  => $l->name,
                'liability_type'        => $l->liability_type,
                'balance'               => $balance,
                'apr'                   => $apr,
                'monthly_rate'          => $monthlyRate,
                'monthly_interest'      => $monthlyInterest,
                'min_payment'           => $minPayment,
                'min_payment_set'       => $minPaymentSet,
                'negative_amortization' => $negAmort,
            ];
        })->values()->all();

        $mortgageData = $mortgages->map(function ($l) {
            $balance         = $l->currentBalance();
            $apr             = (float) ($l->interest_rate ?? 0);
            $monthlyInterest = round($balance * ($apr / 100 / 12), 2);

            return [
                'id'               => $l->id,
                'name'             => $l->name,
                'balance'          => $balance,
                'apr'              => $apr,
                'monthly_interest' => $monthlyInterest,
                'min_payment'      => $l->minimum_payment !== null ? (float) $l->minimum_payment : null,
                'min_payment_set'  => $l->minimum_payment !== null,
            ];
        })->values()->all();

        $totalBalance        = array_sum(array_column($debtData, 'balance'));
        $totalMonthlyInterest = round(
            array_sum(array_column($debtData, 'monthly_interest'))
            + array_sum(array_column($mortgageData, 'monthly_interest')),
            2
        );

        $snowballOrder  = collect($debtData)->sortBy('balance')->pluck('id')->values()->all();
        $avalancheOrder = collect($debtData)->sortByDesc('apr')->pluck('id')->values()->all();

        $snowball  = empty($debtData) ? $this->emptySimulation() : $this->simulate($debtData, $snowballOrder, 0.0);
        $avalanche = empty($debtData) ? $this->emptySimulation() : $this->simulate($debtData, $avalancheOrder, 0.0);

        return [
            'has_data'                   => true,
            'total_balance'              => $totalBalance,
            'total_monthly_interest'     => $totalMonthlyInterest,
            'yearly_interest'            => round($totalMonthlyInterest * 12, 2),
            'debts'                      => $debtData,
            'mortgages'                  => $mortgageData,
            'snowball'                   => $snowball,
            'avalanche'                  => $avalanche,
            'months_saved'               => max(0, $snowball['months'] - $avalanche['months']),
            'interest_saved'             => round(max(0.0, $snowball['total_interest'] - $avalanche['total_interest']), 2),
            'negative_amortization_count' => count(array_filter($debtData, fn ($d) => $d['negative_amortization'])),
        ];
    }

    public function simulate(array $debtData, array $priorityIds, float $extraPayment): array
    {
        $balances = [];
        foreach ($debtData as $d) {
            $balances[$d['id']] = $d['balance'];
        }

        $totalBudget  = $extraPayment + array_sum(array_column($debtData, 'min_payment'));
        $totalInterest = 0.0;
        $payoffMonths  = [];
        $timeline      = [];

        for ($month = 1; $month <= self::MAX_MONTHS; $month++) {
            foreach ($debtData as $d) {
                $id = $d['id'];
                if ($balances[$id] <= 0) continue;
                $interest       = $balances[$id] * $d['monthly_rate'];
                $balances[$id] += $interest;
                $totalInterest  += $interest;
            }

            $currentPriorityId = null;
            foreach ($priorityIds as $pid) {
                if ($balances[$pid] > 0) { $currentPriorityId = $pid; break; }
            }

            $allocated = 0.0;
            foreach ($debtData as $d) {
                $id = $d['id'];
                if ($id === $currentPriorityId || $balances[$id] <= 0) continue;
                $payment        = min($d['min_payment'], $balances[$id]);
                $balances[$id] -= $payment;
                $allocated      += $payment;
                if ($balances[$id] <= 0.01) {
                    $balances[$id] = 0;
                    if (! isset($payoffMonths[$id])) $payoffMonths[$id] = $month;
                }
            }

            if ($currentPriorityId !== null && $balances[$currentPriorityId] > 0) {
                $payment = max(0.0, min($totalBudget - $allocated, $balances[$currentPriorityId]));
                $balances[$currentPriorityId] -= $payment;
                if ($balances[$currentPriorityId] <= 0.01) {
                    $balances[$currentPriorityId] = 0;
                    if (! isset($payoffMonths[$currentPriorityId])) $payoffMonths[$currentPriorityId] = $month;
                }
            }

            $totalRemaining = round(max(0.0, array_sum($balances)), 2);
            $timeline[]     = $totalRemaining;

            if ($totalRemaining <= 0.01) break;
        }

        $months = empty($payoffMonths) ? self::MAX_MONTHS : max($payoffMonths);

        return [
            'months'          => $months,
            'total_interest'  => round($totalInterest, 2),
            'payoff_per_debt' => $payoffMonths,
            'timeline'        => $timeline,
        ];
    }

    private function emptySimulation(): array
    {
        return ['months' => 0, 'total_interest' => 0.0, 'payoff_per_debt' => [], 'timeline' => []];
    }
}
