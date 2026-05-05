<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\ManualAsset;
use App\Models\ManualValuation;
use App\Models\Portfolio;
use Tests\TestCase;

class ManualAssetTest extends TestCase
{
    public function test_create_with_cost_basis(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'        => '123 Main St',
                'asset_class' => 'real_estate',
                'cost_basis'  => 200000,
                'currency'    => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'name'       => '123 Main St',
            'cost_basis' => 200000,
        ]);
    }

    public function test_cost_basis_is_optional(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'        => 'Watch',
                'asset_class' => 'collectible',
                'currency'    => 'USD',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'name'       => 'Watch',
            'cost_basis' => null,
        ]);
    }

    public function test_cost_basis_rejects_negative(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'        => 'Bad',
                'asset_class' => 'other',
                'cost_basis'  => -1,
                'currency'    => 'USD',
            ])
            ->assertSessionHasErrors('cost_basis');
    }

    public function test_update_cost_basis(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => null]);

        $this->actingAs($asset->portfolio->user)
            ->put(route('manual-assets.update', $asset), [
                'name'        => $asset->name,
                'asset_class' => $asset->asset_class,
                'cost_basis'  => 175000,
                'currency'    => $asset->currency,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'id'         => $asset->id,
            'cost_basis' => 175000,
        ]);
    }

    public function test_profit_loss_returns_null_without_cost_basis(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => null]);
        ManualValuation::factory()->for($asset)->create(['value' => 250000]);

        $this->assertNull($asset->fresh()->profitLoss());
    }

    public function test_profit_loss_returns_null_without_valuation(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => 200000]);

        $this->assertNull($asset->profitLoss());
    }

    public function test_profit_loss_computes_difference(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => 200000]);
        ManualValuation::factory()->for($asset)->create(['value' => 250000]);

        $this->assertSame(50000.0, $asset->fresh()->profitLoss());
    }

    public function test_show_page_displays_linked_liability_and_equity(): void
    {
        $asset     = ManualAsset::factory()->create(['asset_class' => 'real_estate']);
        $liability = Liability::factory()->for($asset->portfolio->user)->create([
            'name'            => 'Home Mortgage',
            'liability_type'  => 'mortgage',
            'manual_asset_id' => $asset->id,
            'currency'        => 'USD',
        ]);
        LiabilityBalance::factory()->for($liability)->create(['balance' => 300000]);
        ManualValuation::factory()->for($asset)->create(['value' => 500000]);

        $this->actingAs($asset->portfolio->user)
            ->get(route('manual-assets.show', $asset))
            ->assertOk()
            ->assertSee('Home Mortgage')
            ->assertSee('300,000.00')
            ->assertSee('Equity')
            ->assertSee('200,000.00');
    }

    public function test_show_page_hides_liability_section_when_none_linked(): void
    {
        $asset = ManualAsset::factory()->create();

        $this->actingAs($asset->portfolio->user)
            ->get(route('manual-assets.show', $asset))
            ->assertOk()
            ->assertDontSee('Linked Liabilities');
    }

    public function test_show_page_displays_profit_loss(): void
    {
        $asset = ManualAsset::factory()->create(['cost_basis' => 200000]);
        ManualValuation::factory()->for($asset)->create(['value' => 250000]);

        $this->actingAs($asset->portfolio->user)
            ->get(route('manual-assets.show', $asset))
            ->assertOk()
            ->assertSee('Profit / Loss')
            ->assertSee('+50,000.00');
    }

    public function test_can_create_proxy_tracked_asset(): void
    {
        $portfolio  = Portfolio::factory()->create();
        $proxyAsset = Asset::factory()->create(['symbol' => 'IWB']);
        AssetPrice::factory()->for($proxyAsset)->create(['price' => 100.00, 'recorded_at' => '2026-01-01 00:00:00']);

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'            => 'My 401k',
                'asset_class'     => 'other',
                'currency'        => 'USD',
                'tracking_method' => 'proxy_ticker',
                'proxy_symbol'    => 'IWB',
                'anchor_value'    => 50000,
                'anchor_date'     => '2026-01-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'name'            => 'My 401k',
            'tracking_method' => 'proxy_ticker',
            'proxy_asset_id'  => $proxyAsset->id,
            'anchor_value'    => 50000,
        ]);
    }

    public function test_current_value_computed_from_proxy_price(): void
    {
        $proxyAsset = Asset::factory()->create(['symbol' => 'IWB']);
        $asset      = ManualAsset::factory()->proxyTracked($proxyAsset, 50000.0, '2026-01-01', 500.0)->create();
        AssetPrice::factory()->for($proxyAsset)->create(['price' => 110.00, 'recorded_at' => now()]);

        $asset->load('proxyAsset.latestPrice');

        $this->assertSame(55000.0, $asset->currentValue());
    }

    public function test_current_value_falls_back_to_anchor_when_no_proxy_price(): void
    {
        $proxyAsset = Asset::factory()->create(['symbol' => 'IWB']);
        $asset      = ManualAsset::factory()->proxyTracked($proxyAsset, 50000.0, '2026-01-01', 500.0)->create();

        $asset->load('proxyAsset.latestPrice');

        $this->assertSame(50000.0, $asset->currentValue());
    }

    public function test_proxy_asset_auto_created_when_symbol_unknown(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'            => 'My 401k',
                'asset_class'     => 'other',
                'currency'        => 'USD',
                'tracking_method' => 'proxy_ticker',
                'proxy_symbol'    => 'NEWPRXY',
                'anchor_value'    => 20000,
                'anchor_date'     => '2026-01-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['symbol' => 'NEWPRXY', 'asset_type' => 'stock']);
    }

    public function test_proxy_tracking_fields_required_when_method_is_proxy_ticker(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'            => 'My 401k',
                'asset_class'     => 'other',
                'currency'        => 'USD',
                'tracking_method' => 'proxy_ticker',
            ])
            ->assertSessionHasErrors(['proxy_symbol', 'anchor_value', 'anchor_date']);
    }
}
