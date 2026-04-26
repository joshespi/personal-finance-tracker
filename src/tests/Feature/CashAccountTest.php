<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashAccountTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeAccount(User $user, string $name = 'Checking'): CashAccount
    {
        return CashAccount::create([
            'user_id'      => $user->id,
            'name'         => $name,
            'account_type' => 'checking',
            'currency'     => 'USD',
        ]);
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('cash-accounts.index'))->assertRedirect(route('login'));
    }

    public function test_index_returns_ok_when_empty(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('cash-accounts.index'))
            ->assertOk()
            ->assertSee('No cash accounts yet.');
    }

    public function test_create_account(): void
    {
        $user = $this->makeUser();

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
        $user = $this->makeUser();

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
        $user    = $this->makeUser();
        $other   = $this->makeUser();
        $account = $this->makeAccount($user);

        $this->actingAs($other)
            ->get(route('cash-accounts.show', $account))
            ->assertForbidden();
    }

    public function test_update_account(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount($user);

        $this->actingAs($user)
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
        $user    = $this->makeUser();
        $account = $this->makeAccount($user);

        $this->actingAs($user)
            ->delete(route('cash-accounts.destroy', $account))
            ->assertRedirect(route('cash-accounts.index'));

        $this->assertDatabaseMissing('cash_accounts', ['id' => $account->id]);
    }

    public function test_record_deposit(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount($user);

        $this->actingAs($user)
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
        $user    = $this->makeUser();
        $account = $this->makeAccount($user);

        $account->transactions()->create(['type' => 'deposit', 'amount' => 1000, 'occurred_at' => '2026-04-01']);
        $account->transactions()->create(['type' => 'withdrawal', 'amount' => 250, 'occurred_at' => '2026-04-10']);
        $account->transactions()->create(['type' => 'deposit', 'amount' => 50, 'occurred_at' => '2026-04-15']);

        $this->assertEquals(800.0, $account->balance());
    }

    public function test_transaction_validation_rejects_zero_amount(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount($user);

        $this->actingAs($user)
            ->post(route('cash-accounts.transactions.store', $account), [
                'type'        => 'deposit',
                'amount'      => 0,
                'occurred_at' => '2026-04-26',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_transaction_forbidden_for_other_user(): void
    {
        $user    = $this->makeUser();
        $other   = $this->makeUser();
        $account = $this->makeAccount($user);

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
        $user    = $this->makeUser();
        $account = $this->makeAccount($user);
        $tx      = $account->transactions()->create(['type' => 'deposit', 'amount' => 500, 'occurred_at' => '2026-04-26']);

        $this->actingAs($user)
            ->delete(route('cash-accounts.transactions.destroy', $tx))
            ->assertRedirect(route('cash-accounts.show', $account));

        $this->assertDatabaseMissing('cash_transactions', ['id' => $tx->id]);
    }

    public function test_cascade_delete_transactions_when_account_deleted(): void
    {
        $user    = $this->makeUser();
        $account = $this->makeAccount($user);
        $account->transactions()->create(['type' => 'deposit', 'amount' => 100, 'occurred_at' => '2026-04-26']);

        $account->delete();

        $this->assertDatabaseMissing('cash_transactions', ['cash_account_id' => $account->id]);
    }

    public function test_dashboard_includes_cash_in_net_worth(): void
    {
        $user = $this->makeUser();
        \App\Models\Portfolio::create(['user_id' => $user->id, 'name' => 'P', 'currency' => 'USD']);

        $account = $this->makeAccount($user);
        $account->transactions()->create(['type' => 'deposit', 'amount' => 5000, 'occurred_at' => '2026-04-26']);

        $liability = \App\Models\Liability::create([
            'user_id'        => $user->id,
            'name'           => 'Card',
            'liability_type' => 'credit_card',
            'currency'       => 'USD',
        ]);
        $liability->balances()->create(['balance' => 1000, 'recorded_at' => now()]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Net Worth')
            ->assertSee('$4,000.00'); // 5000 cash - 1000 debt
    }
}
