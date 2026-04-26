<?php

namespace Database\Factories;

use App\Models\Envelope;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Envelope>
 */
class EnvelopeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'        => User::factory(),
            'name'           => fake()->words(2, true),
            'monthly_target' => 500,
            'color'          => '#6366f1',
            'sort_order'     => 0,
            'notes'          => null,
        ];
    }
}
