<?php

namespace App\Enums;

enum ScheduledTransactionType: string
{
    case EnvelopeFund   = 'envelope_fund';
    case EnvelopeSpend  = 'envelope_spend';
    case CashDeposit    = 'cash_deposit';
    case CashWithdrawal = 'cash_withdrawal';
    // Covers any liability payment (credit card, auto loan, …); the backing value
    // predates the generalization and is kept to avoid migrating existing rows.
    case LiabilityPayment = 'mortgage_payment';

    public function label(): string
    {
        return match ($this) {
            self::EnvelopeFund     => 'Fund envelope',
            self::EnvelopeSpend    => 'Envelope spend',
            self::CashDeposit      => 'Cash deposit',
            self::CashWithdrawal   => 'Cash withdrawal',
            self::LiabilityPayment => 'Liability payment',
        };
    }

    /** Whether this type is offered in the create/edit form's Type dropdown. Liability payments
     *  are system-managed (LiabilityController::syncSchedule) — never manually created. */
    public function userSelectable(): bool
    {
        return $this !== self::LiabilityPayment;
    }

    /** Whether this schedule needs an envelope selected. */
    public function needsEnvelope(): bool
    {
        return match ($this) {
            self::EnvelopeFund, self::EnvelopeSpend => true,
            default                                 => false,
        };
    }

    /** Whether the cash-account field is shown at all for this type. */
    public function showsCashAccount(): bool
    {
        return $this !== self::LiabilityPayment;
    }

    /** Whether the cash-account field is required (vs. an optional pairing) when shown. */
    public function requiresCashAccount(): bool
    {
        return match ($this) {
            self::CashDeposit, self::CashWithdrawal, self::EnvelopeSpend => true,
            default                                                      => false,
        };
    }

    /** Whether this scheduled entry increases a balance (deposit/fund) vs. draws it down. */
    public function isInflow(): bool
    {
        return match ($this) {
            self::CashDeposit, self::EnvelopeFund => true,
            default                               => false,
        };
    }

    /** Flat array of string values — for Eloquent whereIn, Rule::in, validation. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Backing values for types considered inflows — for whereIn/orderByRaw IN() clauses. */
    public static function inflowValues(): array
    {
        return self::subsetValues(fn (self $t) => $t->isInflow());
    }

    /** Backing values a user may submit when creating a schedule — excludes system-managed types. */
    public static function userSelectableValues(): array
    {
        return self::subsetValues(fn (self $t) => $t->userSelectable());
    }

    /** Backing values for types needing an envelope — for the create/edit form's Alpine state. */
    public static function envelopeValues(): array
    {
        return self::subsetValues(fn (self $t) => $t->needsEnvelope());
    }

    /** Backing values for types that show the cash-account field — for the form's Alpine state. */
    public static function cashAccountVisibleValues(): array
    {
        return self::subsetValues(fn (self $t) => $t->showsCashAccount());
    }

    /** Backing values for types requiring a cash account — for the form's Alpine state. */
    public static function cashAccountRequiredValues(): array
    {
        return self::subsetValues(fn (self $t) => $t->requiresCashAccount());
    }

    private static function subsetValues(callable $filter): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), $filter)
        ));
    }
}
