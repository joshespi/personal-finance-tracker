<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BackfillRequest;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessBackfillQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_pending_requests_is_a_noop(): void
    {
        $this->artisan('assets:process-backfill-queue')->assertExitCode(0);
    }

    public function test_drains_pending_request_and_generates_snapshots_once_complete(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 10,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-02',
        ]);

        $request = BackfillRequest::create([
            'portfolio_ids'     => [$portfolio->id],
            'from_date'         => '2024-01-02',
            'to_date'           => '2024-01-02',
            'status'            => 'pending',
            'total_assets'      => 1,
            'pending_asset_ids' => [$asset->id],
        ]);

        Http::fake([
            'finnhub.io/*' => Http::response([
                's' => 'ok',
                't' => [mktime(0, 0, 0, 1, 2, 2024)],
                'c' => [155.0],
            ]),
        ]);

        config(['services.finnhub.key' => 'test-key']);

        $this->artisan('assets:process-backfill-queue')->assertExitCode(0);

        $request->refresh();
        $this->assertSame('completed', $request->status);
        $this->assertSame([], $request->pending_asset_ids);
        $this->assertNotNull($request->completed_at);

        $this->assertDatabaseHas('asset_prices', ['asset_id' => $asset->id, 'price' => 155.0]);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-02')
            ->firstOrFail();

        $this->assertEquals(1000.00, (float) $snapshot->cost_basis);
        $this->assertEquals(1550.00, (float) $snapshot->market_value);
    }

    public function test_rate_limit_leaves_request_pending_for_the_next_run(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create(['symbol' => 'AAPL']);

        $request = BackfillRequest::create([
            'portfolio_ids'     => [$portfolio->id],
            'from_date'         => '2024-01-02',
            'to_date'           => '2024-01-02',
            'status'            => 'pending',
            'total_assets'      => 1,
            'pending_asset_ids' => [$asset->id],
        ]);

        Http::fake([
            'finnhub.io/*' => Http::response(['error' => 'rate limit exceeded'], 429),
        ]);

        config(['services.finnhub.key' => 'test-key']);

        $this->artisan('assets:process-backfill-queue')->assertExitCode(0);

        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertSame([$asset->id], $request->pending_asset_ids);
        $this->assertStringContainsString('Rate-limited', $request->last_note);
        $this->assertNull($request->completed_at);
    }

    public function test_only_processes_up_to_the_limit_per_run(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $assets    = Asset::factory()->stock()->count(3)->create();

        BackfillRequest::create([
            'portfolio_ids'     => [$portfolio->id],
            'from_date'         => '2024-01-02',
            'to_date'           => '2024-01-02',
            'status'            => 'pending',
            'total_assets'      => 3,
            'pending_asset_ids' => $assets->pluck('id')->all(),
        ]);

        Http::fake([
            'finnhub.io/*' => Http::response(['s' => 'ok', 't' => [], 'c' => []]),
        ]);

        config(['services.finnhub.key' => 'test-key']);

        $this->artisan('assets:process-backfill-queue', ['--limit' => 1])->assertExitCode(0);

        Http::assertSentCount(1);

        $request = BackfillRequest::sole();
        $this->assertSame('in_progress', $request->status);
        $this->assertCount(2, $request->pending_asset_ids);
    }

    public function test_write_phase_is_chunked_by_day_limit_across_multiple_runs(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-01',
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 110,
            'recorded_at' => '2024-01-01 12:00:00',
        ]);

        $request = BackfillRequest::create([
            'portfolio_ids'     => [$portfolio->id],
            'from_date'         => '2024-01-01',
            'to_date'           => '2024-01-03',
            'status'            => 'pending',
            'total_assets'      => 0,
            'pending_asset_ids' => [],
            'write_cursor'      => '2024-01-01',
        ]);

        // First run only writes one day, since the write phase is capped by --day-limit.
        $this->artisan('assets:process-backfill-queue', ['--day-limit' => 1])->assertExitCode(0);

        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertSame('2024-01-02', $request->write_cursor->toDateString());
        $this->assertDatabaseHas('portfolio_snapshots', ['portfolio_id' => $portfolio->id, 'recorded_on' => '2024-01-01']);
        $this->assertDatabaseMissing('portfolio_snapshots', ['portfolio_id' => $portfolio->id, 'recorded_on' => '2024-01-02']);

        // Second run writes the next day and still isn't done.
        $this->artisan('assets:process-backfill-queue', ['--day-limit' => 1])->assertExitCode(0);
        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertSame('2024-01-03', $request->write_cursor->toDateString());

        // Third run finishes the range and marks the request completed.
        $this->artisan('assets:process-backfill-queue', ['--day-limit' => 1])->assertExitCode(0);
        $request->refresh();
        $this->assertSame('completed', $request->status);
        $this->assertSame('2024-01-04', $request->write_cursor->toDateString());
        $this->assertNotNull($request->completed_at);
        $this->assertDatabaseHas('portfolio_snapshots', ['portfolio_id' => $portfolio->id, 'recorded_on' => '2024-01-03']);
    }

    public function test_chunked_write_derives_synthetic_shares_even_when_anchor_date_is_outside_the_current_chunk(): void
    {
        $user       = User::factory()->create();
        $portfolio  = Portfolio::factory()->for($user)->create();
        $proxyAsset = Asset::factory()->stock()->create();

        ManualAsset::factory()->for($portfolio)->create([
            'tracking_method'         => 'proxy_ticker',
            'proxy_asset_id'          => $proxyAsset->id,
            'anchor_date'             => '2024-01-01',
            'anchor_value'            => 1000,
            'anchor_synthetic_shares' => null,
            'include_in_chart'        => true,
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $proxyAsset->id,
            'price'       => 100,
            'recorded_at' => '2024-01-01 12:00:00',
        ]);
        AssetPrice::factory()->create([
            'asset_id'    => $proxyAsset->id,
            'price'       => 150,
            'recorded_at' => '2024-01-15 12:00:00',
        ]);

        // write_cursor starts partway through the range, in a later chunk whose own
        // chunk-relative ±7-day price window wouldn't reach back to anchor_date.
        BackfillRequest::create([
            'portfolio_ids'     => [$portfolio->id],
            'from_date'         => '2024-01-01',
            'to_date'           => '2024-01-20',
            'status'            => 'in_progress',
            'total_assets'      => 0,
            'pending_asset_ids' => [],
            'write_cursor'      => '2024-01-11',
        ]);

        $this->artisan('assets:process-backfill-queue', ['--day-limit' => 10])->assertExitCode(0);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-15')
            ->firstOrFail();

        // 1000 / 100 (anchor-date price) = 10 synthetic shares; 10 × 150 (2024-01-15 price) = 1500.
        $this->assertEquals(1500.00, (float) $snapshot->manual_value);
    }

    public function test_new_asset_added_mid_backfill_is_fetched_before_resuming_writes(): void
    {
        $user      = User::factory()->create();
        $portfolio = Portfolio::factory()->for($user)->create();
        $oldAsset  = Asset::factory()->stock()->create(['symbol' => 'AAA']);

        Transaction::factory()->for($portfolio)->for($oldAsset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-01',
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $oldAsset->id,
            'price'       => 110,
            'recorded_at' => '2024-01-01 12:00:00',
        ]);

        $request = BackfillRequest::create([
            'portfolio_ids'     => [$portfolio->id],
            'from_date'         => '2024-01-01',
            'to_date'           => '2024-01-01',
            'status'            => 'in_progress',
            'total_assets'      => 1,
            'pending_asset_ids' => [],
            'asset_ids'         => [$oldAsset->id],
            'write_cursor'      => '2024-01-01',
        ]);

        // A brand-new asset is bought after the fetch phase (and the asset_ids snapshot) completed.
        $newAsset = Asset::factory()->stock()->create(['symbol' => 'BBB']);
        Transaction::factory()->for($portfolio)->for($newAsset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 50,
            'fees'           => 0,
            'transacted_at'  => '2024-01-01',
        ]);

        Http::fake([
            'finnhub.io/*' => Http::response(['s' => 'ok', 't' => [mktime(0, 0, 0, 1, 1, 2024)], 'c' => [55.0]]),
        ]);
        config(['services.finnhub.key' => 'test-key']);

        // This run should detect the new asset and route it into a fetch instead of
        // writing a snapshot that silently prices it at cost basis.
        $this->artisan('assets:process-backfill-queue')->assertExitCode(0);

        $request->refresh();
        $this->assertSame('in_progress', $request->status);
        $this->assertSame([$newAsset->id], $request->pending_asset_ids);
        $this->assertEqualsCanonicalizing([$oldAsset->id, $newAsset->id], $request->asset_ids);
        $this->assertDatabaseMissing('portfolio_snapshots', ['portfolio_id' => $portfolio->id]);

        // Next run fetches the new asset, then writes the snapshot using both prices.
        $this->artisan('assets:process-backfill-queue')->assertExitCode(0);

        $request->refresh();
        $this->assertSame('completed', $request->status);

        $snapshot = PortfolioSnapshot::where('portfolio_id', $portfolio->id)
            ->where('recorded_on', '2024-01-01')
            ->firstOrFail();

        // 1 × 110 (AAA) + 1 × 55 (BBB, fetched) = 165 — proves BBB used its fetched price, not its $50 cost basis.
        $this->assertEquals(165.00, (float) $snapshot->market_value);
    }
}
