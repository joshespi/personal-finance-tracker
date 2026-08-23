<?php

namespace App\Models;

use App\Concerns\HasOwnershipRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * Which side of the transfer form this account pre-fills. Money normally leaves a
     * deposit account and lands on a credit line — that landing *is* the card payment.
     * A default only: both dropdowns stay free and the form can swap the pair.
     */
    public function transferPrefillSide(): string
    {
        return $this->isCreditCard() ? 'to_account_id' : 'from_account_id';
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
