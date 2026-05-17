<?php

namespace Tests\Feature;

use App\Mail\ScheduledTransactionsSummary;
use App\Models\CashAccount;
use App\Models\Envelope;
use App\Models\ScheduledTransaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MaterializeScheduledTransactionsTest extends TestCase
{
    public function test_command_materializes_due_transactions_and_sends_email(): void
    {
        Mail::fake();

        $user    = User::factory()->create(['notify_scheduled_transactions' => true]);
        $account = CashAccount::factory()->for($user)->create();

        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'type'        => 'cash_withdrawal',
            'description' => 'Netflix',
            'amount'      => 15.99,
            'recurrence'  => 'monthly',
            'next_due_at' => today(),
            'is_active'   => true,
        ]);

        $this->artisan('transactions:materialize')->assertSuccessful();

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'withdrawal',
            'amount'          => 15.99,
        ]);

        Mail::assertSent(ScheduledTransactionsSummary::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email) && $mail->fired->count() === 1;
        });
    }

    public function test_command_skips_future_transactions(): void
    {
        Mail::fake();

        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'type'        => 'cash_withdrawal',
            'amount'      => 50.00,
            'next_due_at' => today()->addDays(5),
            'is_active'   => true,
        ]);

        $this->artisan('transactions:materialize')->assertSuccessful();

        $this->assertDatabaseCount('cash_transactions', 0);
        Mail::assertNothingSent();
    }

    public function test_command_skips_inactive_transactions(): void
    {
        Mail::fake();

        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();

        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'type'        => 'cash_withdrawal',
            'amount'      => 50.00,
            'next_due_at' => today(),
            'is_active'   => false,
        ]);

        $this->artisan('transactions:materialize')->assertSuccessful();

        $this->assertDatabaseCount('cash_transactions', 0);
        Mail::assertNothingSent();
    }

    public function test_command_sends_separate_email_per_user(): void
    {
        Mail::fake();

        $userA   = User::factory()->create(['notify_scheduled_transactions' => true]);
        $userB   = User::factory()->create(['notify_scheduled_transactions' => true]);
        $accountA = CashAccount::factory()->for($userA)->create();
        $accountB = CashAccount::factory()->for($userB)->create();

        ScheduledTransaction::factory()->for($userA)->for($accountA, 'cashAccount')->create([
            'type' => 'cash_deposit', 'amount' => 100, 'next_due_at' => today(), 'is_active' => true,
        ]);
        ScheduledTransaction::factory()->for($userB)->for($accountB, 'cashAccount')->create([
            'type' => 'cash_deposit', 'amount' => 200, 'next_due_at' => today(), 'is_active' => true,
        ]);

        $this->artisan('transactions:materialize')->assertSuccessful();

        Mail::assertSentCount(2);
        Mail::assertSent(ScheduledTransactionsSummary::class, fn ($m) => $m->hasTo($userA->email));
        Mail::assertSent(ScheduledTransactionsSummary::class, fn ($m) => $m->hasTo($userB->email));
    }

    public function test_command_respects_muted_email_preference(): void
    {
        Mail::fake();

        $user    = User::factory()->create(['notify_scheduled_transactions' => false]);
        $account = CashAccount::factory()->for($user)->create();

        ScheduledTransaction::factory()->for($user)->for($account, 'cashAccount')->create([
            'type'        => 'cash_withdrawal',
            'amount'      => 25.00,
            'next_due_at' => today(),
            'is_active'   => true,
        ]);

        $this->artisan('transactions:materialize')->assertSuccessful();

        // Transaction is still materialized
        $this->assertDatabaseCount('cash_transactions', 1);
        // But no email sent
        Mail::assertNothingSent();
    }

    public function test_command_handles_envelope_fund_transaction(): void
    {
        Mail::fake();

        $user     = User::factory()->create(['notify_scheduled_transactions' => true]);
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Savings']);

        ScheduledTransaction::factory()->for($user)->for($envelope)->create([
            'type'        => 'envelope_fund',
            'description' => 'Monthly savings',
            'amount'      => 500.00,
            'recurrence'  => 'monthly',
            'next_due_at' => today(),
            'is_active'   => true,
        ]);

        $this->artisan('transactions:materialize')->assertSuccessful();

        $this->assertDatabaseHas('envelope_transactions', [
            'envelope_id' => $envelope->id,
            'type'        => 'fund',
            'amount'      => 500.00,
        ]);

        Mail::assertSent(ScheduledTransactionsSummary::class, fn ($m) => $m->hasTo($user->email));
    }
}
