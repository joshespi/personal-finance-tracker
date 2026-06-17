<?php

namespace Tests\Feature;

use App\Livewire\TransactionList;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\IncomeCategory;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class IncomeCategoryTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('income-categories.index'))->assertRedirect(route('login'));
    }

    public function test_user_can_create_category(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('income-categories.store'), [
            'name'  => 'Salary',
            'color' => '#10b981',
        ])->assertRedirect(route('income-categories.index'));

        $this->assertDatabaseHas('income_categories', [
            'user_id' => $user->id,
            'name'    => 'Salary',
            'color'   => '#10b981',
        ]);
    }

    public function test_category_name_is_unique_per_user(): void
    {
        $user = User::factory()->create();
        IncomeCategory::factory()->for($user)->create(['name' => 'Salary']);

        $this->actingAs($user)->post(route('income-categories.store'), [
            'name'  => 'Salary',
            'color' => '#10b981',
        ])->assertSessionHasErrors('name');

        $this->assertEquals(1, IncomeCategory::where('user_id', $user->id)->count());
    }

    public function test_same_name_allowed_across_users(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        IncomeCategory::factory()->for($a)->create(['name' => 'Salary']);

        $this->actingAs($b)->post(route('income-categories.store'), [
            'name'  => 'Salary',
            'color' => '#10b981',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('income_categories', ['user_id' => $b->id, 'name' => 'Salary']);
    }

    public function test_user_cannot_edit_another_users_category(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $cat   = IncomeCategory::factory()->for($owner)->create();

        $this->actingAs($other)->get(route('income-categories.edit', $cat))->assertForbidden();
        $this->actingAs($other)->delete(route('income-categories.destroy', $cat))->assertForbidden();
    }

    public function test_deleting_category_keeps_deposit_but_nulls_category(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        $cat     = IncomeCategory::factory()->for($user)->create();
        $tx      = CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create([
            'amount'             => 500,
            'income_category_id' => $cat->id,
        ]);

        $this->actingAs($user)->delete(route('income-categories.destroy', $cat))->assertRedirect();

        $this->assertDatabaseMissing('income_categories', ['id' => $cat->id]);
        $this->assertDatabaseHas('cash_transactions', ['id' => $tx->id, 'income_category_id' => null]);
    }

    public function test_livewire_deposit_can_be_tagged_with_category(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        $cat     = IncomeCategory::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->set('newType', 'deposit')
            ->set('newAmount', '1500')
            ->set('newOccurredAt', '2026-06-01')
            ->set('newIncomeCategoryId', $cat->id)
            ->call('addTransaction');

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id'    => $account->id,
            'amount'             => 1500,
            'income_category_id' => $cat->id,
        ]);
    }

    public function test_livewire_category_is_dropped_on_withdrawal(): void
    {
        $user    = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        $cat     = IncomeCategory::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->set('newType', 'withdrawal')
            ->set('newAmount', '40')
            ->set('newOccurredAt', '2026-06-01')
            ->set('newIncomeCategoryId', $cat->id)
            ->call('addTransaction');

        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id'    => $account->id,
            'amount'             => 40,
            'type'               => 'withdrawal',
            'income_category_id' => null,
        ]);
    }

    public function test_livewire_rejects_another_users_category(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $account = CashAccount::factory()->for($user)->create();
        $foreign = IncomeCategory::factory()->for($other)->create();

        Livewire::actingAs($user)
            ->test(TransactionList::class, ['account' => $account])
            ->set('newType', 'deposit')
            ->set('newAmount', '1500')
            ->set('newOccurredAt', '2026-06-01')
            ->set('newIncomeCategoryId', $foreign->id)
            ->call('addTransaction')
            ->assertHasErrors('newIncomeCategoryId');

        $this->assertDatabaseMissing('cash_transactions', ['income_category_id' => $foreign->id]);
    }
}
