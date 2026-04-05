<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioHoldingsTest extends TestCase
{
    use RefreshDatabase;

    private function makePortfolio(): Portfolio
    {
        $user = User::factory()->create();
        return Portfolio::create(['user_id' => $user->id, 'name' => 'Test', 'currency' => 'USD']);
    }

    private function makeAsset(string $symbol, ?float $price = null): Asset
    {
        $asset = Asset::create(['symbol' => $symbol, 'name' => $symbol, 'asset_type' => 'crypto']);
        if ($price !== null) {
            AssetPrice::create([
                'asset_id'    => $asset->id,
                'price'       => $price,
                'currency'    => 'USD',
                'recorded_at' => now(),
            ]);
        }
        return $asset;
    }

    private function addTx(Portfolio $portfolio, Asset $asset, string $type, float $qty, float $price, float $fees = 0, string $date = '2024-01-01'): void
    {
        Transaction::create([
            'portfolio_id'   => $portfolio->id,
            'asset_id'       => $asset->id,
            'type'           => $type,
            'quantity'       => $qty,
            'price_per_unit' => $price,
            'fees'           => $fees,
            'currency'       => 'USD',
            'transacted_at'  => $date,
        ]);
    }

    public function test_single_buy_holding(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('BTC', 50000.0);

        $this->addTx($portfolio, $asset, 'buy', 1.0, 40000.0, 10.0);

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $this->assertCount(1, $holdings);
        $h = $holdings->first();

        $this->assertEquals(1.0, $h['quantity']);
        $this->assertEquals(40010.0, $h['total_cost']);   // price + fees
        $this->assertEquals(50000.0, $h['current_value']);
        $this->assertEquals(9990.0, $h['unrealized_gain']); // 50000 - 40010
    }

    public function test_buy_and_partial_sell(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('ETH', 3000.0);

        $this->addTx($portfolio, $asset, 'buy',  2.0, 2000.0, 0, '2024-01-01');
        $this->addTx($portfolio, $asset, 'sell', 1.0, 2500.0, 0, '2024-06-01');

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $h = $holdings->first();
        $this->assertEquals(1.0, $h['quantity']);
        $this->assertEquals(2000.0, $h['total_cost']);  // half of original 4000
        $this->assertEquals(3000.0, $h['current_value']);
        $this->assertEquals(1000.0, $h['unrealized_gain']);
    }

    public function test_full_sell_removes_holding(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('DOGE');

        $this->addTx($portfolio, $asset, 'buy',  100.0, 0.1, 0, '2024-01-01');
        $this->addTx($portfolio, $asset, 'sell', 100.0, 0.2, 0, '2024-06-01');

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $this->assertCount(0, $holdings);
    }

    public function test_staking_reward_adds_to_holdings(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('SOL', 200.0);

        $this->addTx($portfolio, $asset, 'buy',            10.0, 100.0, 0, '2024-01-01');
        $this->addTx($portfolio, $asset, 'staking_reward',  1.0,   0.0, 0, '2024-06-01');

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $h = $holdings->first();
        $this->assertEquals(11.0, $h['quantity']);
        $this->assertEquals(2200.0, $h['current_value']); // 11 * 200
    }

    public function test_transfer_in_and_out(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('ADA', 1.0);

        $this->addTx($portfolio, $asset, 'transfer_in',  500.0, 0.5, 0, '2024-01-01');
        $this->addTx($portfolio, $asset, 'transfer_out', 200.0, 0.5, 0, '2024-06-01');

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $h = $holdings->first();
        $this->assertEquals(300.0, $h['quantity']);
    }

    public function test_holding_without_price_has_null_current_value(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('UNKNOWN'); // no price

        $this->addTx($portfolio, $asset, 'buy', 5.0, 10.0);

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $h = $holdings->first();
        $this->assertEquals(5.0, $h['quantity']);
        $this->assertNull($h['current_value']);
        $this->assertNull($h['unrealized_gain']);
    }

    public function test_multiple_buys_average_cost(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('BTC', 60000.0);

        $this->addTx($portfolio, $asset, 'buy', 1.0, 30000.0, 0, '2024-01-01');
        $this->addTx($portfolio, $asset, 'buy', 1.0, 50000.0, 0, '2024-06-01');

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $h = $holdings->first();
        $this->assertEquals(2.0, $h['quantity']);
        $this->assertEquals(80000.0, $h['total_cost']);
        $this->assertEquals(40000.0, $h['avg_cost']);
        $this->assertEquals(120000.0, $h['current_value']);
        $this->assertEquals(40000.0, $h['unrealized_gain']);
    }

    public function test_fees_included_in_cost_basis(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('ETH');

        $this->addTx($portfolio, $asset, 'buy', 2.0, 1000.0, 50.0); // 2*1000 + 50 = 2050

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $h = $holdings->first();
        $this->assertEquals(2050.0, $h['total_cost']);
        $this->assertEquals(1025.0, $h['avg_cost']);
    }

    public function test_unrealized_pct_calculation(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = $this->makeAsset('BTC', 60000.0);

        $this->addTx($portfolio, $asset, 'buy', 1.0, 40000.0);

        $portfolio->load('transactions.asset.latestPrice', 'manualAssets.latestValuation');
        $holdings = $portfolio->computeHoldings();

        $h = $holdings->first();
        // gain = 20000, cost = 40000, pct = 50%
        $this->assertEquals(50.0, $h['unrealized_pct']);
    }
}
