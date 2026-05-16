<?php

namespace App\Enums;

enum PriceSource: string
{
    case Finnhub   = 'finnhub';
    case CoinGecko = 'coingecko';

    public function label(): string
    {
        return match ($this) {
            self::Finnhub   => 'Finnhub',
            self::CoinGecko => 'CoinGecko',
        };
    }
}
