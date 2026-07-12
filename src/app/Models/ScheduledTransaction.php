<?php

namespace App\Models;

use App\Enums\Recurrence;
use App\Enums\ScheduledTransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'description', 'amount', 'type', 'recurrence',
        'next_due_at', 'envelope_id', 'cash_account_id', 'liability_id', 'is_active',
    ];

    protected $casts = [
        'amount'      => 'decimal:4',
        'next_due_at' => 'date',
        'is_active'   => 'boolean',
        'recurrence'  => Recurrence::class,
        'type'        => ScheduledTransactionType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }

    public function liability(): BelongsTo
    {
        return $this->belongsTo(Liability::class);
    }

    public function typeLabel(): string
    {
        return $this->type->label();
    }

    /** Whether this scheduled entry increases a balance (deposit/fund) vs. draws it down. */
    public function isInflow(): bool
    {
        return $this->type->isInflow();
    }

    /**
     * Secondary ordering that sorts inflows below outflows — the SQL twin of
     * isInflow(), kept here so the two stay in lockstep with the enum.
     */
    public function scopeInflowsLast(Builder $query): Builder
    {
        $inflows      = ScheduledTransactionType::inflowValues();
        $placeholders = implode(',', array_fill(0, count($inflows), '?'));

        return $query->orderByRaw("CASE WHEN type IN ({$placeholders}) THEN 1 ELSE 0 END", $inflows);
    }

    public function recurrenceLabel(): string
    {
        return ($this->recurrence ?? Recurrence::Monthly)->label();
    }
}
