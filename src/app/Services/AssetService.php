<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Models\Asset;

class AssetService
{
    /** Canonical form for ticker symbols — apply before storing or comparing them. */
    public static function normalizeSymbol(string $symbol): string
    {
        return strtoupper(trim($symbol));
    }

    public static function findOrCreateBySymbol(string $symbol, string|AssetType $assetType): Asset
    {
        $symbol = self::normalizeSymbol($symbol);
        $type   = $assetType instanceof AssetType ? $assetType->value : $assetType;

        return Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol, 'asset_type' => $type]
        );
    }
}
