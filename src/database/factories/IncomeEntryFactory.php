<?php

namespace Database\Factories;

use App\Models\IncomeEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IncomeEntry>
 */
class IncomeEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'amount'      => 1000,
            'description' => 'Paycheck',
            'occurred_at' => now()->toDateString(),
        ];
    }
}
