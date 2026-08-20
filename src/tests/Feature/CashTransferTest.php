<?php

namespace Tests\Feature;

use App\Livewire\AllTransactions;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class CashTransferTest extends TestCase
{
    private function transferPayload(CashAccount $from, CashAccount $to, array $overrides = []): array
    {
        return array_merge([
            'from_account_id' => $from->id,
            'to_account_id'   => $to->id,
            'amount'          => '500',
            'description'     => 'Credit card payment',
            'occurred_at'     => '2026-07-01',
            'cleared'         => '1',
        ], $overrides);
    }

    public function test_create_page_requires_auth(): void
    {
        $this->get(route('cash-transfers.create'))->assertRedirect(route('login'));
    }

    public function test_store_requires_auth(): void
    {
        $this->post(route('cash-transfers.store'), [])->assertRedirect(route('login'));
    }

    public function test_create_page_shows_form_with_accounts(): void
    {
        $user = User::factory()->create();
        CashAccount::factory()->for($user)->create(['name' => 'Checking']);
        CashAccount::factory()->for($user)->create(['name' => 'Visa Card', 'account_type' => 'credit_card']);

        $this->actingAs($user)->get(route('cash-transfers.create'))
            ->assertOk()
            ->assertSee('Checking')
            ->assertSee('Visa Card');
    }

    public function test_store_creates_linked_withdrawal_and_deposit(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create(['account_type' => 'checking']);
        $card     = CashAccount::factory()->for($user)->create(['account_type' => 'credit_card']);

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($checking, $card))
            ->assertRedirect(route('cash-accounts.all'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cash_transactions', 2);

        $withdrawal = CashTransaction::where('type', 'withdrawal')->first();
        $deposit    = CashTransaction::where('type', 'deposit')->first();

        $this->assertEquals($checking->id, $withdrawal->cash_account_id);
        $this->assertEquals($card->id, $deposit->cash_account_id);
        $this->assertEquals($withdrawal->id, $deposit->linked_transaction_id);
        $this->assertNull($withdrawal->linked_transaction_id);
        $this->assertEquals('500.00000000', $withdrawal->amount);
        $this->assertEquals('500.00000000', $deposit->amount);
        $this->assertNull($withdrawal->envelope_id);
        $this->assertNull($deposit->income_category_id);
    }

    public function test_paying_a_credit_card_reduces_its_debt_balance(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create(['account_type' => 'checking']);
        $card     = CashAccount::factory()->for($user)->create(['account_type' => 'credit_card']);

        $card->transactions()->create([
            'type' => 'withdrawal', 'amount' => 500, 'occurred_at' => '2026-06-15', 'cleared' => true,
        ]);
        $this->assertEquals(-500.0, $card->fresh()->balance());

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($checking, $card));

        $this->assertEquals(0.0, $card->fresh()->balance());
        $this->assertEquals(-500.0, $checking->fresh()->balance());
    }

    public function test_linked_relations_point_to_correct_accounts(): void
    {
        $user     = User::factory()->create();
        $checking = CashAccount::factory()->for($user)->create();
        $card     = CashAccount::factory()->for($user)->create(['account_type' => 'credit_card']);

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($checking, $card));

        $withdrawal = CashTransaction::where('type', 'withdrawal')->with('linkedTo.cashAccount')->first();
        $deposit    = CashTransaction::where('type', 'deposit')->with('linkedFrom.cashAccount')->first();

        $this->assertEquals($deposit->id, $withdrawal->linkedTo->id);
        $this->assertEquals($card->id, $withdrawal->linkedTo->cash_account_id);
        $this->assertEquals($withdrawal->id, $deposit->linkedFrom->id);
        $this->assertEquals($checking->id, $deposit->linkedFrom->cash_account_id);

        $this->assertEquals($deposit->id, $withdrawal->transferCounterpart()->id);
        $this->assertEquals($withdrawal->id, $deposit->transferCounterpart()->id);
    }

    public function test_store_rejects_same_account(): void
    {
        $account = CashAccount::factory()->create();

        $this->actingAs($account->user)
            ->post(route('cash-transfers.store'), $this->transferPayload($account, $account))
            ->assertSessionHasErrors('to_account_id');

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_store_rejects_another_users_account(): void
    {
        $user    = User::factory()->create();
        $from    = CashAccount::factory()->for($user)->create();
        $foreign = CashAccount::factory()->create();

        $this->actingAs($user)
            ->post(route('cash-transfers.store'), $this->transferPayload($from, $foreign))
            ->assertSessionHasErrors('to_account_id');

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('cash-transfers.store'), [])
            ->assertSessionHasErrors(['from_account_id', 'to_account_id', 'amount', 'occurred_at']);
    }

    public function test_store_validates_positive_amount(): void
    {
        $user = User::factory()->create();
        $from = CashAccount::factory()->for($user)->create();
        $to   = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('cash-transfers.store'), $this->transferPayload($from, $to, ['amount' => '-5']))
            ->assertSessionHasErrors('amount');
    }

    /** The raw FK is ON DELETE SET NULL — the database-level fallback under the app behaviour below. */
    public function test_deleting_one_leg_at_the_model_level_nulls_the_link_on_the_other(): void
    {
        $user = User::factory()->create();
        $from = CashAccount::factory()->for($user)->create();
        $to   = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($from, $to));

        $withdrawal = CashTransaction::where('type', 'withdrawal')->first();
        $deposit    = CashTransaction::where('type', 'deposit')->first();

        $withdrawal->delete();

        $this->assertNull($deposit->fresh()->linked_transaction_id);
    }

    /**
     * A transfer is one event. Removing a single leg used to leave the other behind as a
     * standalone deposit/withdrawal, silently moving the user's net cash by the amount.
     */
    public function test_deleting_a_transfer_leg_removes_both_legs(): void
    {
        $user = User::factory()->create();
        $from = CashAccount::factory()->for($user)->create();
        $to   = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($from, $to));

        $deposit = CashTransaction::where('type', 'deposit')->firstOrFail();

        $this->actingAs($user)
            ->delete(route('cash-accounts.transactions.destroy', $deposit))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_deleting_a_transfer_leg_from_the_ledger_removes_both_legs(): void
    {
        $user = User::factory()->create();
        $from = CashAccount::factory()->for($user)->create();
        $to   = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($from, $to));

        $withdrawal = CashTransaction::where('type', 'withdrawal')->firstOrFail();

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->call('deleteTransaction', $withdrawal->id);

        $this->assertDatabaseCount('cash_transactions', 0);
    }

    public function test_editing_a_transfer_leg_syncs_amount_and_date_to_the_other(): void
    {
        $user = User::factory()->create();
        $from = CashAccount::factory()->for($user)->create();
        $to   = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($from, $to));

        $withdrawal = CashTransaction::where('type', 'withdrawal')->firstOrFail();
        $deposit    = CashTransaction::where('type', 'deposit')->firstOrFail();

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->call('startEdit', $withdrawal->id)
            ->set('editAmount', '750')
            ->set('editOccurredAt', '2026-07-15')
            ->call('saveEdit');

        $deposit = $deposit->fresh();
        $this->assertEquals('750.00000000', $deposit->amount);
        $this->assertSame('2026-07-15', $deposit->occurred_at->toDateString());
    }

    public function test_a_transfer_leg_cannot_have_its_direction_flipped(): void
    {
        $user = User::factory()->create();
        $from = CashAccount::factory()->for($user)->create();
        $to   = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($from, $to));

        $withdrawal = CashTransaction::where('type', 'withdrawal')->firstOrFail();

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->call('startEdit', $withdrawal->id)
            ->set('editType', 'deposit')
            ->call('saveEdit')
            ->assertHasErrors('editType');

        $this->assertSame('withdrawal', $withdrawal->fresh()->type);
    }

    public function test_a_transfer_leg_cannot_be_moved_to_another_account(): void
    {
        $user  = User::factory()->create();
        $from  = CashAccount::factory()->for($user)->create();
        $to    = CashAccount::factory()->for($user)->create();
        $other = CashAccount::factory()->for($user)->create();

        $this->actingAs($user)->post(route('cash-transfers.store'), $this->transferPayload($from, $to));

        $withdrawal = CashTransaction::where('type', 'withdrawal')->firstOrFail();

        Livewire::actingAs($user)
            ->test(AllTransactions::class)
            ->call('startEdit', $withdrawal->id)
            ->set('editAccountId', $other->id)
            ->call('saveEdit')
            ->assertHasErrors('editAccountId');

        $this->assertSame($from->id, $withdrawal->fresh()->cash_account_id);
    }
}
