<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class PortfolioSnapshot extends Model
{
    protected $fillable = ['portfolio_id', 'cost_basis', 'market_value', 'manual_value', 'recorded_on'];

    protected $casts = [
        'recorded_on'  => 'date:Y-m-d',
        'cost_basis'   => 'decimal:8',
        'market_value' => 'decimal:8',
        'manual_value' => 'decimal:8',
    ];

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    /**
     * Sum of (market_value + manual_value) across each portfolio's most recent snapshot,
     * optionally only counting snapshots on or before $asOf. Null if none of the portfolios
     * has a qualifying snapshot (SUM over zero rows is SQL NULL — no separate exists() probe).
     */
    public static function latestTotalFor(Collection $portfolioIds, ?CarbonInterface $asOf = null): ?float
    {
        if ($portfolioIds->isEmpty()) {
            return null;
        }

        $latestDates = static::whereIn('portfolio_id', $portfolioIds)
            ->when($asOf, fn ($q) => $q->where('recorded_on', '<=', $asOf->toDateString()))
            ->selectRaw('portfolio_id, MAX(recorded_on) as max_date')
            ->groupBy('portfolio_id');

        $total = static::joinSub($latestDates, 'latest', fn ($j) => $j
            ->on('portfolio_snapshots.portfolio_id', '=', 'latest.portfolio_id')
            ->on('portfolio_snapshots.recorded_on', '=', 'latest.max_date')
        )->selectRaw('SUM(portfolio_snapshots.market_value + portfolio_snapshots.manual_value) as total')
            ->value('total');

        return $total === null ? null : (float) $total;
    }
}
