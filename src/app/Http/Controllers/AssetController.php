<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function reclassify(Request $request, Asset $asset): RedirectResponse
    {
        $request->validate([
            'asset_type' => ['required', Rule::enum(AssetType::class)],
        ]);

        $type = $request->input('asset_type');
        $asset->update(['asset_type' => $type]);

        $label = AssetType::from($type)->label();

        return back()->with('success', "{$asset->symbol} reclassified as {$label}.");
    }
}
