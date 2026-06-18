<?php

namespace Tests\Feature;

use App\Livewire\TransactionList;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\Portfolio;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class CashAccountTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('cash-accounts.index'))->assertRedirect(route('login'));
    }

    public function test_index_returns_ok_when_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cash-accounts.index'))
            ->assertOk()
            ->assertSee('No spending accounts yet.');
    }

    public function test_create_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('cash-accounts.store'), [
                'name'         => 'Chase Checking',
                'account_type' => 'checking',
                'currency'     => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_accounts', [
            'user_id' => $user->id,
            'name'    => 'Chase Checking',
        ]);
    }

    public function test_validation_rejects_invalid_account_type(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('cash-accounts.store'), [
                'name'         => 'Foo',
                'account_type' => 'bogus_type',
                'currency'     => 'USD',
            ])
            ->assertSessionHasErrors('account_type');
    }

    public function test_show_forbidden_for_other_user(): void
    {
        $account = CashAccount::factory()->create();
        $other   = User::factory()->create();

        $this->actingAs($other)
            ->get(route('cash-accounts.show', $account))
            ->assertForbidden();
    }

    public function test_update_account(): void
    {
        $account = CashAccount::factory()->create();

        $this->actingAs($account->user)
            ->put(route('cash-accounts.update', $account), [
                'name'         => 'Renamed',
                'account_type' => 'savings',
                'currency'     => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_accounts', ['id' => $account->id, 'name' => 'Renamed', 'account_type' => 'savings']);
    }

    public function test_delete_account(): void
    {
        $account = CashAccount::factory()->create();

        $this->actingAs($account->user)
            ->delete(route('cash-accounts.destroy', $account))
            ->assertRedirect(route('cash-accounts.index'));

        $this->assertDatabaseMissing('cash_accounts', ['id' => $account->id]);
    }

    public function test_record_deposit(): void
    {
        $account = CashAccount::factory()->create();

        $this->actingAs($account->user)
            ->post(route('cash-accounts.transactions.store', $account), [
                'type'        => 'deposit',
                'amount'      => 1000,
                'occurred_at' => '2026-04-26',
                'description' => 'Paycheck',
            ])
            ->assertRedirect(route('cash-accounts.show', $account));

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'deposit',
            'amount'          => 1000,
        ]);

        $this->assertEquals(1000.0, $account->fresh()->balance());
    }

    public function test_balance_reflects_deposits_minus_withdrawals(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 1000]);
        CashTransaction::factory()->for($account, 'cashAccount')->withdrawal()->create(['amount' => 250]);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 50]);

        $this->assertEquals(800.0, $account->balance());
    }

    public function test_cleared_and_uncleared_balances_split_working_balance(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->cleared()->create(['amount' => 1000]);
        CashTransaction::factory()->for($account, 'cashAccount')->withdrawal()->cleared()->create(['amount' => 200]);
        CashTransaction::factory()->for($account, 'cashAccount')->withdrawal()->pending()->create(['amount' => 50]);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->pending()->create(['amount' => 30]);

        $this->assertEquals(800.0, $account->clearedBalance());
        $this->assertEquals(-20.0, $account->unclearedBalance());
        $this->assertEquals(780.0, $account->balance());

        // balances() must agree with the scalar methods. Regression guard: the "cleared"
        // alias used to hit CashTransaction's boolean cast and collapse the sum to 0/1.
        $this->assertEquals(
            ['working' => 780.0, 'cleared' => 800.0, 'uncleared' => -20.0],
            $account->balances(),
        );
    }

    public function test_reconcile_targets_cleared_balance_and_ignores_pending(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->cleared()->create(['amount' => 1000]);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->pending()->create(['amount' => 500]);

        // Bank reports 1010 cleared; the $500 pending deposit must not affect the adjustment.
        $this->actingAs($account->user)
            ->post(route('cash-accounts.reconcile', $account), [
                'actual_balance' => '1010.00',
                'occurred_at'    => '2026-05-17',
            ])
            ->assertRedirect(route('cash-accounts.show', $account));

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'deposit',
            'amount'          => '10.00',
            'description'     => 'Reconciliation adjustment',
            'cleared'         => true,
        ]);
        $this->assertEquals(1010.0, $account->fresh()->clearedBalance());
    }

    public function test_livewire_add_transaction_defaults_to_pending(): void
    {
        $account = CashAccount::factory()->create();

        Livewire::actingAs($account->user)
            ->test(TransactionList::class, ['account' => $account])
            ->set('newType', 'deposit')
            ->set('newAmount', '120')
            ->set('newOccurredAt', '2026-06-01')
            ->call('addTransaction');

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'amount'          => 120,
            'cleared'         => false,
        ]);
    }

    public function test_livewire_toggle_cleared_flips_status(): void
    {
        $account = CashAccount::factory()->create();
        $tx      = CashTransaction::factory()->for($account, 'cashAccount')->deposit()->pending()->create(['amount' => 75]);

        Livewire::actingAs($account->user)
            ->test(TransactionList::class, ['account' => $account])
            ->call('toggleCleared', $tx->id);

        $this->assertTrue($tx->fresh()->cleared);
    }

    public function test_transaction_validation_rejects_zero_amount(): void
    {
        $account = CashAccount::factory()->create();

        $this->actingAs($account->user)
            ->post(route('cash-accounts.transactions.store', $account), [
                'type'        => 'deposit',
                'amount'      => 0,
                'occurred_at' => '2026-04-26',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_transaction_forbidden_for_other_user(): void
    {
        $account = CashAccount::factory()->create();
        $other   = User::factory()->create();

        $this->actingAs($other)
            ->post(route('cash-accounts.transactions.store', $account), [
                'type'        => 'deposit',
                'amount'      => 100,
                'occurred_at' => '2026-04-26',
            ])
            ->assertForbidden();
    }

    public function test_delete_transaction(): void
    {
        $account = CashAccount::factory()->create();
        $tx      = CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 500]);

        $this->actingAs($account->user)
            ->delete(route('cash-accounts.transactions.destroy', $tx))
            ->assertRedirect(route('cash-accounts.show', $account));

        $this->assertDatabaseMissing('cash_transactions', ['id' => $tx->id]);
    }

    public function test_cannot_delete_another_users_cash_transaction(): void
    {
        $account = CashAccount::factory()->create();
        $tx      = CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 500]);
        $other   = User::factory()->create();

        $this->actingAs($other)
            ->delete(route('cash-accounts.transactions.destroy', $tx))
            ->assertForbidden();

        $this->assertDatabaseHas('cash_transactions', ['id' => $tx->id]);
    }

    public function test_reconcile_creates_deposit_when_actual_exceeds_tracked(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 1000]);

        $this->actingAs($account->user)
            ->post(route('cash-accounts.reconcile', $account), [
                'actual_balance' => '1050.75',
                'occurred_at'    => '2026-05-17',
            ])
            ->assertRedirect(route('cash-accounts.show', $account));

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'deposit',
            'amount'          => '50.75',
            'description'     => 'Reconciliation adjustment',
        ]);
        $this->assertEquals(1050.75, $account->fresh()->balance());
    }

    public function test_reconcile_creates_withdrawal_when_actual_below_tracked(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 1000]);

        $this->actingAs($account->user)
            ->post(route('cash-accounts.reconcile', $account), [
                'actual_balance' => '980.00',
                'occurred_at'    => '2026-05-17',
            ])
            ->assertRedirect(route('cash-accounts.show', $account));

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'withdrawal',
            'amount'          => '20.00',
            'description'     => 'Reconciliation adjustment',
        ]);
        $this->assertEquals(980.0, $account->fresh()->balance());
    }

    public function test_reconcile_with_matching_balance_creates_no_transaction(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 500]);

        $this->actingAs($account->user)
            ->post(route('cash-accounts.reconcile', $account), [
                'actual_balance' => '500.00',
                'occurred_at'    => '2026-05-17',
            ])
            ->assertRedirect(route('cash-accounts.show', $account))
            ->assertSessionHas('success');

        $this->assertCount(1, $account->fresh()->transactions);
    }

    public function test_reconcile_forbidden_for_other_user(): void
    {
        $account = CashAccount::factory()->create();
        $other   = User::factory()->create();

        $this->actingAs($other)
            ->post(route('cash-accounts.reconcile', $account), [
                'actual_balance' => '0',
                'occurred_at'    => '2026-05-17',
            ])
            ->assertForbidden();
    }

    public function test_credit_card_account_stores_interest_rate_and_billing_day(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('cash-accounts.store'), [
                'name'          => 'Visa Rewards',
                'account_type'  => 'credit_card',
                'currency'      => 'USD',
                'interest_rate' => '22.99',
                'billing_day'   => '15',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('cash_accounts', [
            'user_id'       => $user->id,
            'account_type'  => 'credit_card',
            'interest_rate' => '22.99',
            'billing_day'   => 15,
        ]);
    }

    public function test_cascade_delete_transactions_when_account_deleted(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create();

        $account->delete();

        $this->assertDatabaseMissing('cash_transactions', ['cash_account_id' => $account->id]);
    }

    public function test_show_renders_filter_and_dataset_attrs(): void
    {
        $account = CashAccount::factory()->create();
        CashTransaction::factory()->for($account, 'cashAccount')->withdrawal()->create([
            'amount'      => 45.32,
            'description' => 'Whole Foods',
            'occurred_at' => now()->subDays(3),
        ]);
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create([
            'amount'      => 1000,
            'description' => 'Paycheck',
            'occurred_at' => now()->subDays(1),
        ]);

        $response = $this->actingAs($account->user)
            ->get(route('cash-accounts.show', $account))
            ->assertOk();

        // Livewire renders server-side; transaction data is present in initial HTML.
        $response->assertSee('45.32')
            ->assertSee('Whole Foods')
            ->assertSee('wire:model', false);
    }

    public function test_dashboard_includes_cash_in_net_worth(): void
    {
        $user = User::factory()->create();
        Portfolio::factory()->for($user)->create();

        $account = CashAccount::factory()->for($user)->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 5000]);

        $liability = Liability::factory()->for($user)->create();
        LiabilityBalance::factory()->for($liability)->create(['balance' => 1000]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Net Worth')
            ->assertSee('$4,000.00');
    }
}
