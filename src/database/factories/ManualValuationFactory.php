<?php

namespace Database\Factories;

use App\Models\ManualAsset;
use App\Models\ManualValuation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualValuation>
 */
class ManualValuationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'manual_asset_id' => ManualAsset::factory(),
            'value'           => 100000,
            'notes'           => null,
            'valued_at'       => now(),
        ];
    }
}
