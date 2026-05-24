<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\RealizedGainService;
use Illuminate\Console\Command;

class FixTransferCostBasis extends Command
{
    protected $signature = 'transfers:fix-cost-basis {--dry-run : Preview changes without writing}';

    protected $description = 'Backfill transfer_in price_per_unit with FIFO cost basis from the source portfolio';

    public function handle(RealizedGainService $svc): int
    {
        $dryRun = $this->option('dry-run');

        $transfers = Transaction::where('type', 'transfer_in')
            ->whereNotNull('linked_transfer_id')
            ->with(['linkedFrom.portfolio'])
            ->get();

        if ($transfers->isEmpty()) {
            $this->info('No transfer_in rows found.');
            return 0;
        }

        $updated = 0;
        $skipped = 0;

        foreach ($transfers as $transferIn) {
            $transferOut = $transferIn->linkedFrom;

            if (! $transferOut) {
                $this->warn("transfer_in #{$transferIn->id}: no linked transfer_out — skipping");
                $skipped++;
                continue;
            }

            $fromPortfolio  = $transferOut->portfolio;
            $assetId        = $transferIn->asset_id;
            $date           = $transferIn->transacted_at->toDateString();
            $qtySent        = (float) $transferOut->quantity;
            $qtyReceived    = (float) $transferIn->quantity;

            // replay FIFO on source portfolio excluding the transfer_out itself
            $txns = $fromPortfolio->transactions()
                ->where('asset_id', $assetId)
                ->whereIn('type', Transaction::POSITION_TYPES)
                ->where('transacted_at', '<=', $date)
                ->where('id', '!=', $transferOut->id)
                ->orderBy('transacted_at')
                ->get();

            $openLots = [];

            foreach ($txns as $t) {
                if (in_array($t->type, Transaction::INFLOW_TYPES)) {
                    $usdFee     = $t->fee_in_asset ? 0.0 : (float) $t->fees;
                    $openLots[] = [
                        'qty'           => (float) $t->quantity,
                        'cost_per_unit' => (float) $t->price_per_unit + ($usdFee / max(1, (float) $t->quantity)),
                    ];
                } elseif (in_array($t->type, Transaction::OUTFLOW_TYPES)) {
                    $remaining = $t->fee_in_asset
                        ? (float) $t->quantity + (float) $t->fees
                        : (float) $t->quantity;

                    while ($remaining > 0.000001 && ! empty($openLots)) {
                        $matched          = min($openLots[0]['qty'], $remaining);
                        $openLots[0]['qty'] -= $matched;
                        $remaining        -= $matched;
                        if ($openLots[0]['qty'] < 0.000001) {
                            array_shift($openLots);
                        }
                    }
                }
            }

            $openLots = array_values(array_filter($openLots, fn ($l) => $l['qty'] > 0.000001));

            $correctPrice = $svc->transferInCostPerUnit($openLots, $qtySent, $qtyReceived);

            if ($correctPrice === null) {
                $this->warn("transfer_in #{$transferIn->id} ({$date}): source lots don't cover qty sent — skipping");
                $skipped++;
                continue;
            }

            $old = round((float) $transferIn->price_per_unit, 8);
            $new = round($correctPrice, 8);

            if (abs($old - $new) < 0.000001) {
                $this->line("transfer_in #{$transferIn->id}: already correct ({$old}) — no change");
                continue;
            }

            $this->line(
                ($dryRun ? '[dry-run] ' : '') .
                "transfer_in #{$transferIn->id} ({$date}): {$old} → {$new}"
            );

            if (! $dryRun) {
                $transferIn->update(['price_per_unit' => $correctPrice]);
            }

            $updated++;
        }

        $label = $dryRun ? 'Would update' : 'Updated';
        $this->info("{$label} {$updated} rows, skipped {$skipped}.");

        return 0;
    }
}
