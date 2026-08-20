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
    /** Jan 1 at $1,000 and Feb 1 at $febValue — the two-snapshot frame both TWR tests use. */
    private function snapshotPair(Portfolio $portfolio, float $febValue): void
    {
        foreach (['2024-01-01' => 1000.0, '2024-02-01' => $febValue] as $date => $value) {
            PortfolioSnapshot::create([
                'portfolio_id' => $portfolio->id,
                'recorded_on'  => $date,
                'market_value' => $value,
                'manual_value' => 0,
                'cost_basis'   => $value,
            ]);
        }
    }

    public function test_twr_ignores_fee_when_fee_in_asset_is_true(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->crypto()->create(['symbol' => 'ETH']);

        $this->snapshotPair($portfolio, 2100.0);

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

    /**
     * Regression: the cashflow denominator used to be looked up by an exact match on
     * each snapshot's own date (`$cashflows[$date] ?? 0`), so a buy landing on a day with
     * no snapshot — a missed scheduler run, or any gap — was silently dropped from the
     * denominator. The deposit then read as pure investment gain instead of contributed
     * capital. Buy here on 2024-01-15, between the two snapshot dates, with no price
     * change at all — a correct TWR must be 0%, not "the deposit looks like a gain."
     */
    public function test_twr_attributes_a_cashflow_between_snapshot_dates_to_its_subperiod(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->crypto()->create(['symbol' => 'ETH']);

        // Feb value is exactly Jan + the deposit: no price movement at all.
        $this->snapshotPair($portfolio, 2000.0);

        // No snapshot exists on this date — the whole point of the regression.
        Transaction::factory()->for($portfolio)->for($asset)->buy()->create([
            'quantity'       => 1,
            'price_per_unit' => 1000,
            'fees'           => 0,
            'transacted_at'  => '2024-01-15',
        ]);

        $portfolio->load('snapshots', 'transactions');
        $result = (new PortfolioPerformanceService)->computeTwr($portfolio);

        $this->assertEquals(0.0, $result['total_pct']);
    }

    /**
     * Regression: the cash fee was always *added* to the trade value, which is only right
     * on a buy. On a sell the investor receives (qty x price - fee), so adding the fee to a
     * negatively-signed cashflow overstated the withdrawal by twice the fee and dragged the
     * measured return down. Sell 1 unit at $500 with a $50 cash fee out of a $1,000 book
     * that ends at $550: cash actually taken out is $450, so the denominator is
     * 1000 - 450 = 550 and TWR is 550/550 = 0%. The old code used 1000 - 550 = 450 and
     * reported a spurious +22.22%.
     */
    public function test_twr_subtracts_a_cash_fee_from_sale_proceeds(): void
    {
        $portfolio = Portfolio::factory()->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'MSFT']);

        $this->snapshotPair($portfolio, 550.0);

        Transaction::factory()->for($portfolio)->for($asset)->sell()->create([
            'quantity'       => 1,
            'price_per_unit' => 500,
            'fees'           => 50,
            'fee_in_asset'   => false,
            'transacted_at'  => '2024-02-01',
        ]);

        $portfolio->load('snapshots', 'transactions');
        $result = (new PortfolioPerformanceService)->computeTwr($portfolio);

        $this->assertEquals(0.0, $result['total_pct']);
    }
}
