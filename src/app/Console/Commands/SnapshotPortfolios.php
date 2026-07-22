<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Services\PortfolioSnapshotBackfillService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SnapshotPortfolios extends Command
{
    protected $signature = 'portfolios:snapshot';

    protected $description = 'Record a daily value snapshot for every portfolio';

    public function __construct(private PortfolioSnapshotBackfillService $backfillService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today      = Carbon::now()->startOfDay();
        $portfolios = $this->backfillService->resolvePortfolios(null);

        if ($portfolios->isEmpty()) {
            $this->info('No portfolios found.');

            return self::SUCCESS;
        }

        $allAssets = $this->backfillService->collectAssets($portfolios);

        $this->backfillService->writeRange($portfolios, $allAssets, $today, $today, false,
            function (string $date, Portfolio $portfolio, float $total) {
                $this->line("  {$portfolio->name}: \${$total}");
            }
        );

        $this->info('Snapshots recorded for '.$portfolios->count().' portfolio(s).');

        return self::SUCCESS;
    }
}
