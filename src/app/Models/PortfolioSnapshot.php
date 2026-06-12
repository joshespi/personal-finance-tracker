<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioSnapshot extends Model
{
    protected $fillable = ['portfolio_id', 'cost_basis', 'market_value', 'manual_value', 'excluded_value', 'recorded_on'];

    protected $casts = [
        'recorded_on'    => 'date:Y-m-d',
        'cost_basis'     => 'decimal:8',
        'market_value'   => 'decimal:8',
        'manual_value'   => 'decimal:8',
        'excluded_value' => 'decimal:8',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }
}
