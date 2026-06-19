<?php

namespace App\Console\Commands;

use App\Enums\TransactionType;
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

        $transfers = Transaction::where('type', TransactionType::TransferIn->value)
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

            $fromPortfolio = $transferOut->portfolio;
            $assetId       = $transferIn->asset_id;
            $date          = $transferIn->transacted_at->toDateString();
            $qtySent       = (float) $transferOut->quantity;
            $qtyReceived   = (float) $transferIn->quantity;

            // replay FIFO on source portfolio excluding the transfer_out itself
            $openLots = $svc->openLotsForAsset($fromPortfolio, $assetId, $date, $transferOut->id);

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
                ($dryRun ? '[dry-run] ' : '').
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
