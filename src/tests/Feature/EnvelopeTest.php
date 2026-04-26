<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create();
    }

    private function makeEnvelope(User $user, string $name = 'Groceries'): Envelope
    {
        return Envelope::create([
            'user_id'        => $user->id,
            'name'           => $name,
            'monthly_target' => 500,
            'color'          => '#6366f1',
        ]);
    }

    public function test_index_requires_auth(): void
    {
        $this->get(route('envelopes.index'))->assertRedirect(route('login'));
    }

    public function test_index_returns_ok_when_empty(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk()
            ->assertSee('No envelopes yet.');
    }

    public function test_create_envelope(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('envelopes.store'), [
                'name'           => 'Groceries',
                'monthly_target' => 500,
                'color'          => '#10b981',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('envelopes', [
            'user_id' => $user->id,
            'name'    => 'Groceries',
            'color'   => '#10b981',
        ]);
    }

    public function test_validation_rejects_invalid_color(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->post(route('envelopes.store'), [
                'name'  => 'Foo',
                'color' => 'red',
            ])
            ->assertSessionHasErrors('color');
    }

    public function test_show_forbidden_for_other_user(): void
    {
        $user     = $this->makeUser();
        $other    = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $this->actingAs($other)
            ->get(route('envelopes.show', $envelope))
            ->assertForbidden();
    }

    public function test_update_envelope(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $this->actingAs($user)
            ->put(route('envelopes.update', $envelope), [
                'name'           => 'Renamed',
                'monthly_target' => 600,
                'color'          => '#f59e0b',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('envelopes', ['id' => $envelope->id, 'name' => 'Renamed']);
    }

    public function test_delete_envelope(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $this->actingAs($user)
            ->delete(route('envelopes.destroy', $envelope))
            ->assertRedirect(route('envelopes.index'));

        $this->assertDatabaseMissing('envelopes', ['id' => $envelope->id]);
    }

    public function test_balance_reflects_funds_minus_spends(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $envelope->transactions()->create(['type' => 'fund', 'amount' => 500, 'occurred_at' => '2026-04-01']);
        $envelope->transactions()->create(['type' => 'spend', 'amount' => 75, 'occurred_at' => '2026-04-10']);
        $envelope->transactions()->create(['type' => 'spend', 'amount' => 25, 'occurred_at' => '2026-04-15']);

        $this->assertEquals(400.0, $envelope->balance());
    }

    public function test_spent_in_month_only_counts_current_month(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $envelope->transactions()->create(['type' => 'spend', 'amount' => 100, 'occurred_at' => now()->startOfMonth()]);
        $envelope->transactions()->create(['type' => 'spend', 'amount' => 50, 'occurred_at' => now()->endOfMonth()]);
        $envelope->transactions()->create(['type' => 'spend', 'amount' => 999, 'occurred_at' => now()->subMonth()->startOfMonth()]);

        $this->assertEquals(150.0, $envelope->spentInMonth());
    }

    public function test_record_fund_transaction(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $this->actingAs($user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'        => 'fund',
                'amount'      => 200,
                'occurred_at' => '2026-04-26',
                'description' => 'April allocation',
            ])
            ->assertRedirect(route('envelopes.show', $envelope));

        $this->assertDatabaseHas('envelope_transactions', [
            'envelope_id' => $envelope->id,
            'type'        => 'fund',
            'amount'      => 200,
        ]);
    }

    public function test_transaction_validation_rejects_invalid_type(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $this->actingAs($user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'        => 'transfer',
                'amount'      => 100,
                'occurred_at' => '2026-04-26',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_transaction_forbidden_for_other_user(): void
    {
        $user     = $this->makeUser();
        $other    = $this->makeUser();
        $envelope = $this->makeEnvelope($user);

        $this->actingAs($other)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'        => 'fund',
                'amount'      => 100,
                'occurred_at' => '2026-04-26',
            ])
            ->assertForbidden();
    }

    public function test_delete_transaction(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);
        $tx       = $envelope->transactions()->create(['type' => 'fund', 'amount' => 100, 'occurred_at' => '2026-04-26']);

        $this->actingAs($user)
            ->delete(route('envelopes.transactions.destroy', $tx))
            ->assertRedirect(route('envelopes.show', $envelope));

        $this->assertDatabaseMissing('envelope_transactions', ['id' => $tx->id]);
    }

    public function test_funding_from_cash_account_creates_paired_withdrawal(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);
        $account  = \App\Models\CashAccount::create([
            'user_id'      => $user->id,
            'name'         => 'Checking',
            'account_type' => 'checking',
            'currency'     => 'USD',
        ]);
        $account->transactions()->create(['type' => 'deposit', 'amount' => 1000, 'occurred_at' => '2026-04-01']);

        $this->actingAs($user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'            => 'fund',
                'amount'          => 200,
                'occurred_at'     => '2026-04-26',
                'cash_account_id' => $account->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('envelope_transactions', [
            'envelope_id' => $envelope->id,
            'type'        => 'fund',
            'amount'      => 200,
        ]);
        $this->assertDatabaseHas('cash_transactions', [
            'cash_account_id' => $account->id,
            'type'            => 'withdrawal',
            'amount'          => 200,
        ]);
        $this->assertEquals(800.0, $account->fresh()->balance());
        $this->assertEquals(200.0, $envelope->fresh()->balance());
    }

    public function test_funding_without_cash_account_only_creates_envelope_transaction(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);
        $account  = \App\Models\CashAccount::create([
            'user_id'      => $user->id,
            'name'         => 'Checking',
            'account_type' => 'checking',
            'currency'     => 'USD',
        ]);

        $this->actingAs($user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'        => 'fund',
                'amount'      => 200,
                'occurred_at' => '2026-04-26',
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('cash_transactions', ['cash_account_id' => $account->id]);
        $this->assertEquals(200.0, $envelope->fresh()->balance());
    }

    public function test_cannot_fund_from_other_users_cash_account(): void
    {
        $user     = $this->makeUser();
        $other    = $this->makeUser();
        $envelope = $this->makeEnvelope($user);
        $account  = \App\Models\CashAccount::create([
            'user_id'      => $other->id,
            'name'         => 'Other Checking',
            'account_type' => 'checking',
            'currency'     => 'USD',
        ]);

        $this->actingAs($user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'            => 'fund',
                'amount'          => 200,
                'occurred_at'     => '2026-04-26',
                'cash_account_id' => $account->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('envelope_transactions', ['envelope_id' => $envelope->id]);
    }

    public function test_cash_account_id_ignored_for_spend_transactions(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);
        $account  = \App\Models\CashAccount::create([
            'user_id'      => $user->id,
            'name'         => 'Checking',
            'account_type' => 'checking',
            'currency'     => 'USD',
        ]);

        $this->actingAs($user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'            => 'spend',
                'amount'          => 50,
                'occurred_at'     => '2026-04-26',
                'cash_account_id' => $account->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('cash_transactions', ['cash_account_id' => $account->id]);
        $this->assertDatabaseHas('envelope_transactions', ['envelope_id' => $envelope->id, 'type' => 'spend']);
    }

    public function test_cascade_delete_transactions_when_envelope_deleted(): void
    {
        $user     = $this->makeUser();
        $envelope = $this->makeEnvelope($user);
        $envelope->transactions()->create(['type' => 'fund', 'amount' => 100, 'occurred_at' => '2026-04-26']);

        $envelope->delete();

        $this->assertDatabaseMissing('envelope_transactions', ['envelope_id' => $envelope->id]);
    }
}
