<?php

namespace App\Console\Commands;

use App\Enums\BackfillStatus;
use App\Enums\PriceFetchOutcome;
use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\BackfillRequest;
use App\Services\HistoricalPriceFetchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessBackfillQueue extends Command
{
    protected $signature = 'assets:process-backfill-queue {--limit=25 : Max assets to fetch per run}';

    protected $description = 'Drain the oldest pending portfolio-snapshot backfill request in small, rate-limit-aware batches';

    public function __construct(private HistoricalPriceFetchService $priceFetchService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $request = BackfillRequest::whereIn('status', [BackfillStatus::Pending->value, BackfillStatus::InProgress->value])
            ->orderBy('created_at')
            ->first();

        if (! $request) {
            $this->info('No pending backfill requests.');

            return self::SUCCESS;
        }

        $request->status = BackfillStatus::InProgress->value;

        $limit      = (int) $this->option('limit');
        $pendingIds = collect($request->pending_asset_ids);
        $batch      = $pendingIds->take($limit);
        $assetsById = Asset::whereIn('id', $batch)->get()->keyBy('id');

        // Assets deleted since queueing have nothing left to fetch for them,
        // so they're dropped here rather than carried forward as still-pending.
        $batchAssets  = $batch->map(fn ($id) => $assetsById->get($id))->filter()->values();
        $stillPending = $pendingIds->diff($batch)->values();
        $note         = null;

        $result = $this->priceFetchService->fetchBatch($batchAssets, $request->from_date, $request->to_date,
            function (Asset $asset, PriceSource $source, array $r) use (&$note) {
                if ($r['outcome'] === PriceFetchOutcome::RateLimited) {
                    $note = "Rate-limited by {$source->label()} — resuming next hour";
                    $this->warn("  {$asset->symbol}: {$r['message']}, deferring");
                } else {
                    $this->line("  {$asset->symbol} ({$source->label()}): {$r['outcome']->value}, {$r['count']} day(s)");
                }
            }
        );

        $stillPending = $stillPending->concat($result['deferred']->pluck('id'))->values();

        $request->pending_asset_ids = $stillPending->all();
        $request->last_note         = $note;
        $request->save();

        if ($stillPending->isEmpty()) {
            $this->finalize($request);
        } else {
            $this->info("Backfill request #{$request->id}: {$request->fetchedCount()}/{$request->total_assets} assets fetched so far.");
        }

        return self::SUCCESS;
    }

    private function finalize(BackfillRequest $request): void
    {
        Artisan::call('portfolios:backfill-snapshots', [
            '--from'       => $request->from_date->toDateString(),
            '--to'         => $request->to_date->toDateString(),
            '--portfolio'  => $request->portfolioIdsCsv(),
            '--skip-fetch' => true,
        ]);

        $request->status       = BackfillStatus::Completed->value;
        $request->completed_at = now();
        $request->last_note    = 'Completed — snapshots generated';
        $request->save();

        $this->info("Backfill request #{$request->id} completed — snapshots generated for {$request->from_date->toDateString()} to {$request->to_date->toDateString()}.");
    }
}
