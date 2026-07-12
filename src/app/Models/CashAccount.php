<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashAccount extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'account_type', 'currency', 'notes', 'interest_rate', 'billing_day'];

    protected $casts = [
        'interest_rate' => 'decimal:2',
        'billing_day'   => 'integer',
    ];

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
}
