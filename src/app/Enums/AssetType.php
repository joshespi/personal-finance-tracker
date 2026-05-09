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
}
