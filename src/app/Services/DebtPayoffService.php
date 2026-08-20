<?php

namespace App\Services;

use App\Models\Liability;
use App\Models\User;
use Illuminate\Support\Collection;

class DebtPayoffService
{
    private const MAX_MONTHS = 600;

    private const DEFAULT_MIN_PCT = 0.02; // 2% of balance when min_payment not set

    /**
     * The user's liabilities that still owe something.
     *
     * orderBy('name') is load-bearing, not cosmetic: payoffOrders() sorts by balance/APR and
     * sortBy/sortByDesc are stable, so without a deterministic base ordering two debts with
     * equal balances (snowball) or equal APRs (avalanche) would be paid off in one order on
     * the planning page and another in the what-if slider's resimulate().
     */
    private function liabilitiesWithBalance(User $user): Collection
    {
        return $user->liabilities()->with('latestBalance')->orderBy('name')->get()
            ->filter(fn ($l) => $l->currentBalance() > 0);
    }

    /** Simulator rows for every revolving debt, from both models that carry one. */
    private function revolvingDebtData(Collection $withBalance, User $user): array
    {
        return array_merge(
            $this->buildDebtData($withBalance->filter(fn ($l) => $l->isRevolving())->values()),
            $this->buildCreditCardDebtData($user)
        );
    }

    /** @return array{0: array<int, mixed>, 1: array<int, mixed>} snowball then avalanche payoff order */
    private function payoffOrders(array $debtData): array
    {
        return [
            collect($debtData)->sortBy('balance')->pluck('id')->values()->all(),
            collect($debtData)->sortByDesc('apr')->pluck('id')->values()->all(),
        ];
    }

    public function compute(User $user): array
    {
        $withBalance = $this->liabilitiesWithBalance($user);

        $mortgages = $withBalance->filter(fn ($l) => ! $l->isRevolving())->values();

        $debtData = $this->revolvingDebtData($withBalance, $user);

        if (empty($debtData) && $mortgages->isEmpty()) {
            return ['has_data' => false, 'debts' => [], 'mortgages' => []];
        }

        $mortgageData = $mortgages->map(function ($l) {
            $balance         = $l->currentBalance();
            $apr             = (float) ($l->interest_rate ?? 0);
            $monthlyInterest = round($l->monthlyInterest(), 2);

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

        $totalBalance         = array_sum(array_column($debtData, 'balance'));
        $totalMonthlyInterest = round(
            array_sum(array_column($debtData, 'monthly_interest'))
            + array_sum(array_column($mortgageData, 'monthly_interest')),
            2
        );

        [$snowballOrder, $avalancheOrder] = $this->payoffOrders($debtData);

        $snowball  = empty($debtData) ? $this->emptySimulation() : $this->simulate($debtData, $snowballOrder, 0.0);
        $avalanche = empty($debtData) ? $this->emptySimulation() : $this->simulate($debtData, $avalancheOrder, 0.0);

        return [
            'has_data'                    => true,
            'total_balance'               => $totalBalance,
            'total_monthly_interest'      => $totalMonthlyInterest,
            'yearly_interest'             => round($totalMonthlyInterest * 12, 2),
            'debts'                       => $debtData,
            'mortgages'                   => $mortgageData,
            'snowball'                    => $snowball,
            'avalanche'                   => $avalanche,
            'months_saved'                => max(0, $snowball['months'] - $avalanche['months']),
            'interest_saved'              => round(max(0.0, $snowball['total_interest'] - $avalanche['total_interest']), 2),
            'negative_amortization_count' => count(array_filter($debtData, fn ($d) => $d['negative_amortization'])),
        ];
    }

    /**
     * Re-run the snowball/avalanche simulation for the user's current revolving debts
     * at a hypothetical extra monthly payment — the endpoint the planning page's "what
     * if I pay $X more" slider calls, so the live what-if figures and the initial page
     * render always come from this one simulate() implementation.
     */
    public function resimulate(User $user, float $extraPayment): array
    {
        $debtData = $this->revolvingDebtData($this->liabilitiesWithBalance($user), $user);

        if (empty($debtData)) {
            return ['snowball' => $this->emptySimulation(), 'avalanche' => $this->emptySimulation()];
        }

        [$snowballOrder, $avalancheOrder] = $this->payoffOrders($debtData);

        return [
            'snowball'  => $this->simulate($debtData, $snowballOrder, $extraPayment),
            'avalanche' => $this->simulate($debtData, $avalancheOrder, $extraPayment),
        ];
    }

    /** @param  Collection<int, Liability>  $debts */
    private function buildDebtData($debts): array
    {
        return $debts->map(function ($l) {
            $balance     = $l->currentBalance();
            $apr         = (float) ($l->interest_rate ?? 0);
            $monthlyRate = $apr / 100 / 12;

            return $this->buildDebtRow(
                // Kept as the raw int (not namespaced like the cash_account rows below) —
                // existing consumers key payoff_per_debt by the Liability's ->id directly.
                // Safe: a non-numeric 'cash_account-N' string key can never collide with it.
                id: $l->id,
                source: 'liability',
                entityId: $l->id,
                name: $l->name,
                liabilityType: $l->liability_type,
                balance: $balance,
                apr: $apr,
                monthlyRate: $monthlyRate,
                monthlyInterest: round($balance * $monthlyRate, 2),
                explicitMinPayment: $l->minimum_payment !== null ? (float) $l->minimum_payment : null,
            );
        })->values()->all();
    }

    /**
     * Synthesizes debt-payoff rows for credit-card CashAccounts with money owed — see
     * User::creditCardDebts() for why these aren't Liability rows. Shares the same
     * min-payment-estimate convention as buildDebtData() since CashAccount has no
     * minimum_payment field at all (never "set").
     */
    private function buildCreditCardDebtData(User $user): array
    {
        return $user->creditCardDebts()->map(fn (array $row) => $this->buildDebtRow(
            id: 'cash_account-'.$row['account']->id,
            source: 'cash_account',
            entityId: $row['account']->id,
            name: $row['account']->name,
            liabilityType: 'credit_card',
            balance: $row['balance'],
            apr: $row['apr'],
            monthlyRate: $row['apr'] / 100 / 12,
            monthlyInterest: $row['monthly_interest'],
            explicitMinPayment: null,
        ))->values()->all();
    }

    /**
     * Shared shape for a debt-payoff row, whichever source it comes from. Centralizes
     * the min-payment-estimate fallback (25.0 or self::DEFAULT_MIN_PCT of balance, whichever
     * is larger) and the negative-amortization check so both buildDebtData() and
     * buildCreditCardDebtData() can't drift out of sync on that rule.
     */
    private function buildDebtRow(
        int|string $id,
        string $source,
        int $entityId,
        string $name,
        string $liabilityType,
        float $balance,
        float $apr,
        float $monthlyRate,
        float $monthlyInterest,
        ?float $explicitMinPayment,
    ): array {
        $minPaymentSet = $explicitMinPayment !== null;
        $minPayment    = $minPaymentSet
            ? $explicitMinPayment
            : round(max(25.0, $balance * self::DEFAULT_MIN_PCT), 2);
        $negAmort = $monthlyInterest > 0 && $minPayment < $monthlyInterest;

        return [
            'id'                    => $id,
            'source'                => $source,
            'entity_id'             => $entityId,
            'name'                  => $name,
            'liability_type'        => $liabilityType,
            'balance'               => $balance,
            'apr'                   => $apr,
            'monthly_rate'          => $monthlyRate,
            'monthly_interest'      => $monthlyInterest,
            'min_payment'           => $minPayment,
            'min_payment_set'       => $minPaymentSet,
            'negative_amortization' => $negAmort,
        ];
    }

    public function simulate(array $debtData, array $priorityIds, float $extraPayment): array
    {
        $balances = [];
        foreach ($debtData as $d) {
            $balances[$d['id']] = $d['balance'];
        }

        $totalBudget   = $extraPayment + array_sum(array_column($debtData, 'min_payment'));
        $totalInterest = 0.0;
        $payoffMonths  = [];
        $timeline      = [];
        $allCleared    = false;

        for ($month = 1; $month <= self::MAX_MONTHS; $month++) {
            foreach ($debtData as $d) {
                $id = $d['id'];
                if ($balances[$id] <= 0) {
                    continue;
                }
                $interest = $balances[$id] * $d['monthly_rate'];
                $balances[$id] += $interest;
                $totalInterest += $interest;
            }

            $currentPriorityId = null;
            foreach ($priorityIds as $pid) {
                if ($balances[$pid] > 0) {
                    $currentPriorityId = $pid;
                    break;
                }
            }

            $allocated = 0.0;
            foreach ($debtData as $d) {
                $id = $d['id'];
                if ($id === $currentPriorityId || $balances[$id] <= 0) {
                    continue;
                }
                $payment = min($d['min_payment'], $balances[$id]);
                $balances[$id] -= $payment;
                $allocated += $payment;
                if ($balances[$id] <= 0.01) {
                    $balances[$id] = 0;
                    if (! isset($payoffMonths[$id])) {
                        $payoffMonths[$id] = $month;
                    }
                }
            }

            if ($currentPriorityId !== null && $balances[$currentPriorityId] > 0) {
                $payment = max(0.0, min($totalBudget - $allocated, $balances[$currentPriorityId]));
                $balances[$currentPriorityId] -= $payment;
                if ($balances[$currentPriorityId] <= 0.01) {
                    $balances[$currentPriorityId] = 0;
                    if (! isset($payoffMonths[$currentPriorityId])) {
                        $payoffMonths[$currentPriorityId] = $month;
                    }
                }
            }

            $totalRemaining = round(max(0.0, array_sum($balances)), 2);
            $timeline[]     = $totalRemaining;

            if ($totalRemaining <= 0.01) {
                $allCleared = true;
                break;
            }
        }

        // A debt whose minimum payment doesn't cover its own interest never reaches
        // payoffMonths (see negative_amortization on buildDebtRow()), so the loop runs out
        // the clock with balance still outstanding. Report MAX_MONTHS in that case rather
        // than maxing over only the debts that *did* clear, which would silently
        // under-report "months" while the timeline still shows debt remaining.
        $months = ($allCleared && ! empty($payoffMonths)) ? max($payoffMonths) : self::MAX_MONTHS;

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
