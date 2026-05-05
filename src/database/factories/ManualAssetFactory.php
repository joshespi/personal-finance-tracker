<?php

namespace Database\Factories;

use App\Models\Asset;
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
            'portfolio_id'    => Portfolio::factory(),
            'name'            => fake()->words(2, true),
            'description'     => null,
            'asset_class'     => 'real_estate',
            'currency'        => 'USD',
            'tracking_method' => 'static',
        ];
    }

    public function proxyTracked(Asset $proxyAsset, float $anchorValue, string $anchorDate, ?float $syntheticShares = null): static
    {
        return $this->state([
            'tracking_method'         => 'proxy_ticker',
            'proxy_asset_id'          => $proxyAsset->id,
            'anchor_value'            => $anchorValue,
            'anchor_date'             => $anchorDate,
            'anchor_synthetic_shares' => $syntheticShares,
        ]);
    }
}
