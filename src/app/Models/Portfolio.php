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
     * Aggregate cost basis / market value / unrealized / manual value for a
     * holdings collection (from computeHoldings()) plus this portfolio's manual
     * assets. Unpriced positions fall back to cost so they still count toward
     * market_value/total_value rather than vanishing. Shared by the dashboard's
     * per-portfolio summary and the portfolio show page so the two can't
     * silently disagree on what a portfolio is worth.
     *
     * @return array{cost_basis: float, market_value: ?float, manual_value: float, unrealized: ?float, has_price: bool, total_value: float}
     */
    public function summarizeHoldings(Collection $holdings): array
    {
        $costBasis    = $holdings->sum('total_cost');
        $priced       = $holdings->filter(fn ($h) => $h['current_value'] !== null);
        $marketValue  = $priced->sum('current_value');
        $unpricedCost = $holdings->filter(fn ($h) => $h['current_value'] === null)->sum('total_cost');
        $unrealized   = $priced->sum('unrealized_gain');
        $hasPrice     = $priced->isNotEmpty();
        $manualValue  = $this->chartManualValue();

        return [
            'cost_basis'   => round($costBasis, 2),
            'market_value' => $hasPrice ? round($marketValue + $unpricedCost, 2) : null,
            'manual_value' => round($manualValue, 2),
            'unrealized'   => $hasPrice ? round($unrealized, 2) : null,
            'has_price'    => $hasPrice,
            'total_value'  => round(($hasPrice ? $marketValue + $unpricedCost : $costBasis) + $manualValue, 2),
        ];
    }

    /**
     * Compute holdings from transactions. Cost basis (total_cost/avg_cost) is FIFO —
     * see Transaction::accumulateFifoCostBasis() — the same convention RealizedGainService
     * uses for realized gains, so a position's unrealized_gain here reconciles against
     * its realized gain rather than drifting from it.
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
                [$totalQty, $totalCost] = Transaction::accumulateFifoCostBasis($txns);

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

    public function toBackupArray(): array
    {
        return [
            'name'              => $this->name,
            'description'       => $this->description,
            'currency'          => $this->currency,
            'is_tax_advantaged' => (bool) $this->is_tax_advantaged,
            'created_at'        => $this->created_at->toDateString(),
            'transactions'      => $this->transactions->map(fn ($t) => $t->toBackupArray())->values(),
            'manual_assets'     => $this->manualAssets->map(fn ($m) => $m->toBackupArray())->values(),
            'journal_entries'   => $this->journalEntries->map(fn ($j) => $j->toBackupArray())->values(),
        ];
    }
}
