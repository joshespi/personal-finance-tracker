<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        $symbol = strtoupper(fake()->unique()->lexify('????'));

        return [
            'symbol'     => $symbol,
            'name'       => $symbol,
            'asset_type' => 'stock',
        ];
    }

    public function stock(): static
    {
        return $this->state(['asset_type' => 'stock']);
    }

    public function crypto(): static
    {
        return $this->state(['asset_type' => 'crypto']);
    }
}
