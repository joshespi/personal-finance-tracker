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
        if ($r > 0) {
            $gf = pow(1 + $r, $months);

            return $pv * $gf + $pmt * ($gf - 1) / $r;
        }

        return $pv + $pmt * $months;
    }
}
