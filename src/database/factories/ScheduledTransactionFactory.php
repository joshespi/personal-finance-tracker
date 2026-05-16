<?php

namespace Database\Factories;

use App\Models\CashAccount;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScheduledTransaction>
 */
class ScheduledTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'description'     => fake()->sentence(3),
            'amount'          => 100,
            'type'            => 'cash_deposit',
            'recurrence'      => 'monthly',
            'next_due_at'     => today(),
            'cash_account_id' => CashAccount::factory(),
            'envelope_id'     => null,
            'is_active'       => true,
        ];
    }

    public function envelopeSpend(): static
    {
        return $this->state(fn () => [
            'type' => 'envelope_spend',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function pastDue(int $daysAgo = 3): static
    {
        return $this->state(['next_due_at' => today()->subDays($daysAgo)]);
    }

    public function future(): static
    {
        return $this->state(['next_due_at' => today()->addMonth()]);
    }
}
