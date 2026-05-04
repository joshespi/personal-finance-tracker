<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class RealEstateAssetTypeTest extends TestCase
{
    public function test_can_create_transaction_with_real_estate_asset_type(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.store', $portfolio), [
                'symbol'         => 'VNQ',
                'asset_type'     => 'real_estate',
                'type'           => 'buy',
                'quantity'       => 10,
                'price_per_unit' => 90,
                'currency'       => 'USD',
                'transacted_at'  => '2026-05-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['symbol' => 'VNQ', 'asset_type' => 'real_estate']);
    }

    public function test_can_reclassify_asset_to_real_estate(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->stock()->create();

        $this->actingAs($user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'real_estate'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('real_estate', $asset->fresh()->asset_type);
    }

    public function test_can_add_real_estate_to_watchlist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('watchlist.store'), [
                'symbol'     => 'VNQ',
                'asset_type' => 'real_estate',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('watchlist_items', [
            'user_id'    => $user->id,
            'symbol'     => 'VNQ',
            'asset_type' => 'real_estate',
        ]);
    }

    public function test_invalid_asset_type_still_rejected(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.transactions.store', $portfolio), [
                'symbol'         => 'VNQ',
                'asset_type'     => 'commodity',
                'type'           => 'buy',
                'quantity'       => 10,
                'price_per_unit' => 90,
                'currency'       => 'USD',
                'transacted_at'  => '2026-05-01',
            ])
            ->assertSessionHasErrors('asset_type');
    }

    public function test_dashboard_allocation_buckets_real_estate_separately(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $vnq       = Asset::factory()->create(['symbol' => 'VNQ', 'asset_type' => 'real_estate']);

        Transaction::factory()->for($portfolio)->for($vnq)->create([
            'type'           => 'buy',
            'quantity'       => 10,
            'price_per_unit' => 90,
            'transacted_at'  => '2026-05-01',
        ]);

        // Price is required so allocation['total'] > 0 and the legend/chart block renders.
        // Without this the @if block is skipped and color-array bugs hide in tests.
        AssetPrice::factory()->for($vnq)->create(['price' => 90]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Real Estate');
    }

    public function test_portfolio_can_save_real_estate_target_pct(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->put(route('portfolios.update', $portfolio), [
                'name'                   => $portfolio->name,
                'currency'               => $portfolio->currency,
                'target_stock_pct'       => 60,
                'target_crypto_pct'      => 10,
                'target_real_estate_pct' => 20,
                'target_manual_pct'      => 10,
            ])
            ->assertRedirect();

        $this->assertSame(20, $portfolio->fresh()->target_real_estate_pct);
    }

    public function test_rebalancing_includes_real_estate_row(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create([
            'target_stock_pct'       => 70,
            'target_real_estate_pct' => 30,
        ]);

        $stock = Asset::factory()->create(['symbol' => 'AAPL', 'asset_type' => 'stock']);
        Transaction::factory()->for($portfolio)->for($stock)->create([
            'type' => 'buy', 'quantity' => 10, 'price_per_unit' => 100, 'transacted_at' => '2026-05-01',
        ]);

        $vnq = Asset::factory()->create(['symbol' => 'VNQ', 'asset_type' => 'real_estate']);
        Transaction::factory()->for($portfolio)->for($vnq)->create([
            'type' => 'buy', 'quantity' => 10, 'price_per_unit' => 90, 'transacted_at' => '2026-05-01',
        ]);

        $this->actingAs($user)
            ->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('Real Estate');
    }
}
