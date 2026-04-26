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
            'asset_type' => ['required', 'in:stock,crypto'],
        ]);

        $asset->update(['asset_type' => $request->input('asset_type')]);

        return back()->with('success', "{$asset->symbol} reclassified as " . ucfirst($request->input('asset_type')) . '.');
    }
}
