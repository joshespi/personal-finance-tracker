<?php

namespace Database\Factories;

use App\Models\IncomeCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeCategory>
 */
class IncomeCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'name'       => fake()->unique()->words(2, true),
            'color'      => '#6366f1',
            'sort_order' => 0,
        ];
    }
}
