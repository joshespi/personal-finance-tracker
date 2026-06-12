<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\ManualAsset;
use App\Models\ManualValuation;
use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BackfillPortfolioSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_snapshot_for_each_day_in_range(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 10,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-01',
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 110,
            'recorded_at' => '2024-01-03 12:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'        => '2024-01-03',
            '--to'          => '2024-01-03',
            '--skip-fetch'  => true,
            '--portfolio'   => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-03')
            ->firstOrFail();

        $this->assertEquals(1000.00, (float) $snapshot->cost_basis);
        $this->assertEquals(1100.00, (float) $snapshot->market_value);
        $this->assertEquals(0.00, (float) $snapshot->manual_value);
    }

    public function test_weekend_uses_most_recent_prior_weekday_price(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 5,
            'price_per_unit' => 200,
            'fees'           => 0,
            'transacted_at'  => '2024-01-05', // Friday
        ]);

        // Only Friday price exists — Saturday and Sunday should carry it forward
        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 210,
            'recorded_at' => '2024-01-05 12:00:00', // Friday
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-07', // Sunday
            '--to'         => '2024-01-07',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-07')
            ->firstOrFail();

        $this->assertEquals(1050.00, (float) $snapshot->market_value);
    }

    public function test_dry_run_does_not_write_snapshots(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 50,
            'fees'           => 0,
            'transacted_at'  => '2024-01-10',
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 60,
            'recorded_at' => '2024-01-10 12:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-10',
            '--to'         => '2024-01-10',
            '--skip-fetch' => true,
            '--dry-run'    => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('portfolio_snapshots', ['portfolio_id' => $portfolio->id]);
    }

    public function test_dates_before_first_transaction_have_zero_cost_and_market(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        // Transaction on Jan 5 — snapshot for Jan 3 should have no holdings
        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 10,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-05',
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 105,
            'recorded_at' => '2024-01-03 12:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-03',
            '--to'         => '2024-01-03',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-03')
            ->firstOrFail();

        $this->assertEquals(0.00, (float) $snapshot->cost_basis);
        $this->assertEquals(0.00, (float) $snapshot->market_value);
    }

    public function test_static_manual_asset_uses_nearest_prior_valuation(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        $manualAsset = ManualAsset::factory()->for($portfolio)->create([
            'tracking_method' => 'static',
            'include_in_chart' => true,
        ]);

        // Valuation recorded on Jan 1
        ManualValuation::factory()->for($manualAsset)->create([
            'value'     => 50000,
            'valued_at' => '2024-01-01 00:00:00',
        ]);

        // Later valuation on Jan 10 — should NOT be used for Jan 5 snapshots
        ManualValuation::factory()->for($manualAsset)->create([
            'value'     => 55000,
            'valued_at' => '2024-01-10 00:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-05',
            '--to'         => '2024-01-05',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-05')
            ->firstOrFail();

        $this->assertEquals(50000.00, (float) $snapshot->manual_value);
    }

    public function test_proxy_ticker_manual_asset_uses_proxy_price_times_shares(): void
    {
        $user        = User::factory()->create();
        $portfolio   = Portfolio::factory()->for($user)->create();
        $proxyAsset  = Asset::factory()->stock()->create();

        ManualAsset::factory()->for($portfolio)->create([
            'tracking_method'         => 'proxy_ticker',
            'proxy_asset_id'          => $proxyAsset->id,
            'anchor_date'             => '2024-01-01',
            'anchor_value'            => 10000,
            'anchor_synthetic_shares' => 100,
            'include_in_chart'        => true,
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $proxyAsset->id,
            'price'       => 120,
            'recorded_at' => '2024-01-15 12:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-15',
            '--to'         => '2024-01-15',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-15')
            ->firstOrFail();

        $this->assertEquals(12000.00, (float) $snapshot->manual_value); // 100 shares × $120
    }

    public function test_proxy_ticker_excluded_before_anchor_date(): void
    {
        $user       = User::factory()->create();
        $portfolio  = Portfolio::factory()->for($user)->create();
        $proxyAsset = Asset::factory()->stock()->create();

        ManualAsset::factory()->for($portfolio)->create([
            'tracking_method'         => 'proxy_ticker',
            'proxy_asset_id'          => $proxyAsset->id,
            'anchor_date'             => '2024-02-01',
            'anchor_value'            => 10000,
            'anchor_synthetic_shares' => 100,
            'include_in_chart'        => true,
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $proxyAsset->id,
            'price'       => 120,
            'recorded_at' => '2024-01-15 12:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-15',
            '--to'         => '2024-01-15',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-15')
            ->firstOrFail();

        $this->assertEquals(0.00, (float) $snapshot->manual_value);
    }

    public function test_non_invested_manual_asset_is_split_into_excluded_value(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        // Invested manual asset (e.g. a 401k) — counts toward invested.
        $invested = ManualAsset::factory()->for($portfolio)->create([
            'tracking_method'     => 'static',
            'include_in_chart'    => true,
            'include_in_invested' => true,
        ]);
        ManualValuation::factory()->for($invested)->create([
            'value'     => 30000,
            'valued_at' => '2024-01-01 00:00:00',
        ]);

        // Non-invested manual asset (e.g. the home) — charted but excluded from invested.
        $home = ManualAsset::factory()->for($portfolio)->create([
            'tracking_method'     => 'static',
            'include_in_chart'    => true,
            'include_in_invested' => false,
        ]);
        ManualValuation::factory()->for($home)->create([
            'value'     => 50000,
            'valued_at' => '2024-01-01 00:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-05',
            '--to'         => '2024-01-05',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-05')
            ->firstOrFail();

        // manual_value = both assets; excluded_value = only the non-invested one.
        $this->assertEquals(80000.00, (float) $snapshot->manual_value);
        $this->assertEquals(50000.00, (float) $snapshot->excluded_value);
    }

    public function test_updates_existing_snapshot(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        PortfolioSnapshot::create([
            'portfolio_id' => $portfolio->id,
            'recorded_on'  => '2024-01-20',
            'cost_basis'   => 999,
            'market_value' => 999,
            'manual_value' => 0,
        ]);

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 2,
            'price_per_unit' => 50,
            'fees'           => 0,
            'transacted_at'  => '2024-01-20',
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 60,
            'recorded_at' => '2024-01-20 12:00:00',
        ]);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-20',
            '--to'         => '2024-01-20',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-20')
            ->firstOrFail();

        $this->assertEquals(100.00, (float) $snapshot->cost_basis);
        $this->assertEquals(120.00, (float) $snapshot->market_value);
    }

    public function test_skip_fetch_does_not_make_http_calls(): void
    {
        Http::fake(); // any unmatched request would throw if Http::preventStrayRequests() were on

        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'       => '2024-01-01',
            '--to'         => '2024-01-01',
            '--skip-fetch' => true,
            '--portfolio'  => $portfolio->id,
        ])->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_fetch_path_calls_finnhub_for_stock_assets(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-02',
        ]);

        Http::fake([
            'finnhub.io/*' => Http::response([
                's' => 'ok',
                't' => [mktime(0, 0, 0, 1, 2, 2024)],
                'c' => [155.0],
            ]),
        ]);

        config(['services.finnhub.key' => 'test-key']);

        $this->artisan('portfolios:backfill-snapshots', [
            '--from'      => '2024-01-02',
            '--to'        => '2024-01-02',
            '--portfolio' => $portfolio->id,
        ])->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'finnhub.io'));
    }
}
