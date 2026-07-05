<?php

namespace Database\Factories;

use App\Models\Pension;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pension>
 */
class PensionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'                  => User::factory(),
            'name'                     => 'URS Pension',
            'plan_label'               => 'Tier 1 Public Employees',
            'membership_date'          => '2009-12-01',
            'service_credit_years'     => 16.588,
            'service_cap_years'        => null,
            'multiplier_pct'           => 2.000,
            'final_average_salary'     => 100000,
            'salary_growth_pct'        => 0,
            'cola_pct'                 => 4.00,
            'monthly_benefit_estimate' => null,
            'birth_year'               => 1988,
            'retirement_age'           => 52,
            'life_expectancy_age'      => 90,
            'discount_rate_pct'        => 2.00,
            'include_in_net_worth'     => true,
            'notes'                    => null,
            'currency'                 => 'USD',
        ];
    }
}
