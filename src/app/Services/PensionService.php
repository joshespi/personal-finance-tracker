<?php

namespace App\Services;

use App\Models\Pension;

class PensionService
{
    /**
     * Everything the pension screen needs for a single defined-benefit pension:
     * accrual (years/%, accrued annual benefit), the present value of the accrued
     * benefit today (a COLA'd real life annuity, deferred to the draw age), the
     * projected benefit if service continues to retirement, and the marginal value
     * of one more year of service.
     *
     * A COLA'd pension is valued in real terms: benefits keep pace with inflation,
     * so we use a real discount rate and value the payout stream as a finite life
     * annuity (draw age → life expectancy), then discount back to today at the same
     * real rate. $overrides lets the screen explore sensitivity without editing the
     * stored config: retirement_age, life_expectancy, discount_rate.
     *
     * @return array<string, mixed>
     */
    public function compute(Pension $pension, array $overrides = []): array
    {
        $mult   = (float) $pension->multiplier_pct;
        $fas    = (float) $pension->final_average_salary;
        $growth = (float) $pension->salary_growth_pct / 100;
        $s0     = (float) $pension->service_credit_years;
        $cap    = $pension->service_cap_years !== null ? (float) $pension->service_cap_years : null;

        $retirementAge  = (int) ($overrides['retirement_age'] ?? $pension->retirement_age);
        $lifeExpectancy = (int) ($overrides['life_expectancy'] ?? $pension->life_expectancy_age);
        $d              = (float) ($overrides['discount_rate'] ?? $pension->discount_rate_pct) / 100;

        $retirementAge  = max(40, min(90, $retirementAge));
        $lifeExpectancy = max($retirementAge + 1, min(110, $lifeExpectancy));
        $d              = max(0, min(0.10, $d));

        $currentAge = $pension->birth_year ? (now()->year - (int) $pension->birth_year) : null;
        $t          = $currentAge !== null ? max(0, $retirementAge - $currentAge) : 0;
        $n          = max(0, $lifeExpectancy - $retirementAge);

        $s0Capped = $cap !== null ? min($s0, $cap) : $s0;
        $s1       = $cap !== null ? min($s0 + $t, $cap) : $s0 + $t;

        $fasRet = $fas * pow(1 + $growth, $t);

        $accruedAnnual   = ($s0Capped * $mult / 100) * $fas;
        $projectedAnnual = ($s1 * $mult / 100) * $fasRet;

        // An exact URS "Benefit Estimate" monthly figure, when provided, wins over the
        // service × multiplier × FAS formula. The accrued figure is then a pro-rata
        // share of that estimate by earned-vs-total service credit.
        $usesEstimate = $pension->monthly_benefit_estimate !== null;
        if ($usesEstimate) {
            $projectedAnnual = (float) $pension->monthly_benefit_estimate * 12;
            $accruedAnnual   = $s1 > 0 ? $projectedAnnual * ($s0Capped / $s1) : 0.0;
        }

        $annuityFactor = $d > 0 ? (1 - pow(1 + $d, -$n)) / $d : $n;
        $deferral      = pow(1 + $d, $t);

        $pvAccrued   = $accruedAnnual * $annuityFactor / $deferral;
        $pvProjected = $projectedAnnual * $annuityFactor / $deferral;

        // "Each additional year of service adds multiplier% × FAS per year, for life."
        $marginalAnnualPerYear = ($mult / 100) * $fas;
        $marginalPvPerYear     = $marginalAnnualPerYear * $annuityFactor / $deferral;

        return [
            'currentAge'            => $currentAge,
            'yearsToDraw'           => $t,
            'payoutYears'           => $n,
            'serviceNow'            => round($s0, 3),
            'serviceAtRetirement'   => round($s1, 3),
            'accruedPct'            => round($s0Capped * $mult, 2),
            'projectedPct'          => round($s1 * $mult, 2),
            'fasNow'                => round($fas, 2),
            'fasAtRetirement'       => round($fasRet, 2),
            'accruedAnnual'         => round($accruedAnnual, 2),
            'projectedAnnual'       => round($projectedAnnual, 2),
            'accruedMonthly'        => round($accruedAnnual / 12, 2),
            'projectedMonthly'      => round($projectedAnnual / 12, 2),
            'annuityFactor'         => round($annuityFactor, 3),
            'pvAccrued'             => round($pvAccrued, 2),
            'pvProjected'           => round($pvProjected, 2),
            'marginalAnnualPerYear' => round($marginalAnnualPerYear, 2),
            'marginalPvPerYear'     => round($marginalPvPerYear, 2),
            'usesEstimate'          => $usesEstimate,
            'retirementAge'         => $retirementAge,
            'lifeExpectancy'        => $lifeExpectancy,
            'discountRatePct'       => round($d * 100, 2),
        ];
    }

    /** Accrued present value today — the figure the dashboard folds into net worth. */
    public function presentValue(Pension $pension, array $overrides = []): float
    {
        return $this->compute($pension, $overrides)['pvAccrued'];
    }

    /**
     * Retirement income floor: the COLA'd pension income plus what the investable
     * portfolio supports at a safe withdrawal rate, once the portfolio is grown to
     * the draw age. Compared against optional target annual expenses.
     *
     * @return array<string, mixed>
     */
    public function incomeFloor(float $portfolioBase, float $pensionAnnual, int $yearsToRetirement, array $overrides = []): array
    {
        $expectedReturn = max(0, min(30, (float) ($overrides['expected_return'] ?? 7.0)));
        $swr            = max(0, min(20, (float) ($overrides['swr'] ?? 4.0)));
        $monthly        = max(0, (float) ($overrides['monthly_contribution'] ?? 0));
        $annualExpenses = isset($overrides['annual_expenses']) && $overrides['annual_expenses'] !== null
            ? max(0, (float) $overrides['annual_expenses'])
            : null;

        $r      = pow(1 + $expectedReturn / 100, 1 / 12) - 1;
        $months = max(0, $yearsToRetirement) * 12;

        $portfolioAtRetirement = $this->futureValue($portfolioBase, $monthly, $r, $months);
        $portfolioAnnualIncome = $portfolioAtRetirement * $swr / 100;
        $totalAnnual           = $pensionAnnual + $portfolioAnnualIncome;

        $surplus = $annualExpenses !== null ? round($totalAnnual - $annualExpenses, 2) : null;

        return [
            'portfolioBase'          => round($portfolioBase, 2),
            'portfolioAtRetirement'  => round($portfolioAtRetirement, 2),
            'portfolioAnnualIncome'  => round($portfolioAnnualIncome, 2),
            'portfolioMonthlyIncome' => round($portfolioAnnualIncome / 12, 2),
            'pensionAnnual'          => round($pensionAnnual, 2),
            'pensionMonthly'         => round($pensionAnnual / 12, 2),
            'totalAnnual'            => round($totalAnnual, 2),
            'totalMonthly'           => round($totalAnnual / 12, 2),
            'annualExpenses'         => $annualExpenses,
            'surplus'                => $surplus,
            'onTrack'                => $surplus !== null ? $surplus >= 0 : null,
            'expectedReturn'         => $expectedReturn,
            'swr'                    => $swr,
            'monthlyContribution'    => $monthly,
            'yearsToRetirement'      => $yearsToRetirement,
        ];
    }

    /** Monthly-compounded future value of a lump sum plus a level monthly contribution. */
    private function futureValue(float $pv, float $pmt, float $r, int $months): float
    {
        if ($r > 0) {
            $gf = pow(1 + $r, $months);

            return $pv * $gf + $pmt * ($gf - 1) / $r;
        }

        return $pv + $pmt * $months;
    }
}
