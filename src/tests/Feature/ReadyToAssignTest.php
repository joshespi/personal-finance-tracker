<?php

namespace Tests\Feature;

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

    public function test_index_loads_for_authenticated_user(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('ready-to-assign'))
            ->assertOk();
    }

    public function test_ready_to_assign_is_income_minus_funded(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        IncomeEntry::factory()->for($user)->create(['amount' => 2000]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 500]);

        $this->actingAs($user)
            ->get(route('ready-to-assign'))
            ->assertOk()
            ->assertSee('1,500.00');
    }

    public function test_zero_income_shows_zero(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('ready-to-assign'))
            ->assertOk()
            ->assertSee('0.00');
    }

    public function test_income_entry_store_records_entry_and_updates_rta(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('income-entries.store'), [
            'amount'      => '1500',
            'description' => 'Paycheck',
            'occurred_at' => now()->toDateString(),
        ])->assertRedirect(route('ready-to-assign'));

        $this->assertDatabaseHas('income_entries', [
            'user_id'     => $user->id,
            'description' => 'Paycheck',
        ]);

        $this->assertEquals(1500.0, $user->readyToAssign());
    }

    public function test_income_entry_store_validates_amount_gt_zero(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('income-entries.store'), [
                'amount'      => '0',
                'occurred_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_income_entry_destroy_removes_entry(): void
    {
        $user  = User::factory()->create();
        $entry = IncomeEntry::factory()->for($user)->create(['amount' => 1000]);

        $this->actingAs($user)
            ->delete(route('income-entries.destroy', $entry))
            ->assertRedirect(route('ready-to-assign'));

        $this->assertDatabaseMissing('income_entries', ['id' => $entry->id]);
    }

    public function test_income_entry_destroy_rejects_other_users_entry(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $entry = IncomeEntry::factory()->for($other)->create();

        $this->actingAs($user)
            ->delete(route('income-entries.destroy', $entry))
            ->assertForbidden();
    }

    public function test_assign_creates_fund_transactions(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        IncomeEntry::factory()->for($user)->create(['amount' => 1000]);

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

    public function test_no_cross_user_income_leakage(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        IncomeEntry::factory()->for($other)->create(['amount' => 5000]);

        $this->assertEquals(0.0, $user->readyToAssign());
    }

    public function test_spend_transactions_do_not_reduce_rta(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        IncomeEntry::factory()->for($user)->create(['amount' => 1000]);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create(['amount' => 200]);

        // Spending doesn't reduce RTA — only fund transactions do
        $this->assertEquals(1000.0, $user->readyToAssign());
    }
}
