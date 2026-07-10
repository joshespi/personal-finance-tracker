<?php

namespace App\Console\Commands;

use App\Enums\BackfillStatus;
use App\Enums\PriceFetchOutcome;
use App\Enums\PriceSource;
use App\Models\Asset;
use App\Models\BackfillRequest;
use App\Models\Portfolio;
use App\Services\HistoricalPriceFetchService;
use App\Services\PortfolioSnapshotBackfillService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class BackfillPortfolioSnapshots extends Command
{
    protected $signature = 'portfolios:backfill-snapshots
                            {--portfolio=  : Comma-separated portfolio IDs; all if omitted}
                            {--from=       : Start date Y-m-d; defaults to earliest transaction}
                            {--to=         : End date Y-m-d; defaults to yesterday}
                            {--skip-fetch  : Skip API calls; use existing AssetPrice records}
                            {--dry-run     : Preview without writing to the database}
                            {--queue       : Queue the whole backfill (fetch + snapshot writing) instead of running it inline (drained hourly by assets:process-backfill-queue)}';

    protected $description = 'Backfill portfolio_snapshots for past dates using historical asset prices';

    public function __construct(
        private HistoricalPriceFetchService $priceFetchService,
        private PortfolioSnapshotBackfillService $backfillService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        // A dry run is a preview — it shouldn't make real API calls or persist AssetPrice
        // rows, so it always behaves as skip-fetch regardless of the flag.
        $skipFetch = $this->option('skip-fetch') || $dryRun;
        $queue     = $this->option('queue') && ! $dryRun;

        $portfolioIds = $this->option('portfolio')
            ? array_map('intval', explode(',', $this->option('portfolio')))
            : null;
        $portfolios = $this->backfillService->resolvePortfolios($portfolioIds);
        if ($portfolios->isEmpty()) {
            $this->warn('No portfolios found.');

            return self::SUCCESS;
        }

        $from = $this->resolveFrom($portfolios);
        $to   = $this->resolveTo();

        if ($from->gt($to)) {
            $this->warn('No date range to backfill (from > to).');

            return self::SUCCESS;
        }

        $this->info("Range: {$from->toDateString()} → {$to->toDateString()}, {$portfolios->count()} portfolio(s)");

        $allAssets = $this->backfillService->collectAssets($portfolios);

        // Both fetching historical prices and recomputing every day's snapshot can run long
        // enough to hit provider rate limits or a proxy timeout — queue the whole thing instead
        // so it drains hourly via assets:process-backfill-queue.
        if ($queue) {
            $this->queueBackfill($portfolios, $allAssets, $skipFetch ? collect() : $allAssets, $from, $to);

            return self::SUCCESS;
        }

        if (! $skipFetch) {
            $deferred = $this->fetchHistoricalPrices($allAssets, $from, $to);
            if ($deferred->isNotEmpty()) {
                $this->queueBackfill($portfolios, $allAssets, $deferred, $from, $to, 'Deferred after hitting a provider rate limit');
            }
        }

        $written = $this->backfillService->writeRange($portfolios, $allAssets, $from, $to, $dryRun,
            function (string $date, Portfolio $portfolio, float $total) {
                $this->line("  [{$date}] {$portfolio->name}: \${$total}");
            }
        );

        if ($dryRun) {
            $this->info('Dry run — no snapshots written.');
        } else {
            $this->info("Done. Wrote/updated {$written} snapshot(s).");
        }

        return self::SUCCESS;
    }

    private function resolveFrom(Collection $portfolios): Carbon
    {
        if ($this->option('from')) {
            return Carbon::parse($this->option('from'))->startOfDay();
        }

        $earliest = $portfolios
            ->flatMap(fn ($p) => $p->transactions)
            ->map(fn ($t) => $t->transacted_at->toDateString())
            ->filter()
            ->min();

        return $earliest
            ? Carbon::parse($earliest)->startOfDay()
            : now()->subYear()->startOfDay();
    }

    private function resolveTo(): Carbon
    {
        if ($this->option('to')) {
            return Carbon::parse($this->option('to'))->startOfDay();
        }

        return now()->subDay()->startOfDay();
    }

    /** @return Collection<int, Asset> assets whose fetch was deferred due to a rate limit */
    private function fetchHistoricalPrices(Collection $assets, Carbon $from, Carbon $to): Collection
    {
        $this->line('Fetching historical prices...');

        $result = $this->priceFetchService->fetchBatch($assets, $from, $to,
            function (Asset $asset, PriceSource $source, array $r) {
                if ($r['outcome'] === PriceFetchOutcome::RateLimited) {
                    $this->warn("  {$asset->symbol}: {$r['message']} — deferring remaining {$source->label()} assets to a queued backfill");
                } elseif ($r['outcome'] === PriceFetchOutcome::NoData) {
                    $this->warn("  {$asset->symbol}: {$r['message']}");
                } else {
                    $this->line("  {$asset->symbol} ({$source->label()}): {$r['count']} day(s)");
                }
            }
        );

        return $result['deferred'];
    }

    /**
     * @param  Collection<int, Asset>  $allAssets  every asset in scope for this backfill, fetched or not —
     *                                             frozen at queue time so the write phase can detect assets
     *                                             that enter scope later (a transaction added mid-backfill)
     *                                             and route them through a fetch instead of silently using
     *                                             cost-basis for them.
     * @param  Collection<int, Asset>  $pendingAssets  assets still needing a price fetch; empty if fetch is skipped or already done
     */
    private function queueBackfill(Collection $portfolios, Collection $allAssets, Collection $pendingAssets, Carbon $from, Carbon $to, ?string $note = null): void
    {
        $request = BackfillRequest::create([
            'portfolio_ids'     => $portfolios->pluck('id')->all(),
            'from_date'         => $from->toDateString(),
            'to_date'           => $to->toDateString(),
            'status'            => BackfillStatus::Pending->value,
            'total_assets'      => $pendingAssets->count(),
            'pending_asset_ids' => $pendingAssets->pluck('id')->all(),
            'asset_ids'         => $allAssets->pluck('id')->all(),
            'write_cursor'      => $pendingAssets->isEmpty() ? $from->toDateString() : null,
            'last_note'         => $note,
        ]);

        if ($pendingAssets->isEmpty()) {
            $this->info("Queued backfill request #{$request->id} — snapshots for {$from->toDateString()} to {$to->toDateString()} will be written via the hourly assets:process-backfill-queue job.");
        } else {
            $this->info("Queued backfill request #{$request->id} for {$pendingAssets->count()} asset(s) — will resume automatically via the hourly assets:process-backfill-queue job.");
        }
    }
}
