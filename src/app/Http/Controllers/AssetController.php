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
        // Assets are global; require the user to hold this asset in one of their portfolios.
        $portfolioIds = $request->user()->portfolios()->pluck('id');
        abort_unless(
            $asset->transactions()->whereIn('portfolio_id', $portfolioIds)->exists(),
            403
        );

        $request->validate([
            'asset_type' => ['required', Rule::enum(AssetType::class)],
        ]);

        $type = $request->input('asset_type');
        $asset->update(['asset_type' => $type]);

        $label = AssetType::from($type)->label();

        return back()->with('success', "{$asset->symbol} reclassified as {$label}.");
    }
}
