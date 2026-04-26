<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetPrice>
 */
class AssetPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'asset_id'    => Asset::factory(),
            'price'       => fake()->randomFloat(2, 1, 1000),
            'currency'    => 'USD',
            'recorded_at' => now(),
        ];
    }
}
