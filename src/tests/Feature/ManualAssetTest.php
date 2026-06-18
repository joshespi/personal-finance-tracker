<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Liability;
use App\Models\LiabilityBalance;
use App\Models\ManualAsset;
use App\Models\ManualValuation;
use App\Models\Portfolio;
use App\Models\User;
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

    public function test_can_add_valuation(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create();

        $this->actingAs($portfolio->user)
            ->post(route('manual-assets.valuations.store', $asset), [
                'value'     => 425000,
                'valued_at' => now()->toDateString(),
                'notes'     => 'Zillow estimate',
            ])
            ->assertRedirect(route('manual-assets.show', $asset));

        $this->assertDatabaseHas('manual_valuations', [
            'manual_asset_id' => $asset->id,
            'value'           => 425000,
        ]);
    }

    public function test_cannot_add_valuation_to_another_users_asset(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create();
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->post(route('manual-assets.valuations.store', $asset), [
                'value'     => 100000,
                'valued_at' => now()->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_valuation_validates_non_negative_value(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create();

        $this->actingAs($portfolio->user)
            ->post(route('manual-assets.valuations.store', $asset), [
                'value'     => -1000,
                'valued_at' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('value');
    }

    public function test_owner_can_delete_valuation(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create();
        $valuation = ManualValuation::factory()->for($asset)->create();

        $this->actingAs($portfolio->user)
            ->delete(route('valuations.destroy', $valuation))
            ->assertRedirect(route('manual-assets.show', $asset->id));

        $this->assertDatabaseMissing('manual_valuations', ['id' => $valuation->id]);
    }

    public function test_non_owner_cannot_delete_valuation(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create();
        $valuation = ManualValuation::factory()->for($asset)->create();
        $other     = User::factory()->create();

        $this->actingAs($other)
            ->delete(route('valuations.destroy', $valuation))
            ->assertForbidden();
    }

    public function test_include_in_chart_stored_as_true_when_checked(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'             => 'Card Collection',
                'asset_class'      => 'other',
                'currency'         => 'USD',
                'include_in_chart' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'name'             => 'Card Collection',
            'include_in_chart' => true,
        ]);
    }

    public function test_include_in_chart_stored_as_false_when_unchecked(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'             => 'Primary Residence',
                'asset_class'      => 'real_estate',
                'currency'         => 'USD',
                'include_in_chart' => '0',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('manual_assets', [
            'name'             => 'Primary Residence',
            'include_in_chart' => false,
        ]);
    }

    public function test_excluded_manual_asset_omitted_from_portfolio_chart_value(): void
    {
        $portfolio = Portfolio::factory()->create();
        ManualAsset::factory()->for($portfolio)->create(['include_in_chart' => true]);
        ManualAsset::factory()->for($portfolio)->create(['include_in_chart' => false]);

        $portfolio->load(['manualAssets.latestValuation', 'manualAssets.proxyAsset.latestPrice']);

        $includedCount = $portfolio->manualAssets->where('include_in_chart', true)->count();
        $this->assertEquals(1, $includedCount);
    }
}
