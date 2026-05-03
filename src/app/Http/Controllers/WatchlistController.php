<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\WatchlistItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WatchlistController extends Controller
{
    public function index(Request $request): View
    {
        $items = $request->user()
            ->watchlistItems()
            ->orderBy('symbol')
            ->get();

        // Attach current prices from assets table
        $symbols = $items->pluck('symbol')->all();
        $assets  = Asset::with('latestPrice')
            ->whereIn('symbol', $symbols)
            ->get()
            ->keyBy('symbol');

        $items = $items->map(function ($item) use ($assets) {
            $asset               = $assets->get($item->symbol);
            $item->current_price = $asset?->latestPrice?->price;
            $item->asset_name    = $asset?->name ?? $item->name ?? $item->symbol;

            if ($item->current_price !== null && $item->target_price !== null) {
                $item->pct_to_target = round(
                    ((float) $item->target_price - (float) $item->current_price) / (float) $item->current_price * 100,
                    2
                );
            } else {
                $item->pct_to_target = null;
            }

            return $item;
        });

        return view('watchlist.index', compact('items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'symbol'       => ['required', 'string', 'max:20'],
            'asset_type'   => ['required', 'in:stock,crypto,real_estate'],
            'target_price' => ['nullable', 'numeric', 'gt:0'],
            'notes'        => ['nullable', 'string', 'max:500'],
        ]);

        $symbol = strtoupper(trim($validated['symbol']));

        $asset = Asset::where('symbol', $symbol)->first();

        $request->user()->watchlistItems()->updateOrCreate(
            ['symbol' => $symbol],
            [
                'name'         => $asset?->name ?? $symbol,
                'asset_type'   => $validated['asset_type'],
                'target_price' => $validated['target_price'] ?? null,
                'notes'        => $validated['notes'] ?? null,
            ]
        );

        ActivityLog::record('watchlist.added', null, ['symbol' => $symbol]);

        return redirect()->route('watchlist.index')->with('success', "{$symbol} added to watchlist.");
    }

    public function destroy(Request $request, WatchlistItem $watchlistItem): RedirectResponse
    {
        abort_unless($watchlistItem->user_id === $request->user()->id, 403);

        $symbol = $watchlistItem->symbol;

        ActivityLog::record('watchlist.removed', null, ['symbol' => $symbol]);

        $watchlistItem->delete();

        return redirect()->route('watchlist.index')->with('success', "{$symbol} removed from watchlist.");
    }
}
