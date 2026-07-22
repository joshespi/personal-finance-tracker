<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Support\Rebalancing;
use Illuminate\Support\Collection;

class PortfolioAllocationService
{
    /** Per-holding allocation breakdown (by symbol) within a single portfolio. */
    public function buildAllocation(Collection $holdings, Portfolio $portfolio): array
    {
        $byHolding = $holdings->map(fn ($h) => [
            'symbol' => $h['asset']->symbol,
            'value'  => round($h['effective_value'], 2),
            'type'   => $h['asset']->asset_type,
        ])->sortByDesc('value')->values();

        $manualValue = $portfolio->chartManualValue();

        $total = $byHolding->sum('value') + $manualValue;

        return [
            'holdings'     => $byHolding,
            'manual_value' => round($manualValue, 2),
            'total'        => round($total, 2),
        ];
    }

    /** Drift table against this portfolio's own PortfolioSlice targets (per-holding, not asset-class). */
    public function buildSliceRebalancing(Collection $holdings, Portfolio $portfolio): array
    {
        $slices = $portfolio->slices;

        if ($slices->isEmpty()) {
            return [];
        }

        $holdingsByAssetId = $holdings->keyBy(fn ($h) => $h['asset']->id);

        $total = $holdings->sum('effective_value') + $portfolio->chartManualValue();

        if ($total <= 0) {
            return [];
        }

        $rows = [];
        foreach ($slices as $slice) {
            $holding = $holdingsByAssetId->get($slice->asset_id);

            $rows[] = ['symbol' => $slice->asset->symbol]
                + Rebalancing::driftRow((float) ($holding['effective_value'] ?? 0), (float) $slice->target_pct, $total)
                + ['slice_id' => $slice->id];
        }

        usort($rows, fn ($a, $b) => $b['target_pct'] <=> $a['target_pct']);

        return $rows;
    }
}
