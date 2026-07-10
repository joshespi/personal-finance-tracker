<?php

namespace App\Console\Commands;

use App\Enums\BackfillStatus;
use App\Enums\PriceFetchOutcome;
use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\BackfillRequest;
use App\Services\HistoricalPriceFetchService;
use App\Services\PortfolioSnapshotBackfillService;
use Illuminate\Console\Command;

class ProcessBackfillQueue extends Command
{
    protected $signature = 'assets:process-backfill-queue
                            {--limit=25    : Max assets to fetch per run}
                            {--day-limit=90 : Max days of snapshots to write per run}';

    protected $description = 'Drain the oldest pending portfolio-snapshot backfill request in small, rate-limit-aware batches';

    public function __construct(
        private HistoricalPriceFetchService $priceFetchService,
        private PortfolioSnapshotBackfillService $backfillService,
    ) {
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

        if (! empty($request->pending_asset_ids)) {
            $this->drainAssetFetch($request);
        }

        // Falls through in the same run once fetching just finished, so small requests
        // still complete in a single hourly tick instead of waiting an extra hour.
        if (empty($request->pending_asset_ids)) {
            $this->drainSnapshotWrite($request);
        }

        return self::SUCCESS;
    }

    private function drainAssetFetch(BackfillRequest $request): void
    {
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

        if ($stillPending->isEmpty()) {
            // Only seed the cursor the first time fetching completes — if this fetch was
            // reopened mid-backfill (see drainSnapshotWrite()'s new-asset check), the write
            // phase may already be partway through and shouldn't be rewound to from_date.
            if (! $request->write_cursor) {
                $request->write_cursor = $request->from_date;
            }
        } else {
            $this->info("Backfill request #{$request->id}: {$request->fetchedCount()}/{$request->total_assets} assets fetched so far.");
        }

        $request->save();
    }

    private function drainSnapshotWrite(BackfillRequest $request): void
    {
        if (! $request->write_cursor) {
            $request->write_cursor = $request->from_date;
        }

        $portfolios = $this->backfillService->resolvePortfolios($request->portfolio_ids);
        $allAssets  = $this->backfillService->collectAssets($portfolios);

        // A transaction or manual-asset proxy referencing an asset outside the set fetched
        // at queue time can appear while a large backfill is still draining (the write phase
        // now spans many hourly ticks). Route it through the normal fetch phase instead of
        // writing snapshots with missing price data for it. asset_ids is only null for
        // requests that predate this check (or were built directly in tests) — there's
        // nothing to compare against, so skip rather than treat everything as "new".
        $knownAssetIds = $request->asset_ids !== null ? collect($request->asset_ids) : null;
        $newAssets     = $knownAssetIds !== null
            ? $allAssets->reject(fn ($asset) => $knownAssetIds->contains($asset->id))
            : collect();

        if ($newAssets->isNotEmpty()) {
            $request->asset_ids = $knownAssetIds->concat($newAssets->pluck('id'))->unique()->values()->all();
            $request->total_assets += $newAssets->count();
            $request->pending_asset_ids = collect($request->pending_asset_ids)->concat($newAssets->pluck('id'))->unique()->values()->all();
            $request->last_note         = "Fetching prices for {$newAssets->count()} asset(s) added since queueing";
            $request->save();

            $this->info("Backfill request #{$request->id}: found {$newAssets->count()} new asset(s) since queueing — fetching before resuming snapshot writes.");

            return;
        }

        $chunkDays = max(1, (int) $this->option('day-limit'));
        $chunkFrom = $request->write_cursor->copy();
        $chunkTo   = $chunkFrom->copy()->addDays($chunkDays - 1);
        if ($chunkTo->gt($request->to_date)) {
            $chunkTo = $request->to_date->copy();
        }

        $this->backfillService->writeRange($portfolios, $allAssets, $chunkFrom, $chunkTo, false, priceLoadFrom: $request->from_date);

        $nextCursor            = $chunkTo->copy()->addDay();
        $request->write_cursor = $nextCursor;

        if ($nextCursor->gt($request->to_date)) {
            $request->status       = BackfillStatus::Completed->value;
            $request->completed_at = now();
            $request->last_note    = 'Completed — snapshots generated';
            $request->save();

            $this->info("Backfill request #{$request->id} completed — snapshots generated for {$request->from_date->toDateString()} to {$request->to_date->toDateString()}.");

            return;
        }

        $request->save();

        $this->info("Backfill request #{$request->id}: wrote snapshots through {$chunkTo->toDateString()} ({$request->writtenDays()}/{$request->totalDays()} days).");
    }
}
