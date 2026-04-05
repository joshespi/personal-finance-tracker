<?php

namespace App\Console\Commands;

use App\Models\Portfolio;
use App\Models\PortfolioSnapshot;
use Illuminate\Console\Command;

class SnapshotPortfolios extends Command
{
    protected $signature = 'portfolios:snapshot';
    protected $description = 'Record a daily value snapshot for every portfolio';

    public function handle(): int
    {
        $today      = now()->toDateString();
        $portfolios = Portfolio::with(['transactions.asset.latestPrice', 'manualAssets.latestValuation'])->get();

        if ($portfolios->isEmpty()) {
            $this->info('No portfolios found.');
            return self::SUCCESS;
        }

        foreach ($portfolios as $portfolio) {
            $holdings = $portfolio->computeHoldings();

            $costBasis   = $holdings->sum('total_cost');
            // For unpriced assets, fall back to cost basis so the chart stays meaningful
            $marketValue = $holdings->sum(fn ($h) => $h['current_value'] ?? $h['total_cost']);
            $manualValue = $portfolio->manualAssets->sum(
                fn ($ma) => $ma->latestValuation ? (float) $ma->latestValuation->value : 0
            );

            PortfolioSnapshot::updateOrCreate(
                ['portfolio_id' => $portfolio->id, 'recorded_on' => $today],
                [
                    'cost_basis'   => $costBasis,
                    'market_value' => $marketValue,
                    'manual_value' => $manualValue,
                ]
            );

            $total = round($marketValue + $manualValue, 2);
            $this->line("  {$portfolio->name}: \${$total}");
        }

        $this->info('Snapshots recorded for ' . $portfolios->count() . ' portfolio(s).');

        return self::SUCCESS;
    }
}
