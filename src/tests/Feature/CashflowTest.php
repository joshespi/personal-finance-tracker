<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\IncomeEntry;
use App\Models\User;
use Tests\TestCase;

class CashflowTest extends TestCase
{
    public function test_requires_auth(): void
    {
        $this->get(route('cashflow'))->assertRedirect(route('login'));
    }

    public function test_income_comes_from_income_entries_not_cash_deposits(): void
    {
        $user = User::factory()->create();

        IncomeEntry::factory()->for($user)->create([
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
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Groceries']);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create([
            'amount'      => 250,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow'))
            ->assertOk()
            ->assertSee('Groceries')
            ->assertSee('250.00');
    }

    public function test_net_is_income_minus_spent(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        IncomeEntry::factory()->for($user)->create([
            'amount'      => 2000,
            'occurred_at' => now()->toDateString(),
        ]);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create([
            'amount'      => 600,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow'))
            ->assertOk()
            ->assertSee('1,400.00');
    }

    public function test_does_not_show_other_users_income(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        IncomeEntry::factory()->for($other)->create([
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

    public function test_income_from_other_month_excluded(): void
    {
        $user = User::factory()->create();

        IncomeEntry::factory()->for($user)->create([
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
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Rent']);

        $targetMonth = now()->subMonth();

        IncomeEntry::factory()->for($user)->create([
            'amount'      => 4000,
            'occurred_at' => $targetMonth->toDateString(),
        ]);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create([
            'amount'      => 1200,
            'occurred_at' => $targetMonth->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('cashflow', ['month' => $targetMonth->format('Y-m')]))
            ->assertOk()
            ->assertSee('4,000.00')
            ->assertSee('1,200.00');
    }

    public function test_no_income_shows_empty_state(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('cashflow'))
            ->assertOk()
            ->assertSee('No income recorded for this month');
    }
}
