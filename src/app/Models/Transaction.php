<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'portfolio_id', 'asset_id', 'type', 'quantity',
        'price_per_unit', 'fees', 'currency', 'notes', 'transacted_at',
    ];

    protected $casts = [
        'transacted_at' => 'datetime',
        'quantity'       => 'decimal:8',
        'price_per_unit' => 'decimal:8',
        'fees'           => 'decimal:8',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** Total cost including fees (for buys) */
    public function totalCost(): float
    {
        return (float) $this->quantity * (float) $this->price_per_unit + (float) $this->fees;
    }
}
