<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\User;
use Tests\TestCase;

class CashflowTest extends TestCase
{
    public function test_requires_auth(): void
    {
        $this->get(route('cashflow'))->assertRedirect(route('login'));
    }

    public function test_income_comes_from_cash_deposits(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create(['name' => 'Checking']);

        CashTransaction::factory()->for($account)->deposit()->create([
            'amount'      => 3000,
            'description' => 'Paycheck',
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow'))
            ->assertOk()
            ->assertSee('3,000.00')
            ->assertSee('Paycheck');
    }

    public function test_shows_envelope_spending_breakdown(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Groceries']);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 250,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow'))
            ->assertOk()
            ->assertSee('Groceries')
            ->assertSee('250.00');
    }

    public function test_net_is_deposits_minus_spent(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create([
            'amount' => 2000, 'occurred_at' => now()->toDateString(),
        ]);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount' => 600, 'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow'))
            ->assertOk()
            ->assertSee('1,400.00');
    }

    public function test_does_not_show_other_users_deposits(): void
    {
        $user        = User::factory()->create();
        $other       = User::factory()->create();
        $otherAccount = CashAccount::factory()->for($other)->create();

        CashTransaction::factory()->for($otherAccount)->deposit()->create([
            'amount'      => 9999,
            'description' => 'OtherPaycheck',
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow'))
            ->assertOk()
            ->assertDontSee('OtherPaycheck')
            ->assertDontSee('9,999.00');
    }

    public function test_deposit_from_other_month_excluded(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create([
            'amount'      => 5000,
            'description' => 'OldPaycheck',
            'occurred_at' => now()->subMonths(2)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow'))
            ->assertOk()
            ->assertDontSee('OldPaycheck');
    }

    public function test_month_param_filters_data(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Rent']);

        $targetMonth = now()->subMonth();

        CashTransaction::factory()->for($account)->deposit()->create([
            'amount' => 4000, 'occurred_at' => $targetMonth->toDateString(),
        ]);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount' => 1200, 'occurred_at' => $targetMonth->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow', ['month' => $targetMonth->format('Y-m')]))
            ->assertOk()
            ->assertSee('4,000.00')
            ->assertSee('1,200.00');
    }

    public function test_no_deposits_shows_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('cashflow'))
            ->assertOk()
            ->assertSee('No deposits recorded for this month');
    }
}
