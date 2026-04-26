<?php

namespace Database\Factories;

use App\Models\Liability;
use App\Models\LiabilityBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LiabilityBalance>
 */
class LiabilityBalanceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'liability_id' => Liability::factory(),
            'balance'      => 1000,
            'notes'        => null,
            'recorded_at'  => now(),
        ];
    }
}
