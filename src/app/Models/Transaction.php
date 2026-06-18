<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Transaction extends Model
{
    use HasFactory;

    public const INFLOW_TYPES = ['buy', 'transfer_in', 'staking_reward'];

    public const OUTFLOW_TYPES = ['sell', 'transfer_out'];

    public const POSITION_TYPES = ['buy', 'sell', 'transfer_in', 'transfer_out', 'staking_reward'];

    protected $fillable = [
        'portfolio_id', 'asset_id', 'type', 'quantity',
        'price_per_unit', 'fees', 'fee_in_asset', 'currency', 'notes', 'transacted_at',
        'linked_transfer_id',
    ];

    protected $casts = [
        'transacted_at'  => 'datetime',
        'quantity'       => 'decimal:8',
        'price_per_unit' => 'decimal:8',
        'fees'           => 'decimal:8',
        'fee_in_asset'   => 'boolean',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** The transfer_out that originated this transfer_in (BelongsTo via linked_transfer_id) */
    public function linkedFrom(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transfer_id');
    }

    /** The transfer_in that received funds from this transfer_out (HasOne reverse) */
    public function linkedTo(): HasOne
    {
        return $this->hasOne(Transaction::class, 'linked_transfer_id');
    }

    /** Total cost including fees (for buys) */
    public function totalCost(): float
    {
        return (float) $this->quantity * (float) $this->price_per_unit + (float) $this->fees;
    }

    /** Dividend received: quantity × price_per_unit (no fees) */
    public function dividendValue(): float
    {
        return (float) $this->quantity * (float) $this->price_per_unit;
    }
}
