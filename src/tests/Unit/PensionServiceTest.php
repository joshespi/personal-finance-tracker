<?php

namespace Tests\Unit;

use App\Models\Pension;
use App\Services\PensionService;
use Tests\TestCase;

class PensionServiceTest extends TestCase
{
    private function makePension(array $overrides = []): Pension
    {
        return new Pension(array_merge([
            'name'                     => 'URS Pension',
            'service_credit_years'     => 16.588,
            'service_cap_years'        => null,
            'multiplier_pct'           => 2.0,
            'final_average_salary'     => 100000,
            'salary_growth_pct'        => 0,
            'cola_pct'                 => 4.0,
            'monthly_benefit_estimate' => null,
            'birth_year'               => now()->year - 38, // age 38 → 14 yrs to a draw age of 52
            'retirement_age'           => 52,
            'life_expectancy_age'      => 90,
            'discount_rate_pct'        => 2.0,
            'include_in_net_worth'     => true,
            'currency'                 => 'USD',
        ], $overrides));
    }

    public function test_accrual_matches_service_times_multiplier_times_fas(): void
    {
        $result = (new PensionService)->compute($this->makePension());

        // 16.588 yrs × 2% = 33.176% of FAS
        $this->assertEqualsWithDelta(33.18, $result['accruedPct'], 0.01);
        $this->assertEqualsWithDelta(33176.0, $result['accruedAnnual'], 0.01);
        $this->assertEqualsWithDelta(14, $result['yearsToDraw'], 0.01);
        $this->assertEqualsWithDelta(38, $result['payoutYears'], 0.01);
    }

    public function test_present_value_is_a_finite_life_annuity_discounted_to_today(): void
    {
        $service = new PensionService;
        $result  = $service->compute($this->makePension());

        $d  = 0.02;
        $n  = 38;
        $t  = 14;
        $aN = (1 - pow(1 + $d, -$n)) / $d;

        // COLA'd pension at a 2% real rate capitalizes at ~26× the annual benefit.
        $this->assertEqualsWithDelta(26.44, $result['annuityFactor'], 0.05);

        $expectedPv = 33176 * $aN / pow(1 + $d, $t);
        $this->assertEqualsWithDelta($expectedPv, $result['pvAccrued'], 1.0);

        // presentValue() is a convenience over compute()['pvAccrued'].
        $this->assertSame($result['pvAccrued'], $service->presentValue($this->makePension()));
    }

    public function test_higher_discount_rate_lowers_present_value(): void
    {
        $service = new PensionService;

        $low  = $service->presentValue($this->makePension(), ['discount_rate' => 2.0]);
        $high = $service->presentValue($this->makePension(), ['discount_rate' => 4.0]);

        $this->assertGreaterThan($high, $low);
    }

    public function test_benefit_estimate_overrides_the_formula(): void
    {
        $result = (new PensionService)->compute($this->makePension(['monthly_benefit_estimate' => 5000]));

        $this->assertTrue($result['usesEstimate']);
        $this->assertEqualsWithDelta(60000.0, $result['projectedAnnual'], 0.01);
        // Accrued is a pro-rata share by earned-vs-total service (16.588 / 30.588).
        $this->assertEqualsWithDelta(60000 * (16.588 / 30.588), $result['accruedAnnual'], 1.0);
    }

    public function test_income_floor_is_pension_plus_portfolio_swr(): void
    {
        $service = new PensionService;

        $floor = $service->incomeFloor(
            portfolioBase: 100000,
            pensionAnnual: 36000,
            yearsToRetirement: 14,
            overrides: ['expected_return' => 7.0, 'swr' => 4.0, 'annual_expenses' => 40000],
        );

        $r      = pow(1 + 0.07, 1 / 12) - 1;
        $fv     = 100000 * pow(1 + $r, 168);
        $expect = 36000 + $fv * 0.04;

        $this->assertEqualsWithDelta($fv, $floor['portfolioAtRetirement'], 1.0);
        $this->assertEqualsWithDelta($expect, $floor['totalAnnual'], 1.0);
        $this->assertEqualsWithDelta($expect - 40000, $floor['surplus'], 1.0);
        $this->assertTrue($floor['onTrack']);
    }

    public function test_missing_birth_year_defers_zero_years(): void
    {
        $result = (new PensionService)->compute($this->makePension(['birth_year' => null]));

        $this->assertNull($result['currentAge']);
        $this->assertSame(0, $result['yearsToDraw']);
    }

    public function test_service_cap_limits_projected_service(): void
    {
        // 16.588 earned + 14 more to the draw age = 30.588, but the plan caps at 20.
        $result = (new PensionService)->compute($this->makePension(['service_cap_years' => 20]));

        $this->assertEqualsWithDelta(20.0, $result['serviceAtRetirement'], 0.001);
        $this->assertEqualsWithDelta(40.0, $result['projectedPct'], 0.01); // 20 yrs × 2%

        // The cap sits above earned service, so today's accrual is unaffected.
        $this->assertEqualsWithDelta(33.18, $result['accruedPct'], 0.01);
    }

    public function test_salary_growth_lifts_projected_but_not_accrued_benefit(): void
    {
        $service = new PensionService;
        $flat    = $service->compute($this->makePension(['salary_growth_pct' => 0]));
        $grown   = $service->compute($this->makePension(['salary_growth_pct' => 2.0]));

        // Accrued is measured at today's FAS, so salary growth leaves it untouched.
        $this->assertEqualsWithDelta($flat['accruedAnnual'], $grown['accruedAnnual'], 0.01);

        // FAS is grown to the draw age (t = 14 yrs) at 2%; the projected benefit follows.
        $this->assertEqualsWithDelta(100000 * pow(1.02, 14), $grown['fasAtRetirement'], 1.0);
        $this->assertGreaterThan($flat['projectedAnnual'], $grown['projectedAnnual']);
    }

    public function test_marginal_year_of_service_is_worth_multiplier_times_fas_for_life(): void
    {
        $result = (new PensionService)->compute($this->makePension());

        // One more year adds 2% × $100k = $2,000/yr for life.
        $this->assertEqualsWithDelta(2000.0, $result['marginalAnnualPerYear'], 0.01);

        $d          = 0.02;
        $n          = 38;
        $t          = 14;
        $aN         = (1 - pow(1 + $d, -$n)) / $d;
        $expectedPv = 2000 * $aN / pow(1 + $d, $t);

        $this->assertEqualsWithDelta($expectedPv, $result['marginalPvPerYear'], 1.0);
    }

    public function test_zero_discount_rate_values_benefit_at_undiscounted_payout_years(): void
    {
        $result = (new PensionService)->compute($this->makePension(), ['discount_rate' => 0]);

        // With d = 0 the annuity factor collapses to the raw payout-year count and
        // the deferral factor is 1 — no time-value discount anywhere.
        $this->assertEqualsWithDelta($result['payoutYears'], $result['annuityFactor'], 0.001);
        $this->assertEqualsWithDelta($result['accruedAnnual'] * $result['payoutYears'], $result['pvAccrued'], 1.0);
    }

    public function test_income_floor_with_zero_return_is_a_simple_sum_of_contributions(): void
    {
        $floor = (new PensionService)->incomeFloor(
            portfolioBase: 100000,
            pensionAnnual: 30000,
            yearsToRetirement: 10,
            overrides: ['expected_return' => 0, 'monthly_contribution' => 1000, 'swr' => 4.0],
        );

        // r = 0: future value is just the base plus level contributions, no compounding.
        $this->assertEqualsWithDelta(100000 + 1000 * 120, $floor['portfolioAtRetirement'], 0.01);
    }
}
