<?php

namespace App\Enums;

enum TransactionType: string
{
    case Buy           = 'buy';
    case Sell          = 'sell';
    case Dividend      = 'dividend';
    case StakingReward = 'staking_reward';
    case TransferIn    = 'transfer_in';
    case TransferOut   = 'transfer_out';

    public function label(): string
    {
        return match ($this) {
            self::Buy           => 'Buy',
            self::Sell          => 'Sell',
            self::Dividend      => 'Dividend',
            self::StakingReward => 'Staking Reward',
            self::TransferIn    => 'Transfer In',
            self::TransferOut   => 'Transfer Out',
        };
    }

    /** Adds units to a position: buys, incoming transfers, staking rewards. */
    public function isInflow(): bool
    {
        return match ($this) {
            self::Buy, self::TransferIn, self::StakingReward => true,
            default                                          => false,
        };
    }

    /** Removes units from a position: sells and outgoing transfers. */
    public function isOutflow(): bool
    {
        return match ($this) {
            self::Sell, self::TransferOut => true,
            default                       => false,
        };
    }

    /** Whether this type moves the unit count at all (everything except dividends). */
    public function affectsPosition(): bool
    {
        return $this !== self::Dividend;
    }

    public function isTransfer(): bool
    {
        return match ($this) {
            self::TransferIn, self::TransferOut => true,
            default                             => false,
        };
    }

    /** A real cash in/out flow (position change for cash) — excludes non-cash staking rewards. */
    public function isCashflow(): bool
    {
        return $this->affectsPosition() && $this !== self::StakingReward;
    }

    /** Tailwind badge classes for the transactions tables (green = adds value, red = removes). */
    public function badgeClasses(): string
    {
        return match (true) {
            $this === self::Dividend, $this->isInflow() => 'bg-green-100 dark:bg-green-900/50 text-green-800 dark:text-green-300',
            $this->isOutflow()                          => 'bg-red-100 dark:bg-red-900/50 text-red-800 dark:text-red-300',
        };
    }

    /** Flat array of string values — for Eloquent whereIn, Rule::in, validation. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Backing values for types that move the unit count (excludes dividends). */
    public static function positionValues(): array
    {
        return self::subsetValues(fn (self $t) => $t->affectsPosition());
    }

    /** Backing values for real cash flows (position changes excluding staking) — for whereIn. */
    public static function cashflowValues(): array
    {
        return self::subsetValues(fn (self $t) => $t->isCashflow());
    }

    /** [value => label] map for building <select> options. */
    public static function options(): array
    {
        return array_reduce(self::cases(), function (array $opts, self $t) {
            $opts[$t->value] = $t->label();

            return $opts;
        }, []);
    }

    private static function subsetValues(callable $filter): array
    {
        return array_values(array_map(
            fn (self $t) => $t->value,
            array_filter(self::cases(), $filter)
        ));
    }
}
