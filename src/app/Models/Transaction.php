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
        return (float) $this->quantity * (float) $this->price_per_unit + (float) $this->fees;
    }

    /** Dividend received: quantity × price_per_unit (no fees) */
    public function dividendValue(): float
    {
        return (float) $this->quantity * (float) $this->price_per_unit;
    }

    /**
     * Accumulates running quantity and weighted-average cost basis across a set of
     * same-asset transactions (outflows deduct proportionally from the running cost).
     * Shared by Portfolio::computeHoldings() (current holdings) and
     * PortfolioSnapshotBackfillService (historical as-of holdings) — same algorithm,
     * only the transaction set and the price used to value the result differ.
     *
     * @param  Collection<int, Transaction>  $transactions  same-asset transactions, any order
     * @return array{0: float, 1: float} [quantity, cost_basis]
     */
    public static function accumulateCostBasis(Collection $transactions): array
    {
        $totalQty  = 0.0;
        $totalCost = 0.0;

        foreach ($transactions->sortBy('transacted_at') as $t) {
            $qty = (float) $t->quantity;
            if ($t->type->isInflow()) {
                $usdFee = $t->fee_in_asset ? 0.0 : (float) $t->fees;
                $totalCost += $qty * (float) $t->price_per_unit + $usdFee;
                $totalQty += $qty;
            } elseif ($t->type->isOutflow()) {
                $deduct = $t->fee_in_asset ? $qty + (float) $t->fees : $qty;
                if ($totalQty > 0) {
                    $totalCost -= ($totalCost / $totalQty) * min($deduct, $totalQty);
                }
                $totalQty -= $deduct;
            }
        }

        return [max(0.0, round($totalQty, 8)), max(0.0, $totalCost)];
    }
}
