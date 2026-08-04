<?php

namespace App\Services;

use App\Models\User;

class AllocatorService
{
    public function __construct(private BudgetRuleService $budgetRule) {}

    /**
     * Greedy "extra cash" allocation: fills the emergency-fund gap, then highest-APR
     * revolving debt, then nearest-dated savings goals, returning the per-bucket plan and
     * the leftover. Consumed by the allocator tab of PlanningController. A null $amount
     * (nothing entered yet) yields an empty plan.
     *
     * @return array{amount: ?float, buckets: array, remainder: float}
     */
    public function compute(User $user, ?float $amount): array
    {
        if ($amount === null) {
            return ['amount' => null, 'buckets' => [], 'remainder' => 0.0];
        }

        $buckets   = [];
        $remaining = $amount;

        $budget = $this->budgetRule->compute($user);
        if (! $budget['emergency_funded'] && $budget['emergency_target'] > 0) {
            $gap = max(0.0, $budget['emergency_target'] - $budget['emergency_balance']);
            if ($gap > 0.01) {
                $alloc     = min($remaining, $gap);
                $buckets[] = [
                    'label'  => $budget['emergency_envelope']?->name ?? 'Emergency Fund',
                    'reason' => $budget['target_months'].'-month target: $'.number_format($budget['emergency_target'], 2),
                    'amount' => round($alloc, 2),
                    'gap'    => round($gap, 2),
                    'type'   => 'emergency',
                ];
                $remaining -= $alloc;
            }
        }

        if ($remaining > 0.01) {
            // Liability rows and credit-card CashAccounts with a balance owed (see
            // User::creditCardDebts()) are both real revolving debt — normalize to a
            // common {name, balance, apr, monthly_interest} shape so one loop ranks
            // them together by APR instead of running two separate passes.
            $liabilityDebt = $user->liabilities()
                ->with('latestBalance')
                ->get()
                ->filter(fn ($l) => $l->isRevolving() && $l->currentBalance() > 0)
                ->map(fn ($l) => [
                    'name'             => $l->name,
                    'balance'          => $l->currentBalance(),
                    'apr'              => (float) ($l->interest_rate ?? 0),
                    'monthly_interest' => round($l->monthlyInterest(), 2),
                ]);

            $creditCardDebt = $user->creditCardDebts()->map(fn (array $row) => [
                'name'             => $row['account']->name,
                'balance'          => $row['balance'],
                'apr'              => $row['apr'],
                'monthly_interest' => $row['monthly_interest'],
            ]);

            $debts = $liabilityDebt->concat($creditCardDebt)->sortByDesc('apr');

            foreach ($debts as $d) {
                if ($remaining <= 0.01) {
                    break;
                }
                $alloc     = min($remaining, $d['balance']);
                $buckets[] = [
                    'label'  => $d['name'],
                    'reason' => $d['apr'] > 0
                        ? number_format($d['apr'], 2).'% APR · $'.number_format($d['monthly_interest'], 2).'/mo interest'
                        : 'No APR recorded',
                    'amount' => round($alloc, 2),
                    'gap'    => round($d['balance'], 2),
                    'type'   => 'debt',
                ];
                $remaining -= $alloc;
            }
        }

        if ($remaining > 0.01) {
            $envelopes = $user->envelopes()
                ->whereNotNull('goal_amount')
                ->where('is_emergency_fund', false)
                ->withBalanceTotals()
                ->get()
                ->sortBy([
                    [fn ($e) => $e->goal_date === null ? 1 : 0, 'asc'],
                    ['goal_date', 'asc'],
                ]);

            foreach ($envelopes as $env) {
                if ($remaining <= 0.01) {
                    break;
                }
                $gap = round(max(0.0, (float) $env->goal_amount - $env->balance()), 2);
                if ($gap <= 0.01) {
                    continue;
                }
                $alloc     = min($remaining, $gap);
                $buckets[] = [
                    'label'  => $env->name,
                    'reason' => 'Goal $'.number_format((float) $env->goal_amount, 2)
                        .($env->goal_date ? ' by '.$env->goal_date->format('M Y') : ''),
                    'amount' => round($alloc, 2),
                    'gap'    => $gap,
                    'type'   => 'savings',
                ];
                $remaining -= $alloc;
            }
        }

        $remainder = round(max(0.0, $remaining), 2);

        return compact('amount', 'buckets', 'remainder');
    }
}
