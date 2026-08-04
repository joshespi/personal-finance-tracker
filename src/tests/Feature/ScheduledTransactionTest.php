<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\Envelope;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\ScheduledTransaction;
use App\Models\User;
use App\Services\ScheduledTransactionService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduledTransactionTest extends TestCase
{
    // ── Upcoming & Scheduled widget (on the all-transactions page) ─────────────

    public function test_all_transactions_shows_user_scheduled_transactions(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create(['name' => 'Checking']);
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->future()->create([
            'description' => 'Monthly rent',
        ]);

        $this->actingAs($user)
            ->get(route('cash-accounts.all'))
            ->assertOk()
            ->assertSee('Monthly rent');
    }

    public function test_all_transactions_does_not_show_other_users_scheduled_transactions(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = CashAccount::factory()->for($other)->create();
        ScheduledTransaction::factory()->for($other)->for($account, 'cashAccount')->future()->create([
            'description' => 'Other rent',
        ]);

        $this->actingAs($user)
            ->get(route('cash-accounts.all'))
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
        ])->assertRedirect(route('cash-accounts.all'));

        $this->assertDatabaseHas('scheduled_transactions', [
            'user_id'     => $user->id,
            'description' => 'Paycheck',
            'recurrence'  => 'biweekly',
        ]);
    }

    public function test_can_create_envelope_spend_scheduled_transaction(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        $this->actingAs($user)->post(route('scheduled-transactions.store'), [
            'description'     => 'Netflix',
            'amount'          => '15.99',
            'type'            => 'envelope_spend',
            'recurrence'      => 'monthly',
            'next_due_at'     => today()->addMonth()->toDateString(),
            'envelope_id'     => $envelope->id,
            'cash_account_id' => $account->id,
        ])->assertRedirect(route('cash-accounts.all'));

        $this->assertDatabaseHas('scheduled_transactions', [
            'user_id'         => $user->id,
            'type'            => 'envelope_spend',
            'envelope_id'     => $envelope->id,
            'cash_account_id' => $account->id,
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

    public function test_store_rejects_system_managed_mortgage_type(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        // mortgage_payment schedules are created only by LiabilityController::syncSchedule;
        // a user-created one could carry no cash account and fatal materialization.
        $this->actingAs($user)->post(route('scheduled-transactions.store'), [
            'description'     => 'Sneaky mortgage',
            'amount'          => '100.00',
            'type'            => 'mortgage_payment',
            'recurrence'      => 'monthly',
            'next_due_at'     => today()->addMonth()->toDateString(),
            'cash_account_id' => $account->id,
        ])->assertSessionHasErrors('type');

        $this->assertDatabaseCount('scheduled_transactions', 0);
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
        ])->assertRedirect(route('cash-accounts.all'));

        $this->assertDatabaseHas('scheduled_transactions', [
            'id'          => $scheduled->id,
            'description' => 'Updated desc',
            'recurrence'  => 'weekly',
        ]);
    }

    public function test_updating_existing_mortgage_schedule_keeps_its_type(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $scheduled = ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'type' => 'mortgage_payment',
        ]);

        // The edit form offers mortgage_payment when the schedule already is one;
        // re-saving must not be rejected by the user-selectable whitelist.
        $this->actingAs($user)->put(route('scheduled-transactions.update', $scheduled), [
            'description'     => 'House payment',
            'amount'          => '1500.00',
            'type'            => 'mortgage_payment',
            'recurrence'      => 'monthly',
            'next_due_at'     => today()->addMonth()->toDateString(),
            'cash_account_id' => $account->id,
        ])->assertRedirect(route('cash-accounts.all'));

        $this->assertDatabaseHas('scheduled_transactions', [
            'id'          => $scheduled->id,
            'description' => 'House payment',
            'type'        => 'mortgage_payment',
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
            ->assertRedirect(route('cash-accounts.all'));

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
            ->assertRedirect(route('cash-accounts.all'));

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
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->pastDue()->create([
            'type'   => 'cash_deposit',
            'amount' => 500,
        ]);

        $fired = app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertCount(1, $fired);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'deposit',
            'amount'          => 500,
        ]);
    }

    public function test_materialize_enters_cash_transactions_as_pending(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->pastDue()->create([
            'type'   => 'cash_deposit',
            'amount' => 500,
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'amount'          => 500,
            'cleared'         => false,
        ]);
    }

    public function test_enter_now_records_cash_transaction_as_cleared(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $scheduled = ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->pastDue()->create([
            'type'   => 'cash_deposit',
            'amount' => 500,
        ]);

        app(ScheduledTransactionService::class)->enterNow($scheduled);

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'amount'          => 500,
            'cleared'         => true,
        ]);
    }

    public function test_materialize_creates_cash_withdrawal(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
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
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();
        ScheduledTransaction::factory()->envelopeSpend()
            ->for($user)->for($envelope, 'envelope')->for($account, 'cashAccount')->pastDue()
            ->create(['description' => 'Streaming']);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'envelope_id'     => $envelope->id,
            'type'            => 'withdrawal',
            'description'     => 'Streaming',
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

    public function test_materialize_mortgage_payment_creates_withdrawal_and_paydown(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $liability = Liability::factory()->for($user)->create([
            'liability_type'  => 'mortgage',
            'interest_rate'   => 6,
            'minimum_payment' => 1500,
        ]);
        LiabilityBalance::factory()->for($liability)->create([
            'balance'     => 200000,
            'recorded_at' => today()->subMonth(),
        ]);
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->for($liability, 'liability')->pastDue()->create([
            'type'   => 'mortgage_payment',
            'amount' => 1500,
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'withdrawal',
            'amount'          => 1500,
            'cleared'         => false,
        ]);

        // interest = 200000 * 6% / 12 = 1000; principal = 1500 - 1000 = 500; new balance = 199500
        $this->assertDatabaseHas('liability_balances', [
            'liability_id' => $liability->id,
            'balance'      => 199500,
        ]);
        $this->assertDatabaseCount('liability_balances', 2);
    }

    public function test_materialize_liability_payment_grows_balance_when_payment_below_interest(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $liability = Liability::factory()->for($user)->create([
            'liability_type'  => 'credit_card',
            'interest_rate'   => 24,
            'minimum_payment' => 75,
        ]);
        LiabilityBalance::factory()->for($liability)->create([
            'balance'     => 10000,
            'recorded_at' => today()->subMonth(),
        ]);
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->for($liability, 'liability')->pastDue()->create([
            'type'   => 'mortgage_payment',
            'amount' => 75,
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        // interest = 10000 * 24% / 12 = 200; payment (75) doesn't cover it, so the
        // balance must grow by the shortfall (125), not stay frozen at 10000.
        $this->assertDatabaseHas('liability_balances', [
            'liability_id' => $liability->id,
            'balance'      => 10125,
        ]);
    }

    public function test_materialize_liability_payment_compounds_balance_across_multiple_overdue_cycles(): void
    {
        $user      = User::factory()->create();
        $account   = CashAccount::factory()->for($user)->create();
        $liability = Liability::factory()->for($user)->create([
            'liability_type'  => 'credit_card',
            'interest_rate'   => 24,
            'minimum_payment' => 300,
        ]);
        LiabilityBalance::factory()->for($liability)->create([
            'balance'     => 10000,
            'recorded_at' => today()->subMonths(4),
        ]);
        // Three overdue monthly cycles in one materialization run — regression guard for a
        // bug where each cycle re-read the same cached pre-loop balance instead of compounding.
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->for($liability, 'liability')->create([
            'type'   => 'mortgage_payment',
            'amount' => 300,
            // +1 day so 3 monthly advances land just past today (exactly 3 cycles fire,
            // not 4 — subMonths(3) alone lands exactly on today, which still satisfies
            // the loop's <= check and fires an extra cycle).
            'next_due_at' => today()->subMonths(3)->addDay(),
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        // Cycle 1: interest = 10000*24%/12 = 200.00; principal = 100.00; balance = 9900.00
        // Cycle 2: interest = 9900*24%/12  = 198.00; principal = 102.00; balance = 9798.00
        // Cycle 3: interest = 9798*24%/12  = 195.96; principal = 104.04; balance = 9693.96
        $this->assertDatabaseCount('liability_balances', 4);
        $this->assertDatabaseHas('liability_balances', ['liability_id' => $liability->id, 'balance' => 9900.00]);
        $this->assertDatabaseHas('liability_balances', ['liability_id' => $liability->id, 'balance' => 9798.00]);
        $this->assertDatabaseHas('liability_balances', ['liability_id' => $liability->id, 'balance' => 9693.96]);
        $this->assertDatabaseCount('cash_transactions', 3);
    }

    public function test_materialize_mortgage_payment_skips_paydown_when_liability_missing(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->pastDue()->create([
            'type'         => 'mortgage_payment',
            'amount'       => 1500,
            'liability_id' => null,
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'withdrawal',
            'amount'          => 1500,
        ]);
        $this->assertDatabaseCount('liability_balances', 0);
    }

    public function test_materialize_skips_inactive_scheduled_transactions(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->inactive()->pastDue()->create();

        $fired = app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertCount(0, $fired);
        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_materialize_skips_future_scheduled_transactions(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->future()->create();

        $fired = app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertCount(0, $fired);
    }

    public static function recurrenceAdvanceProvider(): array
    {
        return [
            'monthly'  => ['monthly',  fn () => today()->addMonth()->toDateString()],
            'weekly'   => ['weekly',   fn () => today()->addWeek()->toDateString()],
            'biweekly' => ['biweekly', fn () => today()->addWeeks(2)->toDateString()],
        ];
    }

    #[DataProvider('recurrenceAdvanceProvider')]
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
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        // 20 days ago with weekly recurrence: -20, -13, -6 → 3 occurrences; next = +1 day (future)
        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'next_due_at' => today()->subDays(20),
            'recurrence'  => 'weekly',
        ]);

        app(ScheduledTransactionService::class)->materializeForUser($user);

        $this->assertDatabaseCount('cash_transactions', 3);
    }
}
