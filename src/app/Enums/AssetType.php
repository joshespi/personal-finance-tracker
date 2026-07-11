<?php

namespace App\Enums;

enum AssetType: string
{
    case Stock      = 'stock';
    case Crypto     = 'crypto';
    case RealEstate = 'real_estate';
    case Bond       = 'bond';

    public function label(): string
    {
        return match ($this) {
            self::Stock      => 'Stock',
            self::Crypto     => 'Crypto',
            self::RealEstate => 'Real Estate',
            self::Bond       => 'Bond',
        };
    }

    /** Flat array of string values — for Eloquent whereIn, Rule::in, etc. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Allocation bucket this type rolls up into for the net-worth pie/rebalancing. */
    public function allocationKey(): string
    {
        // Buckets are 1:1 with the backing values today; keep this seam for any
        // future type that should roll up into an existing bucket.
        return $this->value;
    }

    /** Plural display label for the allocation pie / rebalancing table. */
    public function allocationLabel(): string
    {
        return match ($this) {
            self::Stock      => 'Stocks',
            self::Crypto     => 'Crypto',
            self::RealEstate => 'Real Estate',
            self::Bond       => 'Bonds',
        };
    }

    /** Users-table column holding this type's global target allocation percent. */
    public function targetColumn(): string
    {
        return "target_{$this->value}_pct";
    }

    /** Chart color for this allocation bucket. */
    public function allocationColor(): string
    {
        return match ($this) {
            self::Stock      => '#6366f1',
            self::Crypto     => '#f97316',
            self::RealEstate => '#b45309',
            self::Bond       => '#eab308',
        };
    }
}
