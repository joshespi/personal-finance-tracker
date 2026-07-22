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

    public const ASSET_CLASSES = [
        'real_estate' => 'Real Estate',
        'stock'       => 'Stocks / Index Fund',
        'bond'        => 'Bond Fund',
        'crypto'      => 'Crypto Fund',
        'vehicle'     => 'Vehicle',
        'collectible' => 'Collectible',
        'business'    => 'Business',
        'other'       => 'Other',
    ];

    protected $fillable = [
        'portfolio_id', 'name', 'description', 'asset_class', 'cost_basis', 'currency',
        'tracking_method', 'proxy_asset_id', 'anchor_value', 'anchor_date', 'anchor_synthetic_shares',
        'include_in_chart', 'include_in_invested',
    ];

    protected $casts = [
        'cost_basis'              => 'decimal:2',
        'anchor_value'            => 'decimal:2',
        'anchor_date'             => 'date',
        'anchor_synthetic_shares' => 'decimal:8',
        'include_in_chart'        => 'boolean',
        'include_in_invested'     => 'boolean',
    ];

    /** Whether this asset derives its value from a proxy ticker instead of manual valuations. */
    public function isProxyTracked(): bool
    {
        return $this->tracking_method === 'proxy_ticker';
    }

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
        if ($this->isProxyTracked()) {
            $price = $this->proxyAsset?->latestPrice?->price;

            return $this->proxyValueAt(
                $price !== null ? (float) $price : null,
                $this->anchor_synthetic_shares !== null ? (float) $this->anchor_synthetic_shares : null,
            );
        }

        return $this->latestValuation ? (float) $this->latestValuation->value : 0.0;
    }

    /**
     * Value of a proxy-tracked asset given an already-resolved price and share count —
     * shared by the live currentValue() above (latest price, stored share count) and
     * PortfolioSnapshotBackfillService's historical path (as-of-date price, a share count
     * that's sometimes derived rather than stored), which differ only in how those two
     * inputs get resolved. Falls back to the stored anchor snapshot when either is
     * unavailable, or when shares resolve to zero.
     */
    public function proxyValueAt(?float $price, ?float $shares): float
    {
        if ($price !== null && $shares !== null && $shares > 0) {
            return round($shares * $price, 2);
        }

        return (float) ($this->anchor_value ?? 0.0);
    }

    public function toBackupArray(): array
    {
        return [
            'name'            => $this->name,
            'asset_class'     => $this->asset_class,
            'cost_basis'      => $this->cost_basis !== null ? (float) $this->cost_basis : null,
            'currency'        => $this->currency,
            'tracking_method' => $this->tracking_method,
            'description'     => $this->description,
            'valuations'      => $this->valuations->map(fn ($v) => $v->toBackupArray())->values(),
        ];
    }
}
