<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    use HasFactory;

    protected $fillable = ['cash_account_id', 'envelope_id', 'income_category_id', 'type', 'cleared', 'amount', 'description', 'occurred_at'];

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
}
