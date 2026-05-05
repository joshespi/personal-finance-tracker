<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class BondAssetTypeTest extends TestCase
{
    public function test_can_create_transaction_with_bond_asset_type(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.store', $portfolio), [
                'symbol'         => 'AGG',
                'asset_type'     => 'bond',
                'type'           => 'buy',
                'quantity'       => 10,
                'price_per_unit' => 95,
                'currency'       => 'USD',
                'transacted_at'  => '2026-05-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['symbol' => 'AGG', 'asset_type' => 'bond']);
    }

    public function test_can_reclassify_asset_to_bond(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'bond'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('bond', $asset->fresh()->asset_type);
    }

    public function test_can_add_bond_to_watchlist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('watchlist.store'), [
                'symbol'     => 'AGG',
                'asset_type' => 'bond',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('watchlist_items', [
            'user_id'    => $user->id,
            'symbol'     => 'AGG',
            'asset_type' => 'bond',
        ]);
    }

    public function test_dashboard_allocation_buckets_bonds_separately(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $agg       = Asset::factory()->bond()->create(['symbol' => 'AGG']);

        Transaction::factory()->for($portfolio)->for($agg)->create([
            'type'           => 'buy',
            'quantity'       => 10,
            'price_per_unit' => 95,
            'transacted_at'  => '2026-05-01',
        ]);

        AssetPrice::factory()->for($agg)->create(['price' => 95]);

        $response = $this->actingAs($user)->get(route('dashboard'));
        $response->assertOk();

        $allocation = $response->viewData('allocation');
        $bondIdx    = array_search('Bonds', $allocation['labels']);
        $stockIdx   = array_search('Stocks', $allocation['labels']);
        $this->assertSame(950.0, $allocation['values'][$bondIdx]);
        $this->assertSame(0.0, $allocation['values'][$stockIdx]);
    }

    public function test_portfolio_can_save_bond_target_pct(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->put(route('portfolios.update', $portfolio), [
                'name'                   => $portfolio->name,
                'currency'               => $portfolio->currency,
                'target_stock_pct'       => 60,
                'target_crypto_pct'      => 10,
                'target_real_estate_pct' => 10,
                'target_bond_pct'        => 20,
                'target_manual_pct'      => 0,
            ])
            ->assertRedirect();

        $this->assertSame(20, $portfolio->fresh()->target_bond_pct);
    }

    public function test_rebalancing_includes_bond_row(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create([
            'target_stock_pct' => 70,
            'target_bond_pct'  => 30,
        ]);

        $stock = Asset::factory()->create(['symbol' => 'AAPL', 'asset_type' => 'stock']);
        Transaction::factory()->for($portfolio)->for($stock)->create([
            'type' => 'buy', 'quantity' => 10, 'price_per_unit' => 100, 'transacted_at' => '2026-05-01',
        ]);

        $agg = Asset::factory()->bond()->create(['symbol' => 'AGG']);
        Transaction::factory()->for($portfolio)->for($agg)->create([
            'type' => 'buy', 'quantity' => 10, 'price_per_unit' => 95, 'transacted_at' => '2026-05-01',
        ]);

        $this->actingAs($user)
            ->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('Bonds');
    }
}
