<?php

namespace App\Support;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * The one FIFO lot walk: inflows open a lot at their own cost-per-unit, outflows consume
 * the oldest open lot(s) first. Shared by Transaction::accumulateFifoCostBasis() (current
 * and as-of holdings) and RealizedGainService (realized gains + transfer carryover) so a
 * position's unrealized gain reconciles against its realized gain by construction.
 */
class FifoLots
{
    /** Quantities below this are float dust, not a real remainder. */
    public const EPSILON = 0.000001;

    /**
     * Builds FIFO open lots per asset from a transaction set (must already be in
     * chronological order). $onSale, if given, is invoked (lot, matchedQty, transaction)
     * for every FIFO match on an outflow — the hook RealizedGainService uses to record a
     * realized-gain row per match without duplicating the walk. Lots carry their buy
     * `date` only when $onSale is given.
     *
     * @param  Collection<int, Transaction>  $txns
     * @return array<int, array<int, array{qty: float, cost_per_unit: float, date?: Carbon}>> keyed by asset_id
     */
    public static function build(Collection $txns, ?callable $onSale = null): array
    {
        $openLots = [];

        foreach ($txns as $t) {
            $assetId = $t->asset_id;
            $openLots[$assetId] ??= [];

            if ($t->type->isInflow()) {
                $lot = [
                    'qty'           => (float) $t->quantity,
                    'cost_per_unit' => (float) $t->price_per_unit + $t->usdFeePerUnit(),
                ];

                // Only realized-gain rows need the buy date; skipping it keeps the per-day
                // holdings backfill from re-parsing a date cast per lot.
                if ($onSale !== null) {
                    $lot['date'] = $t->transacted_at;
                }

                $openLots[$assetId][] = $lot;
            } elseif ($t->type->isOutflow()) {
                // fee_in_asset on a transfer_out means fee units also left the wallet
                self::consume($openLots[$assetId], $t->quantityWithAssetFee(), $onSale === null
                    ? null
                    : fn (array $lot, float $matched) => $onSale($lot, $matched, $t));
            }
        }

        return $openLots;
    }

    /**
     * Consume $remaining units FIFO from &$lots (each ['qty' => float, ...]), decrementing
     * qty and shifting fully-consumed lots off the front. $onMatch, if given, is invoked
     * with (lot, matchedQty) — before qty is decremented — for every partial/full match.
     */
    public static function consume(array &$lots, float $remaining, ?callable $onMatch = null): void
    {
        while ($remaining > self::EPSILON && ! empty($lots)) {
            $matched = min($lots[0]['qty'], $remaining);

            if ($onMatch !== null) {
                $onMatch($lots[0], $matched);
            }

            $lots[0]['qty'] -= $matched;
            $remaining -= $matched;

            if ($lots[0]['qty'] < self::EPSILON) {
                array_shift($lots);
            }
        }
    }
}
