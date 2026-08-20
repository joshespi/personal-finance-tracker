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

    /**
     * `id` breaks ties on recorded_at. Several balances can share a date — a scheduled
     * payment materializing on a day the user also recorded a manual balance — and a
     * single-column latestOfMany() leaves which one wins up to the database.
     */
    public function latestBalance(): HasOne
    {
        return $this->hasOne(LiabilityBalance::class)->ofMany([
            'recorded_at' => 'max',
            'id'          => 'max',
        ]);
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

    /**
     * The share of a payment that actually reaches the loan — the inverse of
     * totalMonthlyPayment(), kept beside it so the escrow convention has one home.
     * Escrow is stripped back out because it funds the escrow account, not the loan.
     */
    public function principalAndInterestPortion(float $payment): float
    {
        return max(0.0, $payment - (float) ($this->escrow_amount ?? 0));
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
     * A credit-card CashAccount computes the identical formula independently (it isn't a
     * Liability row, so it can't call this method) — see User::creditCardDebts(), which is
     * what now feeds those accounts into DebtPayoffService and AllocatorService alongside
     * Liability rows. The two models still aren't unified; whether a credit-card CashAccount
     * should imply a shadow Liability remains a product decision, not a mechanical dedup.
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
