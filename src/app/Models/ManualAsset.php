<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ManualAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_id', 'name', 'description', 'asset_class', 'cost_basis', 'currency',
        'tracking_method', 'proxy_asset_id', 'anchor_value', 'anchor_date', 'anchor_synthetic_shares',
    ];

    protected $casts = [
        'cost_basis'              => 'decimal:2',
        'anchor_value'            => 'decimal:2',
        'anchor_date'             => 'date',
        'anchor_synthetic_shares' => 'decimal:8',
    ];

    public function profitLoss(): ?float
    {
        if ($this->cost_basis === null || ! $this->latestValuation) {
            return null;
        }

        return (float) $this->latestValuation->value - (float) $this->cost_basis;
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function valuations(): HasMany
    {
        return $this->hasMany(ManualValuation::class);
    }

    public function latestValuation(): HasOne
    {
        return $this->hasOne(ManualValuation::class)->latestOfMany('valued_at');
    }

    public function liabilities(): HasMany
    {
        return $this->hasMany(Liability::class);
    }

    public function proxyAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'proxy_asset_id');
    }

    public function currentValue(): float
    {
        if ($this->tracking_method === 'proxy_ticker') {
            $price = $this->proxyAsset?->latestPrice?->price;
            if ($price !== null && $this->anchor_synthetic_shares !== null) {
                return round((float) $this->anchor_synthetic_shares * (float) $price, 2);
            }
            return (float) ($this->anchor_value ?? 0.0);
        }

        return $this->latestValuation ? (float) $this->latestValuation->value : 0.0;
    }
}
