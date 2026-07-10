<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetPrice;
use App\Models\BackfillRequest;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminToolsBackfillSnapshotsTest extends TestCase
{
    use RefreshDatabase;

    public function test_skip_fetch_runs_synchronously_without_queuing(): void
    {
        Http::fake();

        $admin     = User::factory()->admin()->create();
        $portfolio = Portfolio::factory()->for($admin)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-02',
        ]);

        AssetPrice::factory()->create([
            'asset_id'    => $asset->id,
            'price'       => 110,
            'recorded_at' => '2024-01-02 12:00:00',
        ]);

        $this->actingAs($admin)->post(route('admin.tools.backfill-snapshots'), [
            'from'       => '2024-01-02',
            'to'         => '2024-01-02',
            'portfolio'  => (string) $portfolio->id,
            'skip_fetch' => '1',
        ])->assertRedirect(route('admin.tools'));

        Http::assertNothingSent();
        $this->assertSame(0, BackfillRequest::count());
        $this->assertDatabaseHas('portfolio_snapshots', ['portfolio_id' => $portfolio->id, 'recorded_on' => '2024-01-02']);
    }

    public function test_fetching_without_skip_fetch_queues_instead_of_running_inline(): void
    {
        Http::fake();

        $admin     = User::factory()->admin()->create();
        $portfolio = Portfolio::factory()->for($admin)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-02',
        ]);

        $this->actingAs($admin)->post(route('admin.tools.backfill-snapshots'), [
            'from'      => '2024-01-02',
            'to'        => '2024-01-02',
            'portfolio' => (string) $portfolio->id,
        ])->assertRedirect(route('admin.tools'));

        Http::assertNothingSent();
        $this->assertSame(1, BackfillRequest::count());
        $this->assertDatabaseMissing('portfolio_snapshots', ['portfolio_id' => $portfolio->id]);
    }

    public function test_dry_run_never_fetches_or_writes_even_without_skip_fetch(): void
    {
        Http::fake();

        $admin     = User::factory()->admin()->create();
        $portfolio = Portfolio::factory()->for($admin)->create();
        $asset     = Asset::factory()->stock()->create();

        Transaction::factory()->for($portfolio)->for($asset)->create([
            'type'           => 'buy',
            'quantity'       => 1,
            'price_per_unit' => 100,
            'fees'           => 0,
            'transacted_at'  => '2024-01-02',
        ]);

        $this->actingAs($admin)->post(route('admin.tools.backfill-snapshots'), [
            'from'      => '2024-01-02',
            'to'        => '2024-01-02',
            'portfolio' => (string) $portfolio->id,
            'dry_run'   => '1',
        ])->assertRedirect(route('admin.tools'));

        Http::assertNothingSent();
        $this->assertSame(0, BackfillRequest::count());
        $this->assertDatabaseMissing('portfolio_snapshots', ['portfolio_id' => $portfolio->id]);
    }
}
