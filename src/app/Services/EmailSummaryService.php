<?php

namespace App\Services;

use App\Enums\EmailSummarySection;
use App\Models\CashTransaction;
use App\Models\Liability;
use App\Models\PortfolioSnapshot;
use App\Models\ScheduledTransaction;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Builds the content for the periodic account-summary email: only the sections
 * a user opted into (see EmailSummarySection), scoped to the window since their
 * last summary. There's no other "changes since an arbitrary date" aggregator
 * in the app — CashflowService/EmergencyFundService are current-month/trailing-window,
 * not since-a-timestamp — so this stays a small, purpose-built service rather
 * than bolting the requirement onto either of those.
 */
class EmailSummaryService
{
    /**
     * Per-compute() memo (portfolio IDs, snapshot totals) so sections that need the
     * same figure don't re-query it. Reset at the top of each compute() call so a
     * reused service instance never leaks one user's data into another's summary.
     *
     * @var array<string, mixed>
     */
    private array $memo = [];

    public function __construct(
        private readonly NetWorthService $netWorthService,
        private readonly EmergencyFundService $emergencyFundService,
    ) {}

    /**
     * @param  Collection<int, EmailSummarySection>  $sections
     * @return array<string, mixed> keyed by EmailSummarySection value; only requested sections are present
     */
    public function compute(User $user, CarbonInterface $since, CarbonInterface $until, Collection $sections): array
    {
        $this->memo = [];

        $data = [];

        foreach ($sections as $section) {
            $data[$section->value] = match ($section) {
                EmailSummarySection::Budgeting         => $this->budgeting($user, $since, $until),
                EmailSummarySection::Investing         => $this->investing($user, $since, $until),
                EmailSummarySection::NetWorth          => $this->netWorth($user, $since),
                EmailSummarySection::UpcomingScheduled => $this->upcomingScheduled($user, $since, $until),
                EmailSummarySection::CategoryChanges   => $this->categoryChanges($user, $since, $until),
                EmailSummarySection::Warnings          => $this->warnings($user),
            };
        }

        return $data;
    }

    /** @return array{deposits: float, withdrawals: float, net: float, transactionCount: int} */
    private function budgeting(User $user, CarbonInterface $since, CarbonInterface $until): array
    {
        $rows = CashTransaction::query()
            ->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')
            ->where('cash_accounts.user_id', $user->id)
            ->whereBetween('cash_transactions.occurred_at', [$since, $until])
            ->get(['cash_transactions.type', 'cash_transactions.amount']);

        $deposits    = (float) $rows->where('type', 'deposit')->sum('amount');
        $withdrawals = (float) $rows->where('type', 'withdrawal')->sum('amount');

        return [
            'deposits'         => round($deposits, 2),
            'withdrawals'      => round($withdrawals, 2),
            'net'              => round($deposits - $withdrawals, 2),
            'transactionCount' => $rows->count(),
        ];
    }

    /** @return array{buys: float, sells: float, fees: float, transactionCount: int, valueChange: ?float} */
    private function investing(User $user, CarbonInterface $since, CarbonInterface $until): array
    {
        $portfolioIds = $this->portfolioIds($user);

        $rows = Transaction::whereIn('portfolio_id', $portfolioIds)
            ->whereBetween('transacted_at', [$since, $until])
            ->get();

        $buys  = (float) $rows->filter(fn ($t) => $t->type->isInflow())->sum(fn ($t) => $t->totalCost());
        $sells = (float) $rows->filter(fn ($t) => $t->type->isOutflow())->sum(fn ($t) => $t->dividendValue());
        $fees  = (float) $rows->sum(fn ($t) => $t->fee_in_asset ? 0.0 : (float) $t->fees);

        return [
            'buys'             => round($buys, 2),
            'sells'            => round($sells, 2),
            'fees'             => round($fees, 2),
            'transactionCount' => $rows->count(),
            'valueChange'      => $this->portfolioValueChange($portfolioIds, $since),
        ];
    }

    /**
     * Delta in (market_value + manual_value) between the snapshot nearest $since and
     * the latest snapshot. Null when there isn't at least one snapshot on each side —
     * portfolios:snapshot only started recording once created, so a brand-new user or
     * portfolio may not have a snapshot far enough back yet.
     */
    private function portfolioValueChange(Collection $portfolioIds, CarbonInterface $since): ?float
    {
        $latest = $this->snapshotTotalAsOf($portfolioIds, now());
        $prior  = $this->snapshotTotalAsOf($portfolioIds, $since);

        return $latest === null || $prior === null ? null : round($latest - $prior, 2);
    }

    /** @return array{current: float, change: ?float} */
    private function netWorth(User $user, CarbonInterface $since): array
    {
        $current = (float) $this->netWorthService->compute($user)['totals']['net_worth'];

        // Cash/debt/pension aren't snapshotted over time the way portfolio value is, so the
        // net-worth change since $since is approximated by the portfolio-value delta alone —
        // the same figure the investing section reports.
        return [
            'current' => round($current, 2),
            'change'  => $this->portfolioValueChange($this->portfolioIds($user), $since),
        ];
    }

    /** Memoized per compute() — several sections scope to the same user's portfolios. */
    private function portfolioIds(User $user): Collection
    {
        return $this->memo['portfolioIds'] ??= $user->portfolios()->pluck('id');
    }

    /**
     * Sum of (market_value + manual_value) across each portfolio's most recent snapshot
     * on or before $asOf. Null if none of the portfolios have a snapshot that old.
     */
    private function snapshotTotalAsOf(Collection $portfolioIds, CarbonInterface $asOf): ?float
    {
        if ($portfolioIds->isEmpty()) {
            return null;
        }

        // investing() and netWorth() both ask for the same "latest" and "$since" totals;
        // key the memo by portfolio set + date so it survives across those two sections.
        $key = 'snap:'.$portfolioIds->implode(',').'@'.$asOf->toDateString();

        if (array_key_exists($key, $this->memo)) {
            return $this->memo[$key];
        }

        $latestDates = PortfolioSnapshot::whereIn('portfolio_id', $portfolioIds)
            ->where('recorded_on', '<=', $asOf->toDateString())
            ->selectRaw('portfolio_id, MAX(recorded_on) as max_date')
            ->groupBy('portfolio_id');

        // A single aggregate: SUM over zero matching rows returns SQL NULL, which is exactly
        // the "no snapshot that old" signal — no separate exists() probe needed.
        $total = PortfolioSnapshot::joinSub($latestDates, 'latest', fn ($j) => $j
            ->on('portfolio_snapshots.portfolio_id', '=', 'latest.portfolio_id')
            ->on('portfolio_snapshots.recorded_on', '=', 'latest.max_date')
        )->selectRaw('SUM(portfolio_snapshots.market_value + portfolio_snapshots.manual_value) as total')
            ->value('total');

        return $this->memo[$key] = $total === null ? null : (float) $total;
    }

    /**
     * Active scheduled transactions due before the next summary is expected — i.e.
     * within one more [$since, $until] span past $until, so a weekly summary looks
     * a week ahead and a monthly one looks a month ahead.
     *
     * @return Collection<int, ScheduledTransaction>
     */
    private function upcomingScheduled(User $user, CarbonInterface $since, CarbonInterface $until): Collection
    {
        $horizon = $until->copy()->addDays(max(1, (int) $since->diffInDays($until)));

        return $user->scheduledTransactions()
            ->where('is_active', true)
            ->where('next_due_at', '<=', $horizon)
            ->orderBy('next_due_at')
            ->get();
    }

    /** @return Collection<int, array{envelope: string, current: float, previous: float, percentChange: ?float}> */
    private function categoryChanges(User $user, CarbonInterface $since, CarbonInterface $until): Collection
    {
        $spanDays   = max(1, (int) $since->diffInDays($until));
        $priorSince = $since->copy()->subDays($spanDays);
        $priorUntil = $since->copy();

        $envelopes = $user->envelopes()->get(['id', 'name']);

        if ($envelopes->isEmpty()) {
            return collect();
        }

        $envelopeIds  = $envelopes->pluck('id');
        $currentSpend = $this->spendByEnvelope($envelopeIds, $since, $until);
        $priorSpend   = $this->spendByEnvelope($envelopeIds, $priorSince, $priorUntil);

        return $envelopes->map(function ($envelope) use ($currentSpend, $priorSpend) {
            $current  = round($currentSpend->get($envelope->id, 0.0), 2);
            $previous = round($priorSpend->get($envelope->id, 0.0), 2);

            return [
                'envelope'      => $envelope->name,
                'current'       => $current,
                'previous'      => $previous,
                'percentChange' => $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null,
            ];
        })->filter(fn ($row) => $row['current'] > 0 || $row['previous'] > 0)->values();
    }

    private function spendByEnvelope(Collection $envelopeIds, CarbonInterface $since, CarbonInterface $until): Collection
    {
        return CashTransaction::whereIn('envelope_id', $envelopeIds)
            ->withdrawals()
            ->whereBetween('occurred_at', [$since, $until])
            ->get(['envelope_id', 'amount'])
            ->groupBy('envelope_id')
            ->map(fn ($rows) => (float) $rows->sum('amount'));
    }

    /** @return array{overBudgetEnvelopes: Collection, lowBalanceAccounts: Collection, upcomingBills: Collection, emergencyFundBelowTarget: bool} */
    private function warnings(User $user): array
    {
        // Eager-load spendTransactions so spentInMonth()'s relationLoaded() fast path
        // (see Envelope::spentInMonth()) filters in memory instead of querying per envelope.
        $envelopes = $user->envelopes()->with('spendTransactions')->get();

        $overBudget = $envelopes
            ->map(fn ($e) => ['envelope' => $e->name, 'spent' => round($e->spentInMonth(), 2), 'target' => round((float) $e->monthly_target, 2)])
            ->filter(fn ($row) => $row['target'] > 0 && $row['spent'] > $row['target'])
            ->values();

        // withCurrentBalance() aggregates deposits/withdrawals for every account in one
        // query (same helper CashAccountController uses) instead of balance() per account.
        $lowBalance = $user->cashAccounts()->withCurrentBalance()->get()
            ->map(fn ($a) => ['account' => $a->name, 'balance' => round((float) ($a->deposits_total ?? 0) - (float) ($a->withdrawals_total ?? 0), 2)])
            ->filter(fn ($row) => $row['balance'] < 0)
            ->values();

        $upcomingBills = Liability::where('user_id', $user->id)
            ->whereNotNull('payment_day')
            ->get()
            ->map(fn ($l) => ['liability' => $l->name, 'amount' => round($l->totalMonthlyPayment(), 2), 'paymentDay' => $l->payment_day])
            ->values();

        $fund                     = $this->emergencyFundService->compute($user);
        $emergencyFundBelowTarget = $fund['currentSavings'] !== null && $fund['target3'] > 0
            && $fund['currentSavings'] < $fund['target3'];

        return [
            'overBudgetEnvelopes'      => $overBudget,
            'lowBalanceAccounts'       => $lowBalance,
            'upcomingBills'            => $upcomingBills,
            'emergencyFundBelowTarget' => $emergencyFundBelowTarget,
        ];
    }
}
