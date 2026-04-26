<?php

namespace Database\Factories;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAccount>
 */
class CashAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'      => User::factory(),
            'name'         => fake()->words(2, true),
            'account_type' => 'checking',
            'currency'     => 'USD',
            'notes'        => null,
        ];
    }
}
