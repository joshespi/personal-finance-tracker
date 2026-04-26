<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WatchlistItem>
 */
class WatchlistItemFactory extends Factory
{
    public function definition(): array
    {
        $symbol = strtoupper(fake()->unique()->lexify('????'));

        return [
            'user_id'      => User::factory(),
            'symbol'       => $symbol,
            'name'         => $symbol,
            'asset_type'   => 'stock',
            'target_price' => null,
            'notes'        => null,
        ];
    }
}
