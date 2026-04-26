<?php

namespace Database\Factories;

use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvelopeTransaction>
 */
class EnvelopeTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'envelope_id' => Envelope::factory(),
            'type'        => 'fund',
            'amount'      => 50,
            'description' => null,
            'occurred_at' => now()->toDateString(),
        ];
    }

    public function fund(): static  { return $this->state(['type' => 'fund']); }
    public function spend(): static { return $this->state(['type' => 'spend']); }
}
