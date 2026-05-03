<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function reclassify(Request $request, Asset $asset): RedirectResponse
    {
        $request->validate([
            'asset_type' => ['required', 'in:stock,crypto,real_estate'],
        ]);

        $type = $request->input('asset_type');
        $asset->update(['asset_type' => $type]);

        $label = match ($type) {
            'real_estate' => 'Real Estate',
            default       => ucfirst($type),
        };

        return back()->with('success', "{$asset->symbol} reclassified as {$label}.");
    }
}
