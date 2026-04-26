<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use Tests\TestCase;

class PortfolioHoldingsTest extends TestCase
{
    private function pricedAsset(string $symbol, ?float $price = null): Asset
    {
        $asset = Asset::factory()->crypto()->create(['symbol' => $symbol, 'name' => $symbol]);
        if ($price !== null) {
            AssetPrice::factory()->for($asset)->create(['price' => $price]);
        }

        return $asset;
    }

    private function loadHoldings(Portfolio $portfolio)
    {
        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');

        return $portfolio->computeHoldings();
    }

    public function test_single_buy_holding(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('BTC', 50000.0);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 1.0, 'price_per_unit' => 40000.0, 'fees' => 10.0]);

        $holdings = $this->loadHoldings($portfolio);
        $h        = $holdings->first();

        $this->assertCount(1, $holdings);
        $this->assertEquals(1.0, $h['quantity']);
        $this->assertEquals(40010.0, $h['total_cost']);
        $this->assertEquals(50000.0, $h['current_value']);
        $this->assertEquals(9990.0, $h['unrealized_gain']);
    }

    public function test_buy_and_partial_sell(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('ETH', 3000.0);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 2.0, 'price_per_unit' => 2000.0, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 1.0, 'price_per_unit' => 2500.0, 'transacted_at' => '2024-06-01']);

        $h = $this->loadHoldings($portfolio)->first();

        $this->assertEquals(1.0, $h['quantity']);
        $this->assertEquals(2000.0, $h['total_cost']);
        $this->assertEquals(3000.0, $h['current_value']);
        $this->assertEquals(1000.0, $h['unrealized_gain']);
    }

    public function test_full_sell_removes_holding(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('DOGE');

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 100.0, 'price_per_unit' => 0.1, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 100.0, 'price_per_unit' => 0.2, 'transacted_at' => '2024-06-01']);

        $this->assertCount(0, $this->loadHoldings($portfolio));
    }

    public function test_staking_reward_adds_to_holdings(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('SOL', 200.0);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 10.0, 'price_per_unit' => 100.0, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)
            ->create(['type' => 'staking_reward', 'quantity' => 1.0, 'price_per_unit' => 0.0, 'transacted_at' => '2024-06-01']);

        $h = $this->loadHoldings($portfolio)->first();

        $this->assertEquals(11.0, $h['quantity']);
        $this->assertEquals(2200.0, $h['current_value']);
    }

    public function test_transfer_in_and_out(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('ADA', 1.0);

        Transaction::factory()->for($portfolio)->for($asset)
            ->create(['type' => 'transfer_in', 'quantity' => 500.0, 'price_per_unit' => 0.5, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)
            ->create(['type' => 'transfer_out', 'quantity' => 200.0, 'price_per_unit' => 0.5, 'transacted_at' => '2024-06-01']);

        $h = $this->loadHoldings($portfolio)->first();
        $this->assertEquals(300.0, $h['quantity']);
    }

    public function test_holding_without_price_has_null_current_value(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('UNKNOWN');

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 5.0, 'price_per_unit' => 10.0]);

        $h = $this->loadHoldings($portfolio)->first();

        $this->assertEquals(5.0, $h['quantity']);
        $this->assertNull($h['current_value']);
        $this->assertNull($h['unrealized_gain']);
    }

    public function test_multiple_buys_average_cost(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('BTC', 60000.0);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 1.0, 'price_per_unit' => 30000.0, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 1.0, 'price_per_unit' => 50000.0, 'transacted_at' => '2024-06-01']);

        $h = $this->loadHoldings($portfolio)->first();

        $this->assertEquals(2.0, $h['quantity']);
        $this->assertEquals(80000.0, $h['total_cost']);
        $this->assertEquals(40000.0, $h['avg_cost']);
        $this->assertEquals(120000.0, $h['current_value']);
        $this->assertEquals(40000.0, $h['unrealized_gain']);
    }

    public function test_fees_included_in_cost_basis(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('ETH');

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 2.0, 'price_per_unit' => 1000.0, 'fees' => 50.0]);

        $h = $this->loadHoldings($portfolio)->first();

        $this->assertEquals(2050.0, $h['total_cost']);
        $this->assertEquals(1025.0, $h['avg_cost']);
    }

    public function test_unrealized_pct_calculation(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = $this->pricedAsset('BTC', 60000.0);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 1.0, 'price_per_unit' => 40000.0]);

        $h = $this->loadHoldings($portfolio)->first();
        $this->assertEquals(50.0, $h['unrealized_pct']);
    }
}
