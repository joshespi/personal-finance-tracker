<?php

namespace App\Services;

use App\Enums\AssetType;
use App\Models\Asset;

class AssetService
{
    public static function findOrCreateBySymbol(string $symbol, string|AssetType $assetType): Asset
    {
        $symbol = strtoupper(trim($symbol));
        $type   = $assetType instanceof AssetType ? $assetType->value : $assetType;

        return Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol, 'asset_type' => $type]
        );
    }
}
