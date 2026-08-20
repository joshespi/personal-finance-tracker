<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['cash_account_id', 'envelope_id', 'income_category_id', 'linked_transaction_id', 'type', 'cleared', 'amount', 'description', 'occurred_at'];

    protected $casts = [
        'occurred_at' => 'date',
        'amount'      => 'decimal:8',
        'cleared'     => 'boolean',
    ];

    /** Spend rows (qualified so it's safe inside relation/withSum subqueries too). */
    public function scopeWithdrawals($query)
    {
        return $query->where('cash_transactions.type', 'withdrawal');
    }

    /**
     * Working/cleared/pending balances summed over an arbitrary set of cash transactions.
     * Accepts any query or relation builder so a single account and a multi-account ledger
     * share one definition of "working = deposits − withdrawals".
     *
     * @return array{working: float, cleared: float, uncleared: float}
     */
    public static function balanceTotals($query): array
    {
        // toBase() so the result is a plain row, not a CashTransaction model — otherwise the
        // "cleared" alias would hit the model's boolean cast and collapse the sum to 0/1.
        $row = $query->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) AS working")
            ->selectRaw("COALESCE(SUM(CASE WHEN cleared = 1 THEN (CASE WHEN type = 'deposit' THEN amount ELSE -amount END) ELSE 0 END), 0) AS cleared")
            ->first();

        $working = (float) $row->working;
        $cleared = (float) $row->cleared;

        return [
            'working'   => $working,
            'cleared'   => $cleared,
            'uncleared' => round($working - $cleared, 2),
        ];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }

    public function incomeCategory(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class);
    }

    /** The withdrawal that originated this transfer's deposit leg (BelongsTo via linked_transaction_id). */
    public function linkedFrom(): BelongsTo
    {
        return $this->belongsTo(CashTransaction::class, 'linked_transaction_id');
    }

    /** The deposit leg that received this transfer's withdrawal (HasOne reverse). */
    public function linkedTo(): HasOne
    {
        return $this->hasOne(CashTransaction::class, 'linked_transaction_id');
    }

    /**
     * The other leg of the transfer this transaction is part of, if any.
     *
     * linked_transaction_id already says which side we're on, so only that one relation is
     * worth reading — loading both cost two queries to use one. loadMissing() rather than a
     * bare relation read: callers reached via route-model binding have nothing eager-loaded,
     * and Model::preventLazyLoading() is on outside production, so the implicit lazy read
     * would throw.
     */
    public function transferCounterpart(): ?self
    {
        $relation = $this->linked_transaction_id !== null ? 'linkedFrom' : 'linkedTo';

        return $this->loadMissing($relation)->{$relation};
    }

    /** Whether this transaction is one leg of a two-sided transfer. */
    public function isTransferLeg(): bool
    {
        return $this->transferCounterpart() !== null;
    }

    /**
     * Delete this transaction and, when it's one leg of a transfer, the other leg with it.
     *
     * The FK is ON DELETE SET NULL (see the linked_transaction_id migration), so removing a
     * single leg used to leave the counterpart behind as a standalone deposit/withdrawal —
     * silently moving the user's net cash by the transfer amount. A transfer is one event;
     * deleting it removes both sides.
     *
     * Returns whether a counterpart went with it, so callers don't have to probe for one
     * before the delete just to phrase their confirmation message.
     */
    public function deleteWithCounterpart(): bool
    {
        return DB::transaction(function () {
            $counterpart = $this->transferCounterpart();
            $counterpart?->delete();
            $this->delete();

            return $counterpart !== null;
        });
    }

    /**
     * Push the fields that describe the transfer *event* onto the other leg, so editing one
     * side can't leave the two halves disagreeing about how much moved and when. `cleared`
     * is deliberately excluded — the two accounts clear independently at their own banks.
     */
    public function syncTransferCounterpart(): void
    {
        $this->transferCounterpart()?->update([
            'amount'      => $this->amount,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at,
        ]);
    }

    public function toBackupArray(): array
    {
        return [
            'date'            => $this->occurred_at->toDateString(),
            'type'            => $this->type,
            'amount'          => (float) $this->amount,
            'description'     => $this->description,
            'income_category' => $this->incomeCategory?->name,
            'cleared'         => (bool) $this->cleared,
        ];
    }
}
