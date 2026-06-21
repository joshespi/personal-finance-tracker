<?php

namespace Tests\Feature;

use App\Livewire\AllTransactions;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class AllCashTransactionsTest extends TestCase
{
    public function test_page_requires_auth(): void
    {
        $this->get(route('cash-accounts.all'))->assertRedirect(route('login'));
    }

    public function test_page_renders_for_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('cash-accounts.all'))
            ->assertOk()
            ->assertSeeLivewire(AllTransactions::class);
    }

    public function test_ledger_spans_every_account(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create(['name' => 'Chase Checking']);
        $savings  = CashAccount::factory()->for($user)->create(['name' => 'Ally Savings']);

        CashTransaction::factory()->for($checking, 'cashAccount')->deposit()->create(['description' => 'Paycheck']);
        CashTransaction::factory()->for($savings, 'cashAccount')->withdrawal()->create(['description' => 'Transfer out']);

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->assertSee('Paycheck')
            ->assertSee('Transfer out')
            ->assertSee('Chase Checking')
            ->assertSee('Ally Savings');
    }

    public function test_does_not_show_other_users_transactions(): void
    {
        $user  = User::factory()->create();
        $other = CashAccount::factory()->create(); // belongs to a different user
        CashTransaction::factory()->for($other, 'cashAccount')->deposit()->create(['description' => 'Not mine']);

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->assertDontSee('Not mine');
    }

    public function test_add_transaction_targets_the_chosen_account(): void
    {
        $user = User::factory()->create();
        CashAccount::factory()->for($user)->create(['name' => 'AAA First']);
        $savings = CashAccount::factory()->for($user)->create(['name' => 'ZZZ Savings']);

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->set('newAccountId', $savings->id)
            ->set('newType', 'deposit')
            ->set('newAmount', '250')
            ->set('newOccurredAt', '2026-06-01')
            ->call('addTransaction');

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $savings->id,
            'amount'          => 250,
        ]);
    }

    public function test_add_transaction_rejects_account_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        CashAccount::factory()->for($user)->create();
        $foreign = CashAccount::factory()->create(); // another user's account

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->set('newAccountId', $foreign->id)
            ->set('newType', 'deposit')
            ->set('newAmount', '100')
            ->set('newOccurredAt', '2026-06-01')
            ->call('addTransaction')
            ->assertForbidden();

        $this->assertDatabaseMissing('cash_transactions', [
            'cash_account_id' => $foreign->id,
            'amount'          => 100,
        ]);
    }

    public function test_edit_can_move_transaction_between_owned_accounts(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create();
        $savings  = CashAccount::factory()->for($user)->create();
        $tx       = CashTransaction::factory()->for($checking, 'cashAccount')->deposit()->create(['amount' => 500]);

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->call('startEdit', $tx->id)
            ->set('editAccountId', $savings->id)
            ->call('saveEdit');

        $this->assertSame($savings->id, $tx->fresh()->cash_account_id);
    }

    public function test_edit_cannot_move_transaction_to_foreign_account(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create();
        $foreign  = CashAccount::factory()->create();
        $tx       = CashTransaction::factory()->for($checking, 'cashAccount')->deposit()->create(['amount' => 500]);

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->call('startEdit', $tx->id)
            ->set('editAccountId', $foreign->id)
            ->call('saveEdit')
            ->assertForbidden();

        $this->assertSame($checking->id, $tx->fresh()->cash_account_id);
    }

    public function test_account_filter_narrows_the_ledger(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create();
        $savings  = CashAccount::factory()->for($user)->create();

        CashTransaction::factory()->for($checking, 'cashAccount')->deposit()->create(['description' => 'Checking item']);
        CashTransaction::factory()->for($savings, 'cashAccount')->deposit()->create(['description' => 'Savings item']);

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->set('accountFilter', $checking->id)
            ->assertSee('Checking item')
            ->assertDontSee('Savings item');
    }

    public function test_aggregate_balance_sums_all_accounts(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create();
        $savings  = CashAccount::factory()->for($user)->create();

        CashTransaction::factory()->for($checking, 'cashAccount')->deposit()->create(['amount' => 1000]);
        CashTransaction::factory()->for($savings, 'cashAccount')->deposit()->create(['amount' => 250]);
        CashTransaction::factory()->for($checking, 'cashAccount')->withdrawal()->create(['amount' => 300]);

        $component = Livewire::actingAs($user)->test(AllTransactions::class);

        $this->assertSame(950.0, $component->instance()->balances['working']);
    }
}
