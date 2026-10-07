<?php

namespace App\Models;

use App\Concerns\HasOwnershipRule;
use App\Support\Finance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class CashAccount extends Model
{
    use HasFactory, HasOwnershipRule;

    /** account_type values eligible to hold envelope-linked savings balances (see `envelopes()`). */
    public const SAVINGS_TYPES = ['savings', 'money_market', 'cd'];

    /**
     * The account_type that models a revolving credit line rather than a deposit account.
     * These invert the usual sign convention — a negative working balance is money owed
     * by design, not an overdraft — so callers must opt in (creditCards()) or opt out
     * (excludingCreditCards()) deliberately rather than treating them as cash.
     */
    public const CREDIT_CARD_TYPE = 'credit_card';

    protected $fillable = ['user_id', 'name', 'account_type', 'currency', 'notes', 'interest_rate', 'billing_day'];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'billing_day'   => 'integer',
    ];

    public function isSavingsType(): bool
    {
        return in_array($this->account_type, self::SAVINGS_TYPES, true);
    }

    public function isCreditCard(): bool
    {
        return $this->account_type === self::CREDIT_CARD_TYPE;
    }

    /**
     * ±1 between the ledger's signed balance (card debt is stored negative) and the
     * positive "amount owed" a statement reports; identity for every other type.
     * Multiplying by it converts either direction.
     */
    public function balanceSign(): int
    {
        return $this->isCreditCard() ? -1 : 1;
    }

    /** Positive amount owed for a signed ledger balance — 0 for a card in credit or a non-card. */
    public function amountOwed(float $balance): float
    {
        return $this->isCreditCard() ? max(0.0, -$balance) : 0.0;
    }

    /** Unrounded monthly interest on an amount owed at this card's APR (same rate as Liability). */
    public function monthlyInterest(float $owed): float
    {
        return $owed * Finance::aprMonthlyRate((float) ($this->interest_rate ?? 0));
    }

    /**
     * Which side of the transfer form this account pre-fills. Money normally leaves a
     * deposit account and lands on a credit line — that landing *is* the card payment.
     * A default only: both dropdowns stay free and the form can swap the pair.
     */
    public function transferPrefillSide(): string
    {
        return $this->isCreditCard() ? 'to_account_id' : 'from_account_id';
    }

    /**
     * The most recent statement close on or before $today, from billing_day — null for
     * non-cards or cards without a billing day. billing_day is capped at 28 on input, so
     * setting the day never overflows a short month.
     */
    public function lastStatementClose(?Carbon $today = null): ?Carbon
    {
        if (! $this->isCreditCard() || ! $this->billing_day) {
            return null;
        }

        $today ??= Carbon::today();
        $close = $today->copy()->startOfDay()->day($this->billing_day);

        return $today->day >= $this->billing_day ? $close : $close->subMonthNoOverflow();
    }

    /**
     * What's due on a card: the amount owed at its last statement close, less whatever has
     * been deposited onto it since. Takes the current signed balance (already loaded by
     * callers) and backs out post-close activity, so only rows after the close are scanned.
     * Null when the card has no billing day (or isn't a card).
     *
     * @return array{closedOn: string, balance: float, paidSince: float, remaining: float}|null
     */
    public function statementSummary(float $balance, ?Carbon $today = null): ?array
    {
        $close = $this->lastStatementClose($today);
        if (! $close) {
            return null;
        }

        $since = $this->transactions()
            ->whereDate('occurred_at', '>', $close)
            ->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) AS net")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) AS paid")
            ->first();

        $owedAtClose = $this->amountOwed($balance - (float) $since->net);
        $paidSince   = (float) $since->paid;

        return [
            'closedOn'  => $close->format('M j'),
            'balance'   => round($owedAtClose, 2),
            'paidSince' => round($paidSince, 2),
            'remaining' => round(max(0.0, $owedAtClose - $paidSince), 2),
        ];
    }

    /** Revolving credit lines only — the debt side (see User::creditCardDebts()). */
    public function scopeCreditCards(Builder $query): Builder
    {
        return $query->where('account_type', self::CREDIT_CARD_TYPE);
    }

    /** Deposit accounts only — the exact complement of creditCards(); keep the two in step. */
    public function scopeExcludingCreditCards(Builder $query): Builder
    {
        return $query->where('account_type', '!=', self::CREDIT_CARD_TYPE);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class);
    }

    public function scheduledTransactions(): HasMany
    {
        return $this->hasMany(ScheduledTransaction::class);
    }

    /** Envelopes whose balance is meant to live in this account (emergency fund, savings goals). */
    public function envelopes(): HasMany
    {
        return $this->hasMany(Envelope::class);
    }

    /**
     * Eager-load `deposits_total` / `withdrawals_total` aggregates per account so a
     * list of accounts can show balances in one query (no N+1). Callers derive
     * `current_balance = deposits_total − withdrawals_total`.
     */
    public function scopeWithCurrentBalance(Builder $query): Builder
    {
        return $query
            ->withSum(['transactions as deposits_total' => fn ($q) => $q->where('type', 'deposit')], 'amount')
            ->withSum(['transactions as withdrawals_total' => fn ($q) => $q->where('type', 'withdrawal')], 'amount');
    }

    /**
     * Balance derived from the deposits_total/withdrawals_total sums loaded by
     * scopeWithCurrentBalance() — the cheap, N+1-avoiding balance for a *list* of
     * accounts. Callers assign the result onto current_balance themselves (it isn't
     * an accessor) since the sum attributes are only present after that scope.
     * For a single account, prefer balance()/balances() instead.
     */
    public function currentBalanceFromSums(): float
    {
        return (float) ($this->deposits_total ?? 0) - (float) ($this->withdrawals_total ?? 0);
    }

    /** Working balance — every transaction, cleared or not. */
    public function balance(): float
    {
        return $this->balances()['working'];
    }

    /** Cleared balance — only transactions that have cleared the bank. */
    public function clearedBalance(): float
    {
        return $this->balances()['cleared'];
    }

    /** Uncleared (pending) balance — working minus cleared. */
    public function unclearedBalance(): float
    {
        return $this->balances()['uncleared'];
    }

    /**
     * Working, cleared and pending balances in a single query — for views that show all
     * three at once. Pending is derived arithmetically rather than summed a third time.
     * Memoized per instance (no invalidation) so the three single-figure accessors
     * share one query — don't re-read from the same instance after writing
     * transactions; refetch or query directly instead.
     *
     * @return array{working: float, cleared: float, uncleared: float}
     */
    public function balances(): array
    {
        return $this->balancesCache ??= CashTransaction::balanceTotals($this->transactions());
    }

    private ?array $balancesCache = null;

    public function toBackupArray(): array
    {
        return [
            'name'         => $this->name,
            'account_type' => $this->account_type,
            'currency'     => $this->currency,
            'notes'        => $this->notes,
            'transactions' => $this->transactions->map(fn ($t) => $t->toBackupArray())->values(),
        ];
    }
}
