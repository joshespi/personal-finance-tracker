<?php

namespace Tests\Feature;

use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use App\Models\User;
use Tests\TestCase;

class SpendingTrendsTest extends TestCase
{
    public function test_index_requires_auth(): void
    {
        $this->get(route('spending-trends'))->assertRedirect(route('login'));
    }

    public function test_shows_envelope_spend_for_current_user(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Groceries']);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create([
            'amount'      => 150,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('spending-trends'))
            ->assertOk()
            ->assertSee('Groceries');
    }

    public function test_does_not_show_other_users_envelope_spend(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $envelope = Envelope::factory()->for($other)->create(['name' => 'Other Groceries']);
        EnvelopeTransaction::factory()->for($envelope)->spend()->create([
            'amount'      => 200,
            'occurred_at' => now()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('spending-trends'))
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
            ->get(route('spending-trends'))
            ->assertOk()
            ->assertSee('No envelope spending recorded');
    }

    public function test_months_param_accepted(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('spending-trends', ['months' => 12]))
            ->assertOk();
    }

    public function test_months_param_clamped_to_valid_range(): void
    {
        // months=999 should clamp to 12 — page still loads
        $this->actingAs(User::factory()->create())
            ->get(route('spending-trends', ['months' => 999]))
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
            ->get(route('spending-trends'))
            ->assertOk();

        // The "No spending recorded" message confirms no datasets were returned
        $response->assertSee('No envelope spending recorded');
    }

    public function test_transactions_outside_range_excluded(): void
    {
        $user     = User::factory()->create();
        $envelope = Envelope::factory()->for($user)->create(['name' => 'Travel']);
        // 13 months ago — outside the default 6-month window
        EnvelopeTransaction::factory()->for($envelope)->spend()->create([
            'amount'      => 500,
            'occurred_at' => now()->subMonths(13)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('spending-trends'))
            ->assertOk()
            ->assertDontSee('Travel');
    }
}
