<?php

namespace App\Models;

use App\Enums\AssetType;
use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'portfolio_id', 'asset_id', 'type', 'quantity',
        'price_per_unit', 'fees', 'fee_in_asset', 'currency', 'notes', 'transacted_at',
        'linked_transfer_id',
    ];

    protected $casts = [
        'type'           => TransactionType::class,
        'transacted_at'  => 'datetime',
        'quantity'       => 'decimal:8',
        'price_per_unit' => 'decimal:8',
        'fees'           => 'decimal:8',
        'fee_in_asset'   => 'boolean',
    ];

    /**
     * Shared field-validation rules used by the manual transaction form (store/update)
     * and the CSV importer — keeps the three call sites from drifting independently.
     */
    public static function fieldRules(): array
    {
        return [
            'symbol'         => ['required', 'string', 'max:20'],
            'asset_type'     => ['required', Rule::enum(AssetType::class)],
            'type'           => ['required', Rule::enum(TransactionType::class)],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'gte:0'],
            'fees'           => ['nullable', 'numeric', 'gte:0'],
            'currency'       => ['required', 'string', 'size:3'],
        ];
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** The transfer_out that originated this transfer_in (BelongsTo via linked_transfer_id) */
    public function linkedFrom(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'linked_transfer_id');
    }

    /** The transfer_in that received funds from this transfer_out (HasOne reverse) */
    public function linkedTo(): HasOne
    {
        return $this->hasOne(Transaction::class, 'linked_transfer_id');
    }

    /** Total cost including fees (for buys) */
    public function totalCost(): float
    {
        return (float) $this->quantity * (float) $this->price_per_unit + $this->usdFee();
    }

    /** Dividend received: quantity × price_per_unit (no fees) */
    public function dividendValue(): float
    {
        return (float) $this->quantity * (float) $this->price_per_unit;
    }

    public function toBackupArray(): array
    {
        return [
            'date'           => $this->transacted_at->toDateString(),
            'symbol'         => $this->asset->symbol,
            'asset_type'     => $this->asset->asset_type,
            'type'           => $this->type->value,
            'quantity'       => (float) $this->quantity,
            'price_per_unit' => (float) $this->price_per_unit,
            'fees'           => (float) $this->fees,
            'currency'       => $this->currency,
            'notes'          => $this->notes,
        ];
    }

    /**
     * The fee expressed in cash terms — zero when fee_in_asset is set, since that fee
     * was paid in units of the asset itself (see quantityWithAssetFee()) rather than cash.
     */
    public function usdFee(): float
    {
        return $this->fee_in_asset ? 0.0 : (float) $this->fees;
    }

    /**
     * The cash fee spread across the units transacted — folded into cost-per-unit on a
     * buy, subtracted from the received price on a sell, so both reconcile with the
     * account's actual cash movement.
     *
     * Divides by the real quantity, never max(1, quantity): that is a unit floor, not a
     * zero guard, and would silently shrink the fee's effect on any sub-1-unit trade
     * (routine for crypto). Only an exact zero quantity is special-cased.
     */
    public function usdFeePerUnit(): float
    {
        $qty = (float) $this->quantity;

        return $qty > 0 ? $this->usdFee() / $qty : 0.0;
    }

    /**
     * Total asset units this transaction removes from the wallet: the stored quantity
     * plus the fee, when the fee was paid in the asset itself rather than in cash.
     */
    public function quantityWithAssetFee(): float
    {
        return $this->fee_in_asset ? (float) $this->quantity + (float) $this->fees : (float) $this->quantity;
    }

    /**
     * Net quantity after subtracting an in-asset fee (never below zero) — shared by the
     * transfer wizard (destination received quantity) and the transaction edit form
     * (undoing its gross-for-display transform before storage).
     */
    public static function netOfFee(float $quantity, float $fees, bool $feeInAsset): float
    {
        return $feeInAsset ? max(0.0, $quantity - $fees) : $quantity;
    }

    /**
     * Accumulates running quantity and FIFO remaining cost basis across a set of
     * same-asset transactions: each inflow opens a lot at its own cost-per-unit, each
     * outflow consumes the oldest open lot(s) first. Cost basis is the sum of what's
     * left in the still-open lots — the same FIFO convention RealizedGainService's
     * realized-gain lots already use, so a position's unrealized_gain now reconciles
     * against its realized gain (previously computeHoldings() used a blended
     * weighted-average cost instead, which could not guarantee that).
     *
     * Quantity is unaffected by which convention is used — FIFO and weighted-average
     * net the same units held, only the cost basis of what remains differs.
     *
     * Shared by Portfolio::computeHoldings() (current holdings) and
     * PortfolioSnapshotBackfillService (historical as-of holdings) — same algorithm,
     * only the transaction set and the price used to value the result differ.
     *
     * @param  Collection<int, Transaction>  $transactions  same-asset transactions, any order
     * @return array{0: float, 1: float} [quantity, cost_basis]
     */
    public static function accumulateFifoCostBasis(Collection $transactions): array
    {
        $lots = [];

        foreach ($transactions->sortBy('transacted_at') as $t) {
            if ($t->type->isInflow()) {
                $costPerUnit = (float) $t->price_per_unit + $t->usdFeePerUnit();
                $lots[]      = ['qty' => (float) $t->quantity, 'cost_per_unit' => $costPerUnit];
            } elseif ($t->type->isOutflow()) {
                $remaining = $t->quantityWithAssetFee();

                while ($remaining > 0.000001 && ! empty($lots)) {
                    $matched = min($lots[0]['qty'], $remaining);
                    $lots[0]['qty'] -= $matched;
                    $remaining -= $matched;

                    if ($lots[0]['qty'] < 0.000001) {
                        array_shift($lots);
                    }
                }
            }
        }

        $totalQty  = array_sum(array_column($lots, 'qty'));
        $totalCost = array_sum(array_map(fn ($l) => $l['qty'] * $l['cost_per_unit'], $lots));

        return [max(0.0, round($totalQty, 8)), max(0.0, $totalCost)];
    }
}
