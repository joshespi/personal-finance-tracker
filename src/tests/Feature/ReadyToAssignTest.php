<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\IncomeEntry;
use App\Models\User;
use Tests\TestCase;

class ReadyToAssignTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('ready-to-assign'))->assertRedirect(route('login'));
    }

    public function test_ready_to_assign_is_cash_minus_envelope_balance(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 2000]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 500]);

        $this->actingAs($user)
            ->get(route('ready-to-assign'))
            ->assertOk()
            ->assertSee('1,500.00');
    }

    public function test_zero_cash_shows_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ready-to-assign'))
            ->assertOk()
            ->assertSee('0.00');
    }

    public function test_assign_creates_fund_transactions(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 1000]);

        $this->actingAs($user)->post(route('ready-to-assign.assign'), [
            'amounts' => [$envelope->id => '400'],
        ])->assertRedirect(route('ready-to-assign'));

        $this->assertDatabaseHas('envelope_transactions', [
            'envelope_id' => $envelope->id,
            'type'        => 'fund',
        ]);

        $this->assertEquals(600.0, $user->readyToAssign());
    }

    public function test_assign_ignores_zero_amounts(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        $this->actingAs($user)->post(route('ready-to-assign.assign'), [
            'amounts' => [$envelope->id => '0'],
        ])->assertRedirect();

        $this->assertDatabaseCount('envelope_transactions', 0);
    }

    public function test_assign_rejects_other_users_envelopes(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $env   = Envelope::factory()->for($other)->create();

        $this->actingAs($user)->post(route('ready-to-assign.assign'), [
            'amounts' => [$env->id => '500'],
        ]);

        $this->assertDatabaseCount('envelope_transactions', 0);
    }

    public function test_no_cross_user_cash_leakage(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = CashAccount::factory()->for($other)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 5000]);

        $this->assertEquals(0.0, $user->readyToAssign());
    }

    public function test_envelope_spend_increases_rta_when_no_paired_withdrawal(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 1000]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 800]);
        // Paired: cash withdrawal + envelope spend → RTA unchanged
        CashTransaction::factory()->for($account)->withdrawal()->create(['amount' => 200]);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create(['amount' => 200]);

        // Cash: 1000-200=800, Envelope: 800-200=600, RTA: 800-600=200
        $this->assertEquals(200.0, $user->readyToAssign());
    }

    public function test_income_entry_store_requires_auth(): void
    {
        $this->post(route('income-entries.store'), ['amount' => 100, 'occurred_at' => now()->toDateString()])
            ->assertRedirect(route('login'));
    }

    public function test_income_entry_store_creates_record(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('income-entries.store'), [
            'amount'      => 3500,
            'description' => 'Freelance',
            'occurred_at' => now()->toDateString(),
        ])->assertRedirect(route('ready-to-assign'));

        $this->assertDatabaseHas('income_entries', [
            'user_id' => $user->id,
            'amount'  => 3500,
        ]);
    }

    public function test_income_entry_store_validates_positive_amount(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('income-entries.store'), [
            'amount'      => -100,
            'occurred_at' => now()->toDateString(),
        ])->assertSessionHasErrors('amount');
    }

    public function test_income_entry_destroy_requires_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $entry = IncomeEntry::factory()->for($owner)->create();

        $this->actingAs($other)
            ->delete(route('income-entries.destroy', $entry))
            ->assertForbidden();
    }

    public function test_income_entry_owner_can_delete(): void
    {
        $user  = User::factory()->create();
        $entry = IncomeEntry::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('income-entries.destroy', $entry))
            ->assertRedirect(route('ready-to-assign'));

        $this->assertDatabaseMissing('income_entries', ['id' => $entry->id]);
    }
}
