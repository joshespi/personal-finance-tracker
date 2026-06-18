<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id'   => Portfolio::factory(),
            'asset_id'       => Asset::factory(),
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'currency'       => 'USD',
            'transacted_at'  => '2024-01-01',
        ];
    }

    public function buy(): static
    {
        return $this->state(['type' => 'buy']);
    }

    public function sell(): static
    {
        return $this->state(['type' => 'sell']);
    }
}
