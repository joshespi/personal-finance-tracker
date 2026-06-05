<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Enums\PriceSource;
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
            'asset_type'   => ['sometimes', Rule::enum(AssetType::class)],
            'price_source' => ['sometimes', 'nullable', Rule::enum(PriceSource::class)],
        ]);

        if ($request->has('asset_type')) {
            $type = $request->input('asset_type');
            $request->user()->assetClassifications()->updateOrCreate(
                ['asset_id' => $asset->id],
                ['asset_type' => $type],
            );
            return back()->with('success', "{$asset->symbol} reclassified as " . AssetType::from($type)->label() . '.');
        }

        if ($request->has('price_source')) {
            // price_source drives the single shared price feed for this symbol, so it can't be per-user.
            abort_unless($request->user()->is_admin, 403);
            $source = $request->input('price_source') ?: null;
            $asset->update(['price_source' => $source]);
            $label = $source ? PriceSource::from($source)->label() : 'Auto';
            return back()->with('success', "{$asset->symbol} price feed set to {$label}.");
        }

        return back();
    }
}
