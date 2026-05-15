<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class DividendTest extends TestCase
{
    public function test_dividend_page_requires_auth(): void
    {
        $this->get(route('dividends'))->assertRedirect(route('login'));
    }

    public function test_dividend_page_shows_empty_state_with_no_transactions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dividends'))
            ->assertOk()
            ->assertSee('No dividend transactions');
    }

    public function test_dividend_page_shows_income_for_current_year(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        // Two dividend payments this year
        Transaction::factory()->for($portfolio)->create([
            'asset_id'       => $asset->id,
            'type'           => 'dividend',
            'quantity'       => 10,
            'price_per_unit' => 1.50,
            'transacted_at'  => now()->startOfYear()->addMonths(2),
        ]);
        Transaction::factory()->for($portfolio)->create([
            'asset_id'       => $asset->id,
            'type'           => 'dividend',
            'quantity'       => 10,
            'price_per_unit' => 1.60,
            'transacted_at'  => now()->startOfYear()->addMonths(5),
        ]);

        $this->actingAs($user)
            ->get(route('dividends', ['year' => now()->year]))
            ->assertOk()
            ->assertSee('AAPL')
            ->assertSee('31.00')   // 15.00 + 16.00
            ->assertSee('2');      // 2 payments
    }

    public function test_dividend_page_shows_year_tabs(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'VTI']);

        Transaction::factory()->for($portfolio)->create([
            'asset_id'       => $asset->id,
            'type'           => 'dividend',
            'quantity'       => 5,
            'price_per_unit' => 2.00,
            'transacted_at'  => '2024-06-15 00:00:00',
        ]);
        Transaction::factory()->for($portfolio)->create([
            'asset_id'       => $asset->id,
            'type'           => 'dividend',
            'quantity'       => 5,
            'price_per_unit' => 2.10,
            'transacted_at'  => '2025-03-15 00:00:00',
        ]);

        $this->actingAs($user)
            ->get(route('dividends', ['year' => 2024]))
            ->assertOk()
            ->assertSee('2024')
            ->assertSee('2025')
            ->assertSee('10.00');   // 5 × 2.00 for 2024
    }

    public function test_cross_user_isolation(): void
    {
        $userA     = User::factory()->create();
        $userB     = User::factory()->create();
        $portfolio = Portfolio::factory()->for($userB)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'SPY']);

        Transaction::factory()->for($portfolio)->create([
            'asset_id'       => $asset->id,
            'type'           => 'dividend',
            'quantity'       => 100,
            'price_per_unit' => 5.00,
            'transacted_at'  => now(),
        ]);

        $this->actingAs($userA)
            ->get(route('dividends'))
            ->assertOk()
            ->assertDontSee('500.00');
    }

    public function test_non_dividend_transactions_excluded(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'MSFT']);

        Transaction::factory()->for($portfolio)->create([
            'asset_id'       => $asset->id,
            'type'           => 'buy',
            'quantity'       => 10,
            'price_per_unit' => 300.00,
            'transacted_at'  => now(),
        ]);

        $this->actingAs($user)
            ->get(route('dividends'))
            ->assertOk()
            ->assertSee('No dividend transactions');
    }
}
