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
}
