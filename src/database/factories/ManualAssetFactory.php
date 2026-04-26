<?php

namespace Database\Factories;

use App\Models\ManualAsset;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualAsset>
 */
class ManualAssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'name'         => fake()->words(2, true),
            'description'  => null,
            'asset_class'  => 'real_estate',
            'currency'     => 'USD',
        ];
    }
}
