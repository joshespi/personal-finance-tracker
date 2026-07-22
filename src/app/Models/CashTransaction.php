<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** The other leg of the transfer this transaction is part of, if any. */
    public function transferCounterpart(): ?self
    {
        return $this->linkedTo ?? $this->linkedFrom;
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
