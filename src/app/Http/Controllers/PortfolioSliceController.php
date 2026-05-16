<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\PortfolioSlice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PortfolioSliceController extends Controller
{
    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'symbol'     => ['required', 'string', 'max:20', Rule::exists('assets', 'symbol')],
            'target_pct' => ['required', 'numeric', 'min:0.01', 'max:100'],
        ]);

        $asset = Asset::where('symbol', strtoupper($data['symbol']))->firstOrFail();

        $portfolio->slices()->updateOrCreate(
            ['asset_id' => $asset->id],
            ['target_pct' => $data['target_pct']],
        );

        return back()->with('success', 'Slice saved.');
    }

    public function destroy(Request $request, Portfolio $portfolio, PortfolioSlice $slice): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);
        abort_unless($slice->portfolio_id === $portfolio->id, 403);

        $slice->delete();

        return back()->with('success', 'Slice removed.');
    }
}
