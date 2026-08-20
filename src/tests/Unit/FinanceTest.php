<?php

namespace Tests\Unit;

use App\Support\Finance;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    public function test_monthly_rate_compounds_to_the_annual_rate(): void
    {
        $r = Finance::monthlyRate(7.0);

        $this->assertEqualsWithDelta(1.07, pow(1 + $r, 12), 1e-9);
    }

    public function test_future_value_compounds_a_positive_rate(): void
    {
        // $1,000 at 1%/mo for 12 months, no contributions.
        $this->assertEqualsWithDelta(1000 * pow(1.01, 12), Finance::futureValue(1000, 0, 0.01, 12), 1e-6);
    }

    public function test_future_value_falls_back_to_simple_addition_at_a_zero_rate(): void
    {
        $this->assertEqualsWithDelta(1000 + 100 * 12, Finance::futureValue(1000, 100, 0.0, 12), 1e-9);
    }

    /**
     * Regression: the guard used to be `if ($r > 0)`, so every negative rate fell through to
     * the no-growth branch and a declining projection came back *flat* — $1,000 shrinking at
     * 1%/mo was reported as $1,000. The guard only exists to avoid dividing by zero at r = 0.
     */
    public function test_future_value_compounds_a_negative_rate(): void
    {
        $expected = 1000 * pow(0.99, 12);

        $this->assertEqualsWithDelta($expected, Finance::futureValue(1000, 0, -0.01, 12), 1e-6);
        $this->assertLessThan(1000, Finance::futureValue(1000, 0, -0.01, 12));
    }

    public function test_future_value_annuity_term_holds_at_a_negative_rate(): void
    {
        // Contributions must still be discounted/compounded, not merely summed.
        $r        = -0.01;
        $gf       = pow(1 + $r, 12);
        $expected = 1000 * $gf + 100 * ($gf - 1) / $r;

        $this->assertEqualsWithDelta($expected, Finance::futureValue(1000, 100, $r, 12), 1e-6);
    }
}
