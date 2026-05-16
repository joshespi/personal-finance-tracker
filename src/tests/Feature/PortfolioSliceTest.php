<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\PortfolioSlice;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class PortfolioSliceTest extends TestCase
{
    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_store_requires_auth(): void
    {
        $portfolio = Portfolio::factory()->create();
        $this->post(route('portfolios.slices.store', $portfolio), [])
            ->assertRedirect(route('login'));
    }

    // ── Store ─────────────────────────────────────────────────────────────────

    public function test_owner_can_add_slice(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'VOO']);

        $this->actingAs($user)
            ->post(route('portfolios.slices.store', $portfolio), [
                'symbol'     => 'VOO',
                'target_pct' => 80,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('portfolio_slices', [
            'portfolio_id' => $portfolio->id,
            'asset_id'     => $asset->id,
            'target_pct'   => 80,
        ]);
    }

    public function test_store_upserts_existing_slice(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'VOO']);

        PortfolioSlice::create(['portfolio_id' => $portfolio->id, 'asset_id' => $asset->id, 'target_pct' => 50]);

        $this->actingAs($user)
            ->post(route('portfolios.slices.store', $portfolio), [
                'symbol'     => 'VOO',
                'target_pct' => 80,
            ]);

        $this->assertDatabaseCount('portfolio_slices', 1);
        $this->assertDatabaseHas('portfolio_slices', ['asset_id' => $asset->id, 'target_pct' => 80]);
    }

    public function test_store_rejects_unknown_symbol(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('portfolios.slices.store', $portfolio), [
                'symbol'     => 'FAKEXYZ',
                'target_pct' => 50,
            ])
            ->assertSessionHasErrors('symbol');
    }

    public function test_non_owner_cannot_add_slice(): void
    {
        $portfolio = Portfolio::factory()->create();
        Asset::factory()->stock()->create(['symbol' => 'VOO']);

        $this->actingAs(User::factory()->create())
            ->post(route('portfolios.slices.store', $portfolio), [
                'symbol'     => 'VOO',
                'target_pct' => 50,
            ])
            ->assertForbidden();
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function test_owner_can_remove_slice(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();
        $slice     = PortfolioSlice::create(['portfolio_id' => $portfolio->id, 'asset_id' => $asset->id, 'target_pct' => 50]);

        $this->actingAs($user)
            ->delete(route('portfolios.slices.destroy', [$portfolio, $slice]))
            ->assertRedirect();

        $this->assertDatabaseMissing('portfolio_slices', ['id' => $slice->id]);
    }

    public function test_non_owner_cannot_remove_slice(): void
    {
        $owner     = User::factory()->create();
        $portfolio = Portfolio::factory()->for($owner)->create();
        $asset     = Asset::factory()->stock()->create();
        $slice     = PortfolioSlice::create(['portfolio_id' => $portfolio->id, 'asset_id' => $asset->id, 'target_pct' => 50]);

        $this->actingAs(User::factory()->create())
            ->delete(route('portfolios.slices.destroy', [$portfolio, $slice]))
            ->assertForbidden();
    }

    // ── Rebalancing on show page ───────────────────────────────────────────────

    public function test_portfolio_show_displays_slice_rebalancing(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'VOO']);
        AssetPrice::factory()->for($asset)->create(['price' => 500, 'recorded_at' => now()]);
        Transaction::factory()->buy()->for($portfolio)->for($asset)->create([
            'quantity'       => 10,
            'price_per_unit' => 490,
            'fees'           => 0,
        ]);
        PortfolioSlice::create(['portfolio_id' => $portfolio->id, 'asset_id' => $asset->id, 'target_pct' => 80]);

        $this->actingAs($user)
            ->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('VOO')
            ->assertSee('80');
    }
}
