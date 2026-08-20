<?php

namespace App\Support;

class Finance
{
    /** Effective monthly rate for a compounding annual return given in percent. */
    public static function monthlyRate(float $annualPct): float
    {
        return pow(1 + $annualPct / 100, 1 / 12) - 1;
    }

    /**
     * Future value of a starting balance plus a level monthly contribution,
     * compounded at monthly rate $r. Shared by the retirement (Planning),
     * pension, and FIRE-forecast calculators.
     */
    public static function futureValue(float $pv, float $pmt, float $r, int $months): float
    {
        // Any non-zero rate compounds — including a negative one. The guard is only here to
        // avoid dividing by zero at r = 0, where the annuity formula degenerates to pv + pmt*n;
        // testing `$r > 0` instead sent every negative rate down the no-growth branch.
        if (abs($r) > 1e-12) {
            $gf = pow(1 + $r, $months);

            return $pv * $gf + $pmt * ($gf - 1) / $r;
        }

        return $pv + $pmt * $months;
    }
}
