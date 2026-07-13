<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioSlice;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PortfolioSliceController extends Controller
{
    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        $request->merge(['symbol' => AssetService::normalizeSymbol((string) $request->input('symbol'))]);

        $data = $request->validate([
            'symbol'     => ['required', 'string', 'max:20'],
            'target_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $asset = Asset::where('symbol', $data['symbol'])->first();

        if (! $asset) {
            // Fetch-then-check instead of Rule::exists so this stays one query, while
            // still throwing a real ValidationException (422/session-errors, matching
            // every other "entity must exist" endpoint) instead of a bare redirect.
            throw ValidationException::withMessages(['symbol' => 'The selected symbol is invalid.']);
        }

        $portfolio->slices()->updateOrCreate(
            ['asset_id' => $asset->id],
            ['target_pct' => $data['target_pct']],
        );

        return back()->with('success', 'Slice saved.');
    }

    public function destroy(Request $request, Portfolio $portfolio, PortfolioSlice $slice): RedirectResponse
    {
        $this->authorize('update', $portfolio);
        abort_unless($slice->portfolio_id === $portfolio->id, 403);

        $slice->delete();

        return back()->with('success', 'Slice removed.');
    }
}
