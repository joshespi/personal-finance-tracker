<?php

namespace App\Support;

class Rebalancing
{
    /**
     * Current/target/diff/drift math for one rebalancing-table row, shared by the
     * dashboard's global (asset-class) table and the per-portfolio slice table.
     */
    public static function driftRow(float $currentVal, float $targetPct, float $total): array
    {
        $currentVal = round($currentVal, 2);
        $targetVal  = round($total * $targetPct / 100, 2);
        $currentPct = round($currentVal / $total * 100, 1);

        return [
            'current_pct' => $currentPct,
            'target_pct'  => $targetPct,
            'current_val' => $currentVal,
            'target_val'  => $targetVal,
            'diff'        => round($targetVal - $currentVal, 2),
            'drift_pct'   => round($currentPct - $targetPct, 1),
        ];
    }
}
