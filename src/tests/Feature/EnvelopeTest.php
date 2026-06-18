<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\User;
use Tests\TestCase;

class EnvelopeTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('envelopes.index'))->assertRedirect(route('login'));
    }

    public function test_index_returns_ok_when_empty(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk()
            ->assertSee('No envelopes yet.');
    }

    public function test_create_envelope(): void
    {
        $user = User::factory()->create();

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
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('envelopes.store'), [
                'name'  => 'Foo',
                'color' => 'red',
            ])
            ->assertSessionHasErrors('color');
    }

    public function test_show_forbidden_for_other_user(): void
    {
        $envelope = Envelope::factory()->create();
        $other    = User::factory()->create();

        $this->actingAs($other)
            ->get(route('envelopes.show', $envelope))
            ->assertForbidden();
    }

    public function test_update_envelope(): void
    {
        $envelope = Envelope::factory()->create();

        $this->actingAs($envelope->user)
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
        $envelope = Envelope::factory()->create();

        $this->actingAs($envelope->user)
            ->delete(route('envelopes.destroy', $envelope))
            ->assertRedirect(route('envelopes.index'));

        $this->assertDatabaseMissing('envelopes', ['id' => $envelope->id]);
    }

    public function test_balance_reflects_funds_minus_spends(): void
    {
        $envelope = Envelope::factory()->create();
        $account  = CashAccount::factory()->for($envelope->user)->create();
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 500, 'occurred_at' => '2026-04-01']);
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 75, 'occurred_at' => '2026-04-10']);
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 25, 'occurred_at' => '2026-04-15']);

        $this->assertEquals(400.0, $envelope->fresh()->balance());
    }

    public function test_spent_in_month_only_counts_current_month(): void
    {
        $envelope = Envelope::factory()->create();
        $account  = CashAccount::factory()->for($envelope->user)->create();
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 100, 'occurred_at' => now()->startOfMonth()]);
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 50,  'occurred_at' => now()->endOfMonth()]);
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 999, 'occurred_at' => now()->subMonth()->startOfMonth()]);

        $this->assertEquals(150.0, $envelope->fresh()->spentInMonth());
    }

    public function test_record_fund_transaction(): void
    {
        $envelope = Envelope::factory()->create();

        $this->actingAs($envelope->user)
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
        $envelope = Envelope::factory()->create();

        $this->actingAs($envelope->user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'        => 'transfer',
                'amount'      => 100,
                'occurred_at' => '2026-04-26',
            ])
            ->assertSessionHasErrors('type');
    }

    public function test_transaction_forbidden_for_other_user(): void
    {
        $envelope = Envelope::factory()->create();
        $other    = User::factory()->create();

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
        $envelope = Envelope::factory()->create();
        $tx       = EnvelopeTransaction::factory()->for($envelope)->fund()->create();

        $this->actingAs($envelope->user)
            ->delete(route('envelopes.transactions.destroy', $tx))
            ->assertRedirect(route('envelopes.show', $envelope));

        $this->assertDatabaseMissing('envelope_transactions', ['id' => $tx->id]);
    }

    public function test_cannot_delete_another_users_envelope_transaction(): void
    {
        $envelope = Envelope::factory()->create();
        $tx       = EnvelopeTransaction::factory()->for($envelope)->fund()->create();
        $other    = User::factory()->create();

        $this->actingAs($other)
            ->delete(route('envelopes.transactions.destroy', $tx))
            ->assertForbidden();

        $this->assertDatabaseHas('envelope_transactions', ['id' => $tx->id]);
    }

    public function test_funding_from_cash_account_creates_paired_withdrawal(): void
    {
        $envelope = Envelope::factory()->create();
        $account  = CashAccount::factory()->for($envelope->user)->create();
        CashTransaction::factory()->for($account, 'cashAccount')->deposit()->create(['amount' => 1000]);

        $this->actingAs($envelope->user)
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
        $envelope = Envelope::factory()->create();
        $account  = CashAccount::factory()->for($envelope->user)->create();

        $this->actingAs($envelope->user)
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
        $envelope = Envelope::factory()->create();
        $account  = CashAccount::factory()->create();

        $this->actingAs($envelope->user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'            => 'fund',
                'amount'          => 200,
                'occurred_at'     => '2026-04-26',
                'cash_account_id' => $account->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('envelope_transactions', ['envelope_id' => $envelope->id]);
    }

    public function test_spend_type_rejected_from_envelope_transaction_form(): void
    {
        $envelope = Envelope::factory()->create();

        $this->actingAs($envelope->user)
            ->post(route('envelopes.transactions.store', $envelope), [
                'type'        => 'spend',
                'amount'      => 50,
                'occurred_at' => '2026-04-26',
            ])
            ->assertSessionHasErrors('type');

        $this->assertDatabaseMissing('envelope_transactions', ['envelope_id' => $envelope->id]);
    }

    public function test_cascade_delete_transactions_when_envelope_deleted(): void
    {
        $envelope = Envelope::factory()->create();
        EnvelopeTransaction::factory()->for($envelope)->fund()->create();

        $envelope->delete();

        $this->assertDatabaseMissing('envelope_transactions', ['envelope_id' => $envelope->id]);
    }

    public function test_index_defaults_to_current_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk()
            ->assertSee(now()->format('M Y'));
    }

    public function test_index_month_param_scopes_spend_totals(): void
    {
        $envelope = Envelope::factory()->create();
        $account  = CashAccount::factory()->for($envelope->user)->create();
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 100, 'occurred_at' => '2026-03-15']);
        CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 50,  'occurred_at' => '2026-04-10']);

        $this->actingAs($envelope->user)
            ->get(route('envelopes.index', ['month' => '2026-03']))
            ->assertOk()
            ->assertSee('Mar 2026')
            ->assertSee('100.00');
    }

    public function test_index_past_month_shows_assign_input(): void
    {
        $envelope  = Envelope::factory()->create();
        $pastMonth = now()->subMonths(2)->format('Y-m');

        $this->actingAs($envelope->user)
            ->get(route('envelopes.index', ['month' => $pastMonth]))
            ->assertOk()
            ->assertSee('rta-input w-32', false);
    }

    public function test_index_future_month_hides_assign_input(): void
    {
        $envelope    = Envelope::factory()->create();
        $futureMonth = now()->addMonths(2)->format('Y-m');

        $this->actingAs($envelope->user)
            ->get(route('envelopes.index', ['month' => $futureMonth]))
            ->assertOk()
            ->assertDontSee('rta-input w-32', false);
    }

    public function test_index_month_param_scopes_fund_totals(): void
    {
        $envelope = Envelope::factory()->create();
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 200, 'occurred_at' => '2026-04-01']);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 300, 'occurred_at' => '2026-05-01']);

        $this->actingAs($envelope->user)
            ->get(route('envelopes.index', ['month' => '2026-04']))
            ->assertOk()
            ->assertSee('200.00')
            ->assertDontSee('300.00');
    }

    public function test_index_invalid_month_param_defaults_to_current_month(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('envelopes.index', ['month' => 'not-a-date']))
            ->assertOk()
            ->assertSee(now()->format('M Y'));
    }

    public function test_index_next_arrow_disabled_on_current_month(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('envelopes.index'))
            ->assertOk();

        $nextMonth = now()->addMonth()->format('Y-m');
        $response->assertDontSee(route('envelopes.index', ['month' => $nextMonth]));
    }

    public function test_index_next_arrow_present_on_past_month(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('envelopes.index', ['month' => '2026-03']))
            ->assertOk();

        $response->assertSee(route('envelopes.index', ['month' => '2026-04']));
    }

    public function test_create_envelope_with_savings_goal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('envelopes.store'), [
                'name'        => 'Down Payment',
                'color'       => '#6366f1',
                'goal_amount' => 50000,
                'goal_date'   => '2028-06-01',
            ])
            ->assertRedirect();

        $envelope = Envelope::where('user_id', $user->id)->where('name', 'Down Payment')->firstOrFail();
        $this->assertEquals(50000.0, (float) $envelope->goal_amount);
        $this->assertEquals('2028-06-01', $envelope->goal_date->format('Y-m-d'));
    }

    public function test_update_envelope_goal(): void
    {
        $envelope = Envelope::factory()->create(['goal_amount' => null, 'goal_date' => null]);

        $this->actingAs($envelope->user)
            ->put(route('envelopes.update', $envelope), [
                'name'        => $envelope->name,
                'color'       => $envelope->color,
                'goal_amount' => 10000,
                'goal_date'   => '2027-01-01',
            ])
            ->assertRedirect();

        $envelope->refresh();
        $this->assertEquals(10000.0, (float) $envelope->goal_amount);
        $this->assertEquals('2027-01-01', $envelope->goal_date->format('Y-m-d'));
    }

    public function test_show_displays_savings_goal_tile(): void
    {
        $envelope = Envelope::factory()->create([
            'goal_amount' => 20000,
            'goal_date'   => '2028-01-01',
        ]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 5000]);

        $this->actingAs($envelope->user)
            ->get(route('envelopes.show', $envelope))
            ->assertOk()
            ->assertSee('Savings Goal')
            ->assertSee('20,000.00')
            ->assertSee('by Jan 2028');
    }

    public function test_index_displays_goal_progress_for_savings_envelope(): void
    {
        $envelope = Envelope::factory()->create([
            'goal_amount' => 10000,
            'goal_date'   => '2027-06-01',
        ]);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['amount' => 2500]);

        $this->actingAs($envelope->user)
            ->get(route('envelopes.index'))
            ->assertOk()
            ->assertSee('goal $2,500.00 / $10,000.00');
    }

    public function test_goal_without_date_is_valid(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('envelopes.store'), [
                'name'        => 'Vacation Fund',
                'color'       => '#10b981',
                'goal_amount' => 3000,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('envelopes', [
            'user_id'     => $user->id,
            'goal_amount' => 3000,
            'goal_date'   => null,
        ]);
    }
}
