<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'title'        => fake()->sentence(4),
            'body'         => fake()->paragraph(),
            'entry_date'   => now()->toDateString(),
        ];
    }
}
