<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SnapshotPortfoliosTest extends TestCase
{
    use RefreshDatabase;

    public function test_writes_snapshot_for_today_using_the_shared_valuation_algorithm(): void
    {
        // Same algorithm BackfillPortfolioSnapshotsTest exercises via writeRange() directly —
        // this locks in that the daily cron now goes through the same code path instead of
        // its own bespoke computation (see PortfolioSnapshotBackfillService::writeRange()).
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 10,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => now()->subDay(),
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 110,
            'recorded_at' => now(),
        ]);

        $this->artisan('portfolios:snapshot')->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', now()->toDateString())
            ->firstOrFail();

        $this->assertEquals(1000.00, (float) $snapshot->cost_basis);
        $this->assertEquals(1100.00, (float) $snapshot->market_value);
        $this->assertEquals(0.00, (float) $snapshot->manual_value);
    }

    public function test_unpriced_holding_falls_back_to_cost_basis(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 5,
            'price_per_unit' => 50,
            'fees'           => 0,
            'transacted_at'  => now()->subDay(),
        ]);

        $this->artisan('portfolios:snapshot')->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', now()->toDateString())
            ->firstOrFail();

        $this->assertEquals(250.00, (float) $snapshot->cost_basis);
        $this->assertEquals(250.00, (float) $snapshot->market_value);
    }

    public function test_includes_proxy_tracked_manual_asset_value(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $proxy     = Asset::factory()->stock()->create();

        AssetPrice::factory()->create([
            'asset_id'    => $proxy->id,
            'price'       => 120,
            'recorded_at' => now(),
        ]);

        ManualAsset::factory()->for($portfolio)
            ->proxyTracked($proxy, 10000, now()->subYear()->toDateString(), 100)
            ->create(['include_in_chart' => true]);

        $this->artisan('portfolios:snapshot')->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', now()->toDateString())
            ->firstOrFail();

        $this->assertEquals(12000.00, (float) $snapshot->manual_value);
    }

    public function test_no_portfolios_prints_message_and_writes_nothing(): void
    {
        $this->artisan('portfolios:snapshot')
            ->expectsOutput('No portfolios found.')
            ->assertExitCode(0);

        $this->assertEquals(0, PortfolioSnapshot::count());
    }
}
