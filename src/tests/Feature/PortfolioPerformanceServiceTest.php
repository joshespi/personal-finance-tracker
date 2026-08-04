<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Services\PortfolioPerformanceService;
use Tests\TestCase;

class PortfolioPerformanceServiceTest extends TestCase
{
    public function test_twr_ignores_fee_when_fee_in_asset_is_true(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->crypto()->create(['symbol' => 'ETH']);

        PortfolioSnapshot::create([
            'portfolio_id' => $portfolio->id,
            'recorded_on'  => '2024-01-01',
            'market_value' => 1000,
            'manual_value' => 0,
            'cost_basis'   => 1000,
        ]);
        PortfolioSnapshot::create([
            'portfolio_id' => $portfolio->id,
            'recorded_on'  => '2024-02-01',
            'market_value' => 2100,
            'manual_value' => 0,
            'cost_basis'   => 2100,
        ]);

        // fee_in_asset buy: the $100 fee left the wallet as units, never as cash — the
        // TWR cashflow denominator must use only quantity*price (usdFee() is zero here),
        // not the raw fees column, or it double-counts a fee that was never cash.
        Transaction::factory()->for($portfolio)->for($asset)->buy()->create([
            'quantity'       => 1,
            'price_per_unit' => 1000,
            'fees'           => 100,
            'fee_in_asset'   => true,
            'transacted_at'  => '2024-02-01',
        ]);

        $portfolio->load('snapshots', 'transactions');
        $result = (new PortfolioPerformanceService)->computeTwr($portfolio);

        // denominator = prevValue(1000) + cashflow(1000, the buy's cash cost with the
        // fee excluded) = 2000; twr = 2100/2000 = 1.05 => +5.00%
        $this->assertEquals(5.0, $result['total_pct']);
    }
}
