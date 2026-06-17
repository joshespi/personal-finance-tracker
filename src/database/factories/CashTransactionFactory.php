<?php

namespace Database\Factories;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTransaction>
 */
class CashTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cash_account_id' => CashAccount::factory(),
            'type'            => 'deposit',
            'amount'          => 100,
            'description'     => null,
            'occurred_at'     => now()->toDateString(),
        ];
    }

    public function deposit(): static    { return $this->state(['type' => 'deposit']); }
    public function withdrawal(): static { return $this->state(['type' => 'withdrawal']); }
    public function cleared(): static    { return $this->state(['cleared' => true]); }
    public function pending(): static    { return $this->state(['cleared' => false]); }

    public function spend(\App\Models\Envelope $envelope): static
    {
        return $this->state(['type' => 'withdrawal', 'envelope_id' => $envelope->id]);
    }
}
