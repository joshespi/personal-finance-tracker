<?php

namespace Tests\Feature;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\User;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    public function test_forecast_requires_auth(): void
    {
        $this->get(route('forecast'))->assertRedirect(route('login'));
    }

    public function test_forecast_loads_for_empty_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forecast'))
            ->assertOk()
            ->assertSee('Net Worth Forecast')
            ->assertSee('Year-by-Year');
    }

    public function test_forecast_accepts_custom_inputs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('forecast', [
                'starting_nw'     => 100000,
                'monthly_savings' => 2000,
                'annual_return'   => 8,
                'inflation_rate'  => 3,
                'years'           => 20,
            ]))
            ->assertOk()
            ->assertSee('20yr');
    }

    public function test_forecast_shows_fire_target_when_reachable(): void
    {
        $user = User::factory()->create();

        // Starting at $950k with $10k/mo savings, $1M is hit in year 0 or 1
        $this->actingAs($user)
            ->get(route('forecast', [
                'starting_nw'     => 950000,
                'monthly_savings' => 10000,
                'annual_return'   => 7,
                'fire_target'     => 1000000,
                'years'           => 30,
            ]))
            ->assertOk()
            ->assertSee('FIRE Target')
            ->assertSee('1,000,000');
    }

    public function test_forecast_shows_shortfall_when_fire_target_unreachable(): void
    {
        $user = User::factory()->create();

        // $0 starting, $0 savings, 0% return — $1B never reached in 10 years
        $this->actingAs($user)
            ->get(route('forecast', [
                'starting_nw'     => 0,
                'monthly_savings' => 0,
                'annual_return'   => 0,
                'fire_target'     => 1_000_000_000,
                'years'           => 10,
            ]))
            ->assertOk()
            ->assertSee('Not reached in');
    }

    public function test_forecast_prefills_monthly_savings_from_cashflow(): void
    {
        $user     = User::factory()->create();
        $account  = CashAccount::factory()->for($user)->create();
        $envelope = Envelope::factory()->for($user)->create();

        // 3 months of deposits and spend — average net = (900 - 300) / 3 = 200/mo
        $months = [now()->subMonths(3), now()->subMonths(2), now()->subMonths(1)];
        foreach ($months as $m) {
            CashTransaction::factory()->for($account)->deposit()->create(['amount' => 300, 'occurred_at' => $m->startOfMonth()]);
            CashTransaction::factory()->for($account)->spend($envelope)->create(['amount' => 100, 'occurred_at' => $m->startOfMonth()]);
        }

        $response = $this->actingAs($user)->get(route('forecast'))->assertOk();

        // Default monthly_savings field should be pre-filled with 200
        $response->assertSee('value="200"', false);
    }

    public function test_milestone_hit_at_year_zero_when_already_above_threshold(): void
    {
        $user = User::factory()->create();

        // Starting at $2M already exceeds $500k and $1M milestones at year 0
        $response = $this->actingAs($user)
            ->get(route('forecast', [
                'starting_nw'     => 2_000_000,
                'monthly_savings' => 0,
                'annual_return'   => 0,
                'years'           => 30,
            ]))
            ->assertOk();

        $response->assertSee('Year 0');
    }
}
