<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SnapshotPortfolios extends Command
{
    protected $signature = 'portfolios:snapshot';
    protected $description = 'Record a daily value snapshot for every portfolio';

    public function handle(): int
    {
        $today      = now()->toDateString();
        $portfolios = Portfolio::with(['transactions.asset.latestPrice', 'manualAssets.latestValuation', 'manualAssets.proxyAsset.latestPrice'])->get();

        if ($portfolios->isEmpty()) {
            $this->info('No portfolios found.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($portfolios, $today) {
            foreach ($portfolios as $portfolio) {
                $holdings = $portfolio->computeHoldings();

                $costBasis     = $holdings->sum('total_cost');
                $marketValue   = $holdings->sum('effective_value');
                $manualValue   = $portfolio->chartManualValue();
                $excludedValue = $portfolio->chartExcludedValue();

                PortfolioSnapshot::updateOrCreate(
                    ['portfolio_id' => $portfolio->id, 'recorded_on' => $today],
                    [
                        'cost_basis'     => $costBasis,
                        'market_value'   => $marketValue,
                        'manual_value'   => $manualValue,
                        'excluded_value' => $excludedValue,
                    ]
                );

                $total = round($marketValue + $manualValue, 2);
                $this->line("  {$portfolio->name}: \${$total}");
            }
        });

        $this->info('Snapshots recorded for ' . $portfolios->count() . ' portfolio(s).');

        return self::SUCCESS;
    }
}
