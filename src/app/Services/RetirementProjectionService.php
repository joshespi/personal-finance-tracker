<?php

namespace App\Services;

use App\Models\User;
use App\Support\Finance;

class RetirementProjectionService
{
    /**
     * Project retirement readiness for a user from an already-resolved set of
     * inputs (request parsing/defaulting stays in the controller since
     * `$currentValue`'s default depends on `$defaultValue`, resolved there).
     */
    public function compute(
        User $user,
        ?int $birthYear,
        int $retirementAge,
        float $currentValue,
        float $monthlyContrib,
        float $annualReturn,
        ?float $annualExpenses,
    ): array {
        $currentYear = now()->year;

        $annualIncome = round($user->incomeForTrailingMonths(6) / 6 * 12, 2);

        $age = $birthYear !== null ? ($currentYear - $birthYear) : null;

        $result = null;

        if ($age !== null && $age < $retirementAge) {
            $monthsLeft   = ($retirementAge - $age) * 12;
            $r            = Finance::monthlyRate($annualReturn);
            $growthFactor = pow(1 + $r, $monthsLeft);

            $projectedFv = Finance::futureValue($currentValue, $monthlyContrib, $r, $monthsLeft);

            $incomeBase = $annualExpenses ?? ($annualIncome > 0 ? $annualIncome : null);
            $target     = $incomeBase ? round($incomeBase * 25, 2) : null;

            $gap             = $target !== null ? round($target - $projectedFv, 2) : null;
            $requiredContrib = null;

            if ($target !== null && $gap > 0) {
                $needed = $target - $currentValue * $growthFactor;
                if ($r > 0 && $growthFactor > 1) {
                    $requiredContrib = round(max(0, $needed * $r / ($growthFactor - 1)), 2);
                } elseif ($monthsLeft > 0) {
                    $requiredContrib = round(max(0, $needed / $monthsLeft), 2);
                }
            } elseif ($target !== null) {
                $requiredContrib = 0;
            }

            $benchmarks = [];
            if ($annualIncome > 0) {
                foreach ([[30, 1], [35, 2], [40, 3], [45, 4], [50, 6], [55, 7], [60, 8], [67, 10]] as [$benchAge, $mult]) {
                    if ($benchAge < $age) {
                        continue;
                    }
                    $months       = ($benchAge - $age) * 12;
                    $proj         = Finance::futureValue($currentValue, $monthlyContrib, $r, $months);
                    $benchmarks[] = [
                        'age'       => $benchAge,
                        'multiple'  => $mult,
                        'target'    => round($annualIncome * $mult, 2),
                        'projected' => round($proj, 2),
                        'on_track'  => $proj >= $annualIncome * $mult,
                    ];
                }
            }

            $result = [
                'years_left'       => $retirementAge - $age,
                'projected_fv'     => round($projectedFv, 2),
                'target'           => $target,
                'gap'              => $gap,
                'required_contrib' => $requiredContrib,
                'benchmarks'       => $benchmarks,
                'on_track'         => $target !== null ? ($projectedFv >= $target) : null,
            ];
        }

        return [
            'birthYear'      => $birthYear,
            'age'            => $age,
            'retirementAge'  => $retirementAge,
            'currentValue'   => $currentValue,
            'monthlyContrib' => $monthlyContrib,
            'annualReturn'   => $annualReturn,
            'annualExpenses' => $annualExpenses,
            'annualIncome'   => $annualIncome,
            'result'         => $result,
        ];
    }
}
