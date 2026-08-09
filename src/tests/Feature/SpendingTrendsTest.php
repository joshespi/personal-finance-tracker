<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\User;
use App\Services\SpendingTrendsService;
use Tests\TestCase;

class SpendingTrendsTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('analysis', ['tab' => 'trends']))->assertRedirect(route('login'));
    }

    public function test_shows_envelope_spend_for_current_user(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Groceries']);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 150,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('analysis', ['tab' => 'trends']))
            ->assertOk()
            ->assertSee('Groceries');
    }

    public function test_does_not_show_other_users_envelope_spend(): void
    {
        $user     = User::factory()->create();
        $other    = User::factory()->create();
        $account  = CashAccount::factory()->for($other)->create();
        $envelope = Envelope::factory()->for($other)->create(['name' => 'Other Groceries']);
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 200,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('analysis', ['tab' => 'trends']))
            ->assertOk()
            ->assertDontSee('Other Groceries');
    }

    public function test_empty_state_when_no_spend_transactions(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create();
        // Only a fund transaction — no spend
        EnvelopeTransaction::factory()->for($envelope)->fund()->create(['occurred_at' => now()->toDateString()]);

        $this->actingAs($user)
            ->get(route('analysis', ['tab' => 'trends']))
            ->assertOk()
            ->assertSee('No envelope spending recorded');
    }

    public function test_months_param_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('analysis', ['tab' => 'trends', 'months' => 12]))
            ->assertOk();
    }

    public function test_months_param_clamped_to_valid_range(): void
    {
        // months=999 should clamp to 12 — page still loads
        $this->actingAs(User::factory()->create())
            ->get(route('analysis', ['tab' => 'trends', 'months' => 999]))
            ->assertOk();
    }

    public function test_fund_transactions_not_counted_as_spend(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Rent']);
        EnvelopeTransaction::factory()->for($envelope)->fund()->create([
            'amount'      => 1200,
            'occurred_at' => now()->toDateString(),
        ]);

        // Rent envelope should not appear in datasets since it has no spend
        $response = $this->actingAs($user)
            ->get(route('analysis', ['tab' => 'trends']))
            ->assertOk();

        // The "No spending recorded" message confirms no datasets were returned
        $response->assertSee('No envelope spending recorded');
    }

    /**
     * Regression: compute() built the spend-row date range with
     * `$monthStarts->last()->endOfMonth()`, which mutates Carbon in place — corrupting the
     * last entry of the `monthStarts` collection that's also returned to the caller. Not
     * currently exploitable (every consumer only ->format()s the values), but a future one
     * reading monthStarts as a date would silently get an end-of-month value for the most
     * recent month instead of its start.
     */
    public function test_month_starts_are_not_mutated_to_end_of_month(): void
    {
        $user = User::factory()->create();

        $monthStarts = (new SpendingTrendsService)->compute($user, 6)['monthStarts'];

        $this->assertSame(1, $monthStarts->last()->day);
    }

    public function test_transactions_outside_range_excluded(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Travel']);
        // 13 months ago — outside the default 6-month window
        CashTransaction::factory()->for($account)->spend($envelope)->create([
            'amount'      => 500,
            'occurred_at' => now()->subMonths(13)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('analysis', ['tab' => 'trends']))
            ->assertOk()
            ->assertSee('No envelope spending recorded');
    }
}
