<?php

namespace App\Http\Controllers;

use App\Models\ManualAsset;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualAssetController extends Controller
{
    public const ASSET_CLASSES = [
        'real_estate' => 'Real Estate',
        'vehicle'     => 'Vehicle',
        'collectible' => 'Collectible',
        'business'    => 'Business',
        'other'       => 'Other',
    ];

    public function index(Request $request, Portfolio $portfolio): View
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $manualAssets = $portfolio->manualAssets()->with('latestValuation')->orderBy('name')->get();

        return view('manual-assets.index', compact('portfolio', 'manualAssets'));
    }

    public function create(Request $request, Portfolio $portfolio): View
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        return view('manual-assets.create', [
            'portfolio'    => $portfolio,
            'assetClasses' => self::ASSET_CLASSES,
        ]);
    }

    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'asset_class' => ['required', 'in:' . implode(',', array_keys(self::ASSET_CLASSES))],
            'cost_basis'  => ['nullable', 'numeric', 'min:0'],
            'currency'    => ['required', 'string', 'size:3'],
        ]);

        $asset = $portfolio->manualAssets()->create($validated);

        return redirect()->route('manual-assets.show', $asset)->with('success', 'Asset created.');
    }

    public function show(Request $request, ManualAsset $manualAsset): View
    {
        abort_unless($manualAsset->portfolio->user_id === $request->user()->id, 403);

        $manualAsset->load([
            'portfolio',
            'valuations' => fn ($q) => $q->orderByDesc('valued_at'),
        ]);

        return view('manual-assets.show', [
            'manualAsset'  => $manualAsset,
            'assetClasses' => self::ASSET_CLASSES,
        ]);
    }

    public function edit(Request $request, ManualAsset $manualAsset): View
    {
        abort_unless($manualAsset->portfolio->user_id === $request->user()->id, 403);

        $manualAsset->load('portfolio');

        return view('manual-assets.edit', [
            'manualAsset'  => $manualAsset,
            'portfolio'    => $manualAsset->portfolio,
            'assetClasses' => self::ASSET_CLASSES,
        ]);
    }

    public function update(Request $request, ManualAsset $manualAsset): RedirectResponse
    {
        abort_unless($manualAsset->portfolio->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'asset_class' => ['required', 'in:' . implode(',', array_keys(self::ASSET_CLASSES))],
            'cost_basis'  => ['nullable', 'numeric', 'min:0'],
            'currency'    => ['required', 'string', 'size:3'],
        ]);

        $manualAsset->update($validated);

        return redirect()->route('manual-assets.show', $manualAsset)->with('success', 'Asset updated.');
    }

    public function destroy(Request $request, ManualAsset $manualAsset): RedirectResponse
    {
        abort_unless($manualAsset->portfolio->user_id === $request->user()->id, 403);

        $portfolioId = $manualAsset->portfolio_id;
        $manualAsset->delete();

        return redirect()
            ->route('portfolios.manual-assets.index', $portfolioId)
            ->with('success', 'Asset deleted.');
    }
}
