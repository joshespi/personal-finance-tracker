<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Envelope;
use App\Models\ScheduledTransaction;
use App\Models\User;
use App\Services\ScheduledTransactionService;
use Tests\TestCase;

class ScheduledTransactionTest extends TestCase
{
    // ── Auth ─────────────────────────────────────────────────────────────────

    public function test_index_requires_auth(): void
    {
        $this->get(route('scheduled-transactions.index'))->assertRedirect(route('login'));
    }

    public function test_create_requires_auth(): void
    {
        $this->get(route('scheduled-transactions.create'))->assertRedirect(route('login'));
    }

    // ── Index ─────────────────────────────────────────────────────────────────

    public function test_index_shows_user_scheduled_transactions(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create(['name' => 'Checking']);
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->future()->create([
            'description' => 'Monthly rent',
        ]);

        $this->actingAs($user)
            ->get(route('scheduled-transactions.index'))
            ->assertOk()
            ->assertSee('Monthly rent');
    }

    public function test_index_does_not_show_other_users_scheduled_transactions(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = CashAccount::factory()->for($other)->create();
        ScheduledTransaction::factory()->for($other)->for($account, 'cashAccount')->future()->create([
            'description' => 'Other rent',
        ]);

        $this->actingAs($user)
            ->get(route('scheduled-transactions.index'))
            ->assertOk()
            ->assertDontSee('Other rent');
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_can_create_cash_deposit_scheduled_transaction(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)->post(route('scheduled-transactions.store'), [
            'description'     => 'Paycheck',
            'amount'          => '2500.00',
            'type'            => 'cash_deposit',
            'recurrence'      => 'biweekly',
            'next_due_at'     => today()->addMonth()->toDateString(),
            'cash_account_id' => $account->id,
        ])->assertRedirect(route('scheduled-transactions.index'));

        $this->assertDatabaseHas('scheduled_transactions', [
            'user_id'     => $user->id,
            'description' => 'Paycheck',
            'recurrence'  => 'biweekly',
        ]);
    }

    public function test_can_create_envelope_spend_scheduled_transaction(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();

        $this->actingAs($user)->post(route('scheduled-transactions.store'), [
            'description' => 'Netflix',
            'amount'      => '15.99',
            'type'        => 'envelope_spend',
            'recurrence'  => 'monthly',
            'next_due_at' => today()->addMonth()->toDateString(),
            'envelope_id' => $envelope->id,
        ])->assertRedirect(route('scheduled-transactions.index'));

        $this->assertDatabaseHas('scheduled_transactions', [
            'user_id'     => $user->id,
            'type'        => 'envelope_spend',
            'envelope_id' => $envelope->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('scheduled-transactions.store'), [])
            ->assertSessionHasErrors(['description', 'amount', 'type', 'recurrence', 'next_due_at']);
    }

    public function test_store_rejects_another_users_envelope(): void
    {
        $user          = User::factory()->create();
        $otherEnvelope = Envelope::factory()->create();

        $this->actingAs($user)->post(route('scheduled-transactions.store'), [
            'description' => 'Netflix',
            'amount'      => '15.99',
            'type'        => 'envelope_spend',
            'recurrence'  => 'monthly',
            'next_due_at' => today()->addMonth()->toDateString(),
            'envelope_id' => $otherEnvelope->id,
        ])->assertSessionHasErrors('envelope_id');
    }

    // ── Edit / Update ─────────────────────────────────────────────────────────

    public function test_edit_requires_ownership(): void
    {
        $scheduled = ScheduledTransaction::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('scheduled-transactions.edit', $scheduled))
            ->assertForbidden();
    }

    public function test_owner_can_update_scheduled_transaction(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $scheduled = ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create();

        $this->actingAs($user)->put(route('scheduled-transactions.update', $scheduled), [
            'description'     => 'Updated desc',
            'amount'          => '50.00',
            'type'            => 'cash_deposit',
            'recurrence'      => 'weekly',
            'next_due_at'     => today()->addMonths(2)->toDateString(),
            'cash_account_id' => $account->id,
        ])->assertRedirect(route('scheduled-transactions.index'));

        $this->assertDatabaseHas('scheduled_transactions', [
            'id'          => $scheduled->id,
            'description' => 'Updated desc',
            'recurrence'  => 'weekly',
        ]);
    }

    public function test_non_owner_cannot_update(): void
    {
        $scheduled = ScheduledTransaction::factory()->create();

        $this->actingAs(User::factory()->create())
            ->put(route('scheduled-transactions.update', $scheduled), [
                'description'     => 'Hack',
                'amount'          => '1.00',
                'type'            => 'cash_deposit',
                'recurrence'      => 'monthly',
                'next_due_at'     => today()->addMonth()->toDateString(),
                'cash_account_id' => 999,
            ])->assertForbidden();
    }

    // ── Delete ────────────────────────────────────────────────────────────────

    public function test_owner_can_delete_scheduled_transaction(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $scheduled = ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create();

        $this->actingAs($user)
            ->delete(route('scheduled-transactions.destroy', $scheduled))
            ->assertRedirect(route('scheduled-transactions.index'));

        $this->assertDatabaseMissing('scheduled_transactions', ['id' => $scheduled->id]);
    }

    public function test_non_owner_cannot_delete(): void
    {
        $scheduled = ScheduledTransaction::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete(route('scheduled-transactions.destroy', $scheduled))
            ->assertForbidden();
    }

    // ── Toggle ────────────────────────────────────────────────────────────────

    public function test_owner_can_toggle_active_state(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $scheduled = ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create(['is_active' => true]);

        $this->actingAs($user)
            ->patch(route('scheduled-transactions.toggle', $scheduled))
            ->assertRedirect(route('scheduled-transactions.index'));

        $this->assertDatabaseHas('scheduled_transactions', ['id' => $scheduled->id, 'is_active' => false]);
    }

    public function test_non_owner_cannot_toggle(): void
    {
        $scheduled = ScheduledTransaction::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('scheduled-transactions.toggle', $scheduled))
            ->assertForbidden();
    }

    // ── Service: materialize ──────────────────────────────────────────────────

    public function test_materialize_creates_cash_deposit(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->pastDue()->create([
            'type'   => 'cash_deposit',
            'amount' => 500,
        ]);

        $count = app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'deposit',
            'amount'          => 500,
        ]);
    }

    public function test_materialize_creates_cash_withdrawal(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->pastDue()->create([
            'type' => 'cash_withdrawal',
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'withdrawal',
        ]);
    }

    public function test_materialize_creates_envelope_spend(): void
    {
        $user      = User::factory()->create();
        $envelope  = Envelope::factory()->for($user)->create();
        ScheduledTransaction::factory()->envelopeSpend()->for($user)->for($envelope, 'envelope')->pastDue()->create([
            'description' => 'Streaming',
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseHas('envelope_transactions', [
            'envelope_id' => $envelope->id,
            'type'        => 'spend',
            'description' => 'Streaming',
        ]);
    }

    public function test_materialize_envelope_fund_also_debits_cash_account(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Groceries']);
        $account  = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($envelope, 'envelope')->for($account, 'cashAccount')->pastDue()->create([
            'type'   => 'envelope_fund',
            'amount' => 200,
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseHas('envelope_transactions', ['envelope_id' => $envelope->id, 'type' => 'fund']);
        $this->assertDatabaseHas('cash_transactions', ['cash_account_id' => $account->id, 'type' => 'withdrawal']);
    }

    public function test_materialize_skips_inactive_scheduled_transactions(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->inactive()->pastDue()->create();

        $count = app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertEquals(0, $count);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_materialize_skips_future_scheduled_transactions(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->future()->create();

        $count = app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertEquals(0, $count);
    }

    public static function recurrenceAdvanceProvider(): array
    {
        return [
            'monthly'  => ['monthly',  fn () => today()->addMonth()->toDateString()],
            'weekly'   => ['weekly',   fn () => today()->addWeek()->toDateString()],
            'biweekly' => ['biweekly', fn () => today()->addWeeks(2)->toDateString()],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('recurrenceAdvanceProvider')]
    public function test_materialize_advances_next_due_at(string $recurrence, \Closure $expectedDate): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $scheduled = ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'next_due_at' => today(),
            'recurrence'  => $recurrence,
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertSame($expectedDate(), $scheduled->fresh()->next_due_at->toDateString());
    }

    public function test_materialize_catches_up_multiple_missed_occurrences(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        // 20 days ago with weekly recurrence: -20, -13, -6 → 3 occurrences; next = +1 day (future)
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'next_due_at' => today()->subDays(20),
            'recurrence'  => 'weekly',
        ]);

        $count = app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertEquals(3, $count);
        $this->assertDatabaseCount('cash_transactions', 3);
    }
}
