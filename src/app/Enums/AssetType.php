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
        return match ($this) {
            self::Crypto     => 'crypto',
            self::RealEstate => 'real_estate',
            self::Bond       => 'bond',
            self::Stock      => 'stock',
        };
    }
}
