<?php

namespace App\Services;

use App\Models\User;

class AllocatorService
{
    public function __construct(private BudgetRuleService $budgetRule) {}

    /**
     * Greedy "extra cash" allocation: fills the emergency-fund gap, then highest-APR
     * revolving debt, then nearest-dated savings goals, returning the per-bucket plan and
     * the leftover. Consumed by the standalone AllocatorController and the allocator tab of
     * PlanningController. A null $amount (nothing entered yet) yields an empty plan.
     *
     * @return array{amount: ?float, buckets: array, remainder: float}
     */
    public function compute(User $user, ?float $amount): array
    {
        $buckets   = [];
        $remainder = 0.0;

        if ($amount !== null) {
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
                $liabilities = $user->liabilities()
                    ->with('latestBalance')
                    ->get()
                    ->filter(fn ($l) => $l->isRevolving() && $l->currentBalance() > 0)
                    ->sortByDesc(fn ($l) => (float) ($l->interest_rate ?? 0));

                foreach ($liabilities as $l) {
                    if ($remaining <= 0.01) {
                        break;
                    }
                    $balance   = $l->currentBalance();
                    $apr       = (float) ($l->interest_rate ?? 0);
                    $alloc     = min($remaining, $balance);
                    $buckets[] = [
                        'label'  => $l->name,
                        'reason' => $apr > 0
                            ? number_format($apr, 2).'% APR · $'.number_format(round($balance * $apr / 100 / 12, 2), 2).'/mo interest'
                            : 'No APR recorded',
                        'amount' => round($alloc, 2),
                        'gap'    => round($balance, 2),
                        'type'   => 'debt',
                    ];
                    $remaining -= $alloc;
                }
            }

            if ($remaining > 0.01) {
                $envelopes = $user->envelopes()
                    ->whereNotNull('goal_amount')
                    ->where('is_emergency_fund', false)
                    ->with(['transactions', 'spendTransactions'])
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
        }

        return compact('amount', 'buckets', 'remainder');
    }
}
