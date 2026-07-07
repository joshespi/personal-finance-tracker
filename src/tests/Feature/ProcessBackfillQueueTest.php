<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BackfillRequest;
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
}
