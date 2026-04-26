<?php

namespace Database\Factories;

use App\Models\Liability;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Liability>
 */
class LiabilityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'manual_asset_id' => null,
            'name'            => fake()->words(2, true),
            'liability_type'  => 'credit_card',
            'interest_rate'   => null,
            'notes'           => null,
            'currency'        => 'USD',
        ];
    }
}
