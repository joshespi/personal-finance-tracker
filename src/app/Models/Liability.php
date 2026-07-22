<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Liability extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'manual_asset_id', 'name', 'liability_type', 'interest_rate',
        'minimum_payment', 'escrow_amount', 'payment_day',
        'payment_envelope_id', 'payment_cash_account_id', 'notes', 'currency',
    ];

    protected $casts = [
        'interest_rate'   => 'decimal:3',
        'minimum_payment' => 'decimal:2',
        'escrow_amount'   => 'decimal:2',
        'payment_day'     => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manualAsset(): BelongsTo
    {
        return $this->belongsTo(ManualAsset::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LiabilityBalance::class);
    }

    public function latestBalance(): HasOne
    {
        return $this->hasOne(LiabilityBalance::class)->latestOfMany('recorded_at');
    }

    public function paymentEnvelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class, 'payment_envelope_id');
    }

    public function paymentCashAccount(): BelongsTo
    {
        return $this->belongsTo(CashAccount::class, 'payment_cash_account_id');
    }

    public function linkedSchedule(): HasOne
    {
        return $this->hasOne(ScheduledTransaction::class);
    }

    public function totalMonthlyPayment(): float
    {
        return (float) ($this->minimum_payment ?? 0) + (float) ($this->escrow_amount ?? 0);
    }

    public function isRevolving(): bool
    {
        return self::isRevolvingType($this->liability_type);
    }

    /** Same rule as isRevolving(), usable before a model instance exists (e.g. on a not-yet-persisted validated payload). */
    public static function isRevolvingType(string $liabilityType): bool
    {
        return $liabilityType !== 'mortgage';
    }

    public function currentBalance(): float
    {
        return $this->latestBalance ? (float) $this->latestBalance->balance : 0.0;
    }

    /**
     * Unrounded monthly interest accrual on the current balance at the stored APR.
     *
     * FLAGGED, not fixed: a credit-card CashAccount computes the identical formula
     * independently in resources/views/cash-accounts/show.blade.php (it isn't a
     * Liability row, so it can't call this method) — the debt-payoff/allocator
     * calculators only ever see Liability, so a credit card tracked purely as a
     * CashAccount is invisible to them. Reconciling the two "debt with an APR" models
     * is a product decision (e.g. should every credit-card CashAccount imply a
     * shadow Liability?), not a mechanical dedup — left as-is pending that decision.
     */
    public function monthlyInterest(): float
    {
        return $this->currentBalance() * ((float) ($this->interest_rate ?? 0) / 100 / 12);
    }

    public function toBackupArray(): array
    {
        return [
            'name'            => $this->name,
            'liability_type'  => $this->liability_type,
            'interest_rate'   => $this->interest_rate !== null ? (float) $this->interest_rate : null,
            'minimum_payment' => $this->minimum_payment !== null ? (float) $this->minimum_payment : null,
            'currency'        => $this->currency,
            'notes'           => $this->notes,
            'balances'        => $this->balances->map(fn ($b) => $b->toBackupArray())->values(),
        ];
    }
}
