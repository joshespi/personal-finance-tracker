<?php

namespace App\Http\Controllers;

use App\Models\ManualAsset;
use App\Models\ManualValuation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ManualValuationController extends Controller
{
    public function store(Request $request, ManualAsset $manualAsset): RedirectResponse
    {
        $this->authorize('update', $manualAsset);

        $validated = $request->validate([
            'value'     => ['required', 'numeric', 'gte:0'],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'valued_at' => ['required', 'date'],
        ]);

        $manualAsset->valuations()->create($validated);

        return redirect()->route('manual-assets.show', $manualAsset)->with('success', 'Valuation recorded.');
    }

    public function destroy(Request $request, ManualValuation $valuation): RedirectResponse
    {
        $this->authorize('delete', $valuation);

        $assetId = $valuation->manual_asset_id;
        $valuation->delete();

        return redirect()->route('manual-assets.show', $assetId)->with('success', 'Valuation deleted.');
    }
}
