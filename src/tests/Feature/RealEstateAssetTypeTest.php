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
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create();
        Transaction::factory()->create(['portfolio_id' => $portfolio->id, 'asset_id' => $asset->id]);

        $this->actingAs($portfolio->user)
            ->patch(route('assets.reclassify', $asset), ['asset_type' => 'real_estate'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('user_asset_classifications', [
            'user_id'    => $portfolio->user_id,
            'asset_id'   => $asset->id,
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

    public function test_real_estate_manual_asset_rolls_into_real_estate_allocation_bucket(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create(['asset_class' => 'real_estate']);
        ManualValuation::factory()->for($asset)->create(['value' => 500000]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $allocation = $response->viewData('allocation');
        $reIdx      = array_search('Real Estate', $allocation['labels']);
        $otherIdx   = array_search('Other Assets', $allocation['labels']);
        $this->assertSame(500000.0, $allocation['values'][$reIdx]);
        $this->assertSame(0.0, $allocation['values'][$otherIdx]);
    }

    public function test_non_real_estate_manual_asset_stays_in_other_assets_bucket(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = ManualAsset::factory()->for($portfolio)->create(['asset_class' => 'vehicle']);
        ManualValuation::factory()->for($asset)->create(['value' => 25000]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $allocation = $response->viewData('allocation');
        $reIdx      = array_search('Real Estate', $allocation['labels']);
        $otherIdx   = array_search('Other Assets', $allocation['labels']);
        $this->assertSame(0.0, $allocation['values'][$reIdx]);
        $this->assertSame(25000.0, $allocation['values'][$otherIdx]);
    }
}
