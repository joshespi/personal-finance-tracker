<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use App\Services\RealizedGainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RealizedGainTest extends TestCase
{
    use RefreshDatabase;

    private function makePortfolio(): Portfolio
    {
        $user = User::factory()->create();
        return Portfolio::create(['user_id' => $user->id, 'name' => 'Test', 'currency' => 'USD']);
    }

    private function makeTxn(Portfolio $portfolio, Asset $asset, string $type, float $qty, float $price, string $date, float $fees = 0): void
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

    public function test_simple_fifo_gain(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = Asset::create(['symbol' => 'BTC', 'name' => 'Bitcoin', 'asset_type' => 'crypto']);

        $this->makeTxn($portfolio, $asset, 'buy',  1.0, 40000, '2024-01-01');
        $this->makeTxn($portfolio, $asset, 'sell', 1.0, 50000, '2024-06-01');

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertCount(1, $result['lots']);
        $this->assertEquals(10000.00, $result['totalGain']);
    }

    public function test_fifo_partial_sell(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = Asset::create(['symbol' => 'ETH', 'name' => 'Ethereum', 'asset_type' => 'crypto']);

        $this->makeTxn($portfolio, $asset, 'buy',  2.0, 2000, '2024-01-01');
        $this->makeTxn($portfolio, $asset, 'sell', 1.0, 3000, '2024-06-01');

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertCount(1, $result['lots']);
        $this->assertEquals(1000.00, $result['totalGain']);
    }

    public function test_fifo_multiple_lots(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = Asset::create(['symbol' => 'AAPL', 'name' => 'Apple', 'asset_type' => 'stock']);

        $this->makeTxn($portfolio, $asset, 'buy',  10, 100, '2024-01-01');
        $this->makeTxn($portfolio, $asset, 'buy',  10, 120, '2024-02-01');
        $this->makeTxn($portfolio, $asset, 'sell', 15, 150, '2024-07-01');

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        // First lot: 10 shares at 100 → sold at 150 = +500
        // Second lot: 5 shares at 120 → sold at 150 = +150
        $this->assertEquals(650.00, $result['totalGain']);
        $this->assertCount(2, $result['lots']);
    }

    public function test_realized_loss(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = Asset::create(['symbol' => 'GME', 'name' => 'GameStop', 'asset_type' => 'stock']);

        $this->makeTxn($portfolio, $asset, 'buy',  100, 300, '2021-01-01');
        $this->makeTxn($portfolio, $asset, 'sell', 100, 50,  '2021-03-01');

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertEquals(-25000.00, $result['totalGain']);
    }

    public function test_by_year_grouping(): void
    {
        $portfolio = $this->makePortfolio();
        $asset     = Asset::create(['symbol' => 'TSLA', 'name' => 'Tesla', 'asset_type' => 'stock']);

        $this->makeTxn($portfolio, $asset, 'buy',  1, 200, '2022-01-01');
        $this->makeTxn($portfolio, $asset, 'sell', 1, 300, '2022-06-01');
        $this->makeTxn($portfolio, $asset, 'buy',  1, 100, '2023-01-01');
        $this->makeTxn($portfolio, $asset, 'sell', 1, 150, '2023-09-01');

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertArrayHasKey(2022, $result['byYear']);
        $this->assertArrayHasKey(2023, $result['byYear']);
        $this->assertEquals(100.00, $result['byYear'][2022]);
        $this->assertEquals(50.00,  $result['byYear'][2023]);
    }

    public function test_portfolio_show_renders_realized_gains_section(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::create(['user_id' => $user->id, 'name' => 'Test', 'currency' => 'USD']);
        $asset     = Asset::create(['symbol' => 'NVDA', 'name' => 'Nvidia', 'asset_type' => 'stock']);

        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id, 'type' => 'buy',
            'quantity' => 10, 'price_per_unit' => 400, 'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2023-01-01',
        ]);
        Transaction::create([
            'portfolio_id' => $portfolio->id, 'asset_id' => $asset->id, 'type' => 'sell',
            'quantity' => 10, 'price_per_unit' => 600, 'fees' => 0, 'currency' => 'USD', 'transacted_at' => '2023-12-01',
        ]);

        $this->actingAs($user)->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('Realized Gains')
            ->assertSee('2,000.00');
    }
}
