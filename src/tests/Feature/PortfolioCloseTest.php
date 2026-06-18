<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class PortfolioCloseTest extends TestCase
{
    public function test_close_sets_closed_at(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->patch(route('portfolios.close', $portfolio))
            ->assertRedirect(route('portfolios.show', $portfolio));

        $this->assertNotNull($portfolio->fresh()->closed_at);
    }

    public function test_reopen_clears_closed_at(): void
    {
        $portfolio = Portfolio::factory()->create(['closed_at' => now()]);

        $this->actingAs($portfolio->user)
            ->patch(route('portfolios.reopen', $portfolio))
            ->assertRedirect(route('portfolios.show', $portfolio));

        $this->assertNull($portfolio->fresh()->closed_at);
    }

    public function test_close_forbidden_for_other_user(): void
    {
        $portfolio = Portfolio::factory()->create();
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->patch(route('portfolios.close', $portfolio))
            ->assertForbidden();

        $this->assertNull($portfolio->fresh()->closed_at);
    }

    public function test_reopen_forbidden_for_other_user(): void
    {
        $portfolio = Portfolio::factory()->create(['closed_at' => now()]);
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->patch(route('portfolios.reopen', $portfolio))
            ->assertForbidden();

        $this->assertNotNull($portfolio->fresh()->closed_at);
    }

    public function test_dashboard_excludes_closed_portfolio(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create();

        $active = Portfolio::factory()->for($user)->create(['name' => 'Active Brokerage']);
        Transaction::factory()->for($active)->for($asset)->buy()->create(['quantity' => 1, 'price_per_unit' => 100]);

        $closed = Portfolio::factory()->for($user)->create(['name' => 'Old 401k', 'closed_at' => now()]);
        Transaction::factory()->for($closed)->for($asset)->buy()->create(['quantity' => 5, 'price_per_unit' => 100]);

        $names = $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->viewData('summaries')->pluck('portfolio.name');

        $this->assertContains('Active Brokerage', $names);
        $this->assertNotContains('Old 401k', $names);
    }

    public function test_index_separates_active_and_closed(): void
    {
        $user   = User::factory()->create();
        $active = Portfolio::factory()->for($user)->create(['name' => 'Active One']);
        $closed = Portfolio::factory()->for($user)->create(['name' => 'Closed One', 'closed_at' => now()]);

        $response = $this->actingAs($user)->get(route('portfolios.index'))->assertOk();

        $this->assertTrue($response->viewData('portfolios')->contains('id', $active->id));
        $this->assertFalse($response->viewData('portfolios')->contains('id', $closed->id));
        $this->assertTrue($response->viewData('closedPortfolios')->contains('id', $closed->id));
    }

    public function test_cannot_add_transaction_to_closed_portfolio(): void
    {
        $portfolio = Portfolio::factory()->create(['closed_at' => now()]);

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.store', $portfolio), [
                'symbol'         => 'AAPL',
                'asset_type'     => 'stock',
                'type'           => 'buy',
                'quantity'       => 1,
                'price_per_unit' => 150,
                'currency'       => 'USD',
                'transacted_at'  => '2026-05-01',
            ])
            ->assertRedirect(route('portfolios.show', $portfolio))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_transaction_create_page_redirects_when_closed(): void
    {
        $portfolio = Portfolio::factory()->create(['closed_at' => now()]);

        $this->actingAs($portfolio->user)
            ->get(route('portfolios.transactions.create', $portfolio))
            ->assertRedirect(route('portfolios.show', $portfolio));
    }

    public function test_cannot_add_manual_asset_to_closed_portfolio(): void
    {
        $portfolio = Portfolio::factory()->create(['closed_at' => now()]);

        $this->actingAs($portfolio->user)
            ->get(route('portfolios.manual-assets.create', $portfolio))
            ->assertRedirect(route('portfolios.show', $portfolio));

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'        => 'Gold Bar',
                'asset_class' => 'other',
            ])
            ->assertRedirect(route('portfolios.show', $portfolio))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('manual_assets', 0);
    }

    public function test_closed_portfolio_still_appears_in_all_transactions(): void
    {
        $user      = User::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'NVDA']);
        $portfolio = Portfolio::factory()->for($user)->create(['name' => 'Archived', 'closed_at' => now()]);
        Transaction::factory()->for($portfolio)->for($asset)->buy()->create(['quantity' => 1, 'price_per_unit' => 100]);

        $this->actingAs($user)
            ->get(route('transactions.all'))
            ->assertOk()
            ->assertSee('NVDA');
    }
}
