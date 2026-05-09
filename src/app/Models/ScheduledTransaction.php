<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledTransaction extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'description', 'amount', 'type', 'recurrence',
        'next_due_at', 'envelope_id', 'cash_account_id', 'is_active',
    ];

    protected $casts = [
        'amount'      => 'decimal:4',
        'next_due_at' => 'date',
        'is_active'   => 'boolean',
    ];

    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function envelope(): BelongsTo   { return $this->belongsTo(Envelope::class); }
    public function cashAccount(): BelongsTo { return $this->belongsTo(CashAccount::class); }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'envelope_fund'      => 'Fund envelope',
            'envelope_spend'     => 'Envelope spend',
            'cash_deposit'       => 'Cash deposit',
            'cash_withdrawal'    => 'Cash withdrawal',
            default              => $this->type,
        };
    }

    public function recurrenceLabel(): string
    {
        return match ($this->recurrence) {
            'weekly'   => 'Weekly',
            'biweekly' => 'Biweekly',
            default    => 'Monthly',
        };
    }
}
