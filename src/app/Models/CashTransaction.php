<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTransaction extends Model
{
    protected $fillable = ['cash_account_id', 'type', 'amount', 'description', 'occurred_at'];

    protected $casts = [
        'occurred_at' => 'date',
        'amount'      => 'decimal:8',
    ];

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class);
    }
}
