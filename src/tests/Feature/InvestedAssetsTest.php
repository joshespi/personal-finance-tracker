<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\ManualAsset;
use App\Models\ManualValuation;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Tests\TestCase;

class InvestedAssetsTest extends TestCase
{
    public function test_excluded_manual_asset_drops_from_invested_but_stays_in_net_worth(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        $stock = Asset::factory()->stock()->create();
        Transaction::factory()->for($portfolio)->for($stock)->buy()->create(['quantity' => 10, 'price_per_unit' => 100]);
        AssetPrice::factory()->for($stock)->create(['price' => 100]);

        $house = ManualAsset::factory()->for($portfolio)->create([
            'asset_class'         => 'real_estate',
            'include_in_chart'    => true,
            'include_in_invested' => false,
        ]);
        ManualValuation::factory()->for($house)->create(['value' => 400000]);

        $totals = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('totals');

        $this->assertEqualsWithDelta(1000.0, $totals['invested_value'], 0.01);
        $this->assertEqualsWithDelta(401000.0, $totals['portfolio_value'], 0.01);
        $this->assertEqualsWithDelta(401000.0, $totals['net_worth'], 0.01);
    }

    public function test_manual_asset_counts_as_invested_by_default(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        $reit = ManualAsset::factory()->for($portfolio)->create([
            'asset_class'      => 'real_estate',
            'include_in_chart' => true,
        ]);
        ManualValuation::factory()->for($reit)->create(['value' => 50000]);

        $totals = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('totals');

        $this->assertEqualsWithDelta($totals['portfolio_value'], $totals['invested_value'], 0.01);
    }

    public function test_asset_excluded_from_chart_is_not_double_subtracted_from_invested(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        // Off-chart asset is already absent from portfolio_value; excluding from invested too must be a no-op.
        $house = ManualAsset::factory()->for($portfolio)->create([
            'asset_class'         => 'real_estate',
            'include_in_chart'    => false,
            'include_in_invested' => false,
        ]);
        ManualValuation::factory()->for($house)->create(['value' => 400000]);

        $totals = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('totals');

        $this->assertEqualsWithDelta($totals['portfolio_value'], $totals['invested_value'], 0.01);
    }

    public function test_store_persists_include_in_invested_false(): void
    {
        $portfolio = Portfolio::factory()->create();

        $this->actingAs($portfolio->user)
            ->post(route('portfolios.manual-assets.store', $portfolio), [
                'name'                => 'Primary Home',
                'asset_class'         => 'real_estate',
                'currency'            => 'USD',
                'include_in_chart'    => '1',
                'include_in_invested' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($portfolio->manualAssets()->firstWhere('name', 'Primary Home')->include_in_invested);
    }
}
