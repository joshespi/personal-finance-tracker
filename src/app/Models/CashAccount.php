<?php

namespace App\Models;

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

    /** Working balance — every transaction, cleared or not. */
    public function balance(): float
    {
        return $this->balanceQuery();
    }

    /** Cleared balance — only transactions that have cleared the bank. */
    public function clearedBalance(): float
    {
        return $this->balanceQuery(true);
    }

    /** Uncleared (pending) balance — working minus cleared. */
    public function unclearedBalance(): float
    {
        return $this->balanceQuery(false);
    }

    /**
     * Working, cleared and pending balances in a single query — for views that show all
     * three at once. Pending is derived arithmetically rather than summed a third time.
     *
     * @return array{working: float, cleared: float, uncleared: float}
     */
    public function balances(): array
    {
        return CashTransaction::balanceTotals($this->transactions());
    }

    private function balanceQuery(?bool $cleared = null): float
    {
        $query = $this->transactions();

        if ($cleared !== null) {
            $query->where('cleared', $cleared);
        }

        return (float) $query
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END), 0) AS bal")
            ->value('bal');
    }
}
