<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Services\RealizedGainService;
use Tests\TestCase;

class RealizedGainTest extends TestCase
{
    public function test_simple_fifo_gain(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->crypto()->create(['symbol' => 'BTC']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 1.0, 'price_per_unit' => 40000, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 1.0, 'price_per_unit' => 50000, 'transacted_at' => '2024-06-01']);

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertCount(1, $result['lots']);
        $this->assertEquals(10000.00, $result['totalGain']);
    }

    public function test_fifo_partial_sell(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->crypto()->create(['symbol' => 'ETH']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 2.0, 'price_per_unit' => 2000, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 1.0, 'price_per_unit' => 3000, 'transacted_at' => '2024-06-01']);

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertCount(1, $result['lots']);
        $this->assertEquals(1000.00, $result['totalGain']);
    }

    public function test_fifo_multiple_lots(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 10, 'price_per_unit' => 100, 'transacted_at' => '2024-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 10, 'price_per_unit' => 120, 'transacted_at' => '2024-02-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 15, 'price_per_unit' => 150, 'transacted_at' => '2024-07-01']);

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertEquals(650.00, $result['totalGain']);
        $this->assertCount(2, $result['lots']);
    }

    public function test_realized_loss(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'GME']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 100, 'price_per_unit' => 300, 'transacted_at' => '2021-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 100, 'price_per_unit' => 50, 'transacted_at' => '2021-03-01']);

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertEquals(-25000.00, $result['totalGain']);
    }

    public function test_by_year_grouping(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'TSLA']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 1, 'price_per_unit' => 200, 'transacted_at' => '2022-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 1, 'price_per_unit' => 300, 'transacted_at' => '2022-06-01']);
        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 1, 'price_per_unit' => 100, 'transacted_at' => '2023-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 1, 'price_per_unit' => 150, 'transacted_at' => '2023-09-01']);

        $portfolio->load('transactions.asset');
        $result = (new RealizedGainService())->compute($portfolio);

        $this->assertArrayHasKey(2022, $result['byYear']);
        $this->assertArrayHasKey(2023, $result['byYear']);
        $this->assertEquals(100.00, $result['byYear'][2022]);
        $this->assertEquals(50.00,  $result['byYear'][2023]);
    }

    public function test_portfolio_show_renders_realized_gains_section(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'NVDA']);

        Transaction::factory()->for($portfolio)->for($asset)->buy()
            ->create(['quantity' => 10, 'price_per_unit' => 400, 'transacted_at' => '2023-01-01']);
        Transaction::factory()->for($portfolio)->for($asset)->sell()
            ->create(['quantity' => 10, 'price_per_unit' => 600, 'transacted_at' => '2023-12-01']);

        $this->actingAs($portfolio->user)->get(route('portfolios.show', $portfolio))
            ->assertOk()
            ->assertSee('Realized Gains')
            ->assertSee('2,000.00');
    }
}
