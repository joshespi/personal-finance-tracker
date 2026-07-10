<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'description', 'currency', 'is_tax_advantaged'];

    protected $casts = [
        'is_tax_advantaged' => 'boolean',
        'closed_at'         => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->whereNull('closed_at');
    }

    public function scopeClosed($query)
    {
        return $query->whereNotNull('closed_at');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function manualAssets(): HasMany
    {
        return $this->hasMany(ManualAsset::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(PortfolioSnapshot::class);
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class)->orderByDesc('entry_date')->orderByDesc('id');
    }

    public function slices(): HasMany
    {
        return $this->hasMany(PortfolioSlice::class);
    }

    public function chartManualValue(): float
    {
        return (float) $this->manualAssets->where('include_in_chart', true)->sum(fn ($ma) => $ma->currentValue());
    }

    /**
     * Compute holdings from transactions.
     * Requires transactions.asset.latestPrice and manualAssets.latestValuation to be loaded.
     */
    public function computeHoldings(): Collection
    {
        if (! $this->relationLoaded('transactions')) {
            $this->load('transactions.asset.latestPrice');
        }

        return $this->transactions
            ->filter(fn ($t) => $t->type->affectsPosition())
            ->groupBy('asset_id')
            ->map(function ($txns) {
                $asset                  = $txns->first()->asset;
                [$totalQty, $totalCost] = Transaction::accumulateCostBasis($txns);

                $latestPrice    = $asset->latestPrice ? (float) $asset->latestPrice->price : null;
                $currentValue   = $latestPrice !== null ? round($totalQty * $latestPrice, 2) : null;
                $unrealizedGain = $currentValue !== null ? round($currentValue - $totalCost, 2) : null;
                $unrealizedPct  = ($unrealizedGain !== null && $totalCost > 0)
                    ? round($unrealizedGain / $totalCost * 100, 2)
                    : null;

                return [
                    'asset'           => $asset,
                    'quantity'        => $totalQty,
                    'avg_cost'        => $totalQty > 0 ? round($totalCost / $totalQty, 8) : 0.0,
                    'total_cost'      => round($totalCost, 2),
                    'current_price'   => $latestPrice,
                    'current_value'   => $currentValue,
                    'effective_value' => $currentValue ?? round($totalCost, 2),
                    'unrealized_gain' => $unrealizedGain,
                    'unrealized_pct'  => $unrealizedPct,
                ];
            })
            ->filter(fn ($h) => $h['quantity'] > 0)
            ->sortBy(fn ($h) => $h['asset']->symbol)
            ->values();
    }
}
