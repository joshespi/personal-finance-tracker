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
        $this->get(route('envelopes.index'))->assertRedirect(route('login'));
    }

    public function test_ready_to_assign_is_cash_minus_envelope_balance(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 2000]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 500]);

        $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk()
            ->assertSee('1,500.00');
    }

    public function test_ready_to_assign_is_negative_when_envelopes_funded_beyond_cash(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 300]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 1000]);

        $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk()
            ->assertSee('700.00');
    }

    public function test_zero_cash_shows_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk();
    }

    public function test_assign_one_creates_fund_transaction(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 1000]);

        $this->actingAs($user)->postJson(route('envelopes.assign-one'), [
            'envelope_id' => $envelope->id,
            'amount'      => 400,
        ])->assertOk()->assertJsonStructure(['ready_to_assign', 'envelope_balance']);

        $this->assertDatabaseHas('envelope_transactions', [
            'envelope_id' => $envelope->id,
            'type'        => 'fund',
        ]);

        $this->assertEquals(600.0, $user->readyToAssign());
    }

    public function test_assign_one_without_month_dates_today(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('envelopes.assign-one'), [
            'envelope_id' => $envelope->id,
            'amount'      => 100,
        ])->assertOk();

        $tx = EnvelopeTransaction::where('envelope_id', $envelope->id)->latest('id')->first();
        $this->assertEquals(now()->toDateString(), $tx->occurred_at->toDateString());
    }

    public function test_assign_one_dates_fund_in_past_month(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        $pastMonth = now()->subMonths(2)->format('Y-m');

        $this->actingAs($user)->postJson(route('envelopes.assign-one'), [
            'envelope_id' => $envelope->id,
            'amount'      => 100,
            'month'       => $pastMonth,
        ])->assertOk();

        $tx = EnvelopeTransaction::where('envelope_id', $envelope->id)->latest('id')->first();
        $this->assertEquals($pastMonth, $tx->occurred_at->format('Y-m'));
        $this->assertEquals($pastMonth.'-01', $tx->occurred_at->format('Y-m-d'));
    }

    public function test_assign_one_current_month_param_still_dates_today(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('envelopes.assign-one'), [
            'envelope_id' => $envelope->id,
            'amount'      => 100,
            'month'       => now()->format('Y-m'),
        ])->assertOk();

        $tx = EnvelopeTransaction::where('envelope_id', $envelope->id)->latest('id')->first();
        $this->assertEquals(now()->toDateString(), $tx->occurred_at->toDateString());
    }

    public function test_assign_one_rejects_bad_month_format(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        $this->actingAs($user)->postJson(route('envelopes.assign-one'), [
            'envelope_id' => $envelope->id,
            'amount'      => 100,
            'month'       => '2026-13',
        ])->assertStatus(422);
    }

    public function test_assign_one_rejects_other_users_envelopes(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $env   = Envelope::factory()->for($other)->create();

        $this->actingAs($user)->postJson(route('envelopes.assign-one'), [
            'envelope_id' => $env->id,
            'amount'      => 500,
        ])->assertForbidden();

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

    public function test_tagged_withdrawal_debits_envelope_and_keeps_rta_balanced(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 1000]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 800]);
        // Tagged withdrawal: cash goes down, envelope balance goes down → RTA unchanged
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 200]);

        // Cash: 1000-200=800, Envelope: 800-200=600, RTA: 800-600=200
        $this->assertEquals(200.0, $user->readyToAssign());
    }

    public function test_untagged_withdrawal_reduces_rta(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        CashTransaction::factory()->for($account)->deposit()->create(['amount' => 1000]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 800]);
        // Untagged withdrawal: cash goes down, envelope balance unchanged → RTA drops
        CashTransaction::factory()->for($account)->withdrawal()->create(['amount' => 200]);

        // Cash: 1000-200=800, Envelope: 800, RTA: 800-800=0
        $this->assertEquals(0.0, $user->readyToAssign());
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
