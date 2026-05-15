<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Models\AssetPrice;
use App\Services\AssetService;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualAssetController extends Controller
{
    public const ASSET_CLASSES = [
        'real_estate' => 'Real Estate',
        'stock'       => 'Stocks / Index Fund',
        'bond'        => 'Bond Fund',
        'crypto'      => 'Crypto Fund',
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

        $validated = $this->validatePayload($request);
        $validated['tracking_method'] ??= 'static';

        $asset = $portfolio->manualAssets()->create(array_merge(
            array_diff_key($validated, ['proxy_symbol' => null]),
            $this->resolveProxyData($validated)
        ));

        return redirect()->route('manual-assets.show', $asset)->with('success', 'Asset created.');
    }

    public function show(Request $request, ManualAsset $manualAsset): View
    {
        abort_unless($manualAsset->portfolio->user_id === $request->user()->id, 403);

        $manualAsset->load([
            'portfolio',
            'valuations'            => fn ($q) => $q->orderByDesc('valued_at'),
            'latestValuation',
            'proxyAsset.latestPrice',
            'liabilities.latestBalance',
        ]);

        return view('manual-assets.show', [
            'manualAsset'  => $manualAsset,
            'assetClasses' => self::ASSET_CLASSES,
        ]);
    }

    public function edit(Request $request, ManualAsset $manualAsset): View
    {
        abort_unless($manualAsset->portfolio->user_id === $request->user()->id, 403);

        $manualAsset->load(['portfolio', 'proxyAsset']);

        return view('manual-assets.edit', [
            'manualAsset'  => $manualAsset,
            'portfolio'    => $manualAsset->portfolio,
            'assetClasses' => self::ASSET_CLASSES,
        ]);
    }

    public function update(Request $request, ManualAsset $manualAsset): RedirectResponse
    {
        abort_unless($manualAsset->portfolio->user_id === $request->user()->id, 403);

        $validated = $this->validatePayload($request);
        $validated['tracking_method'] ??= 'static';

        $manualAsset->update(array_merge(
            array_diff_key($validated, ['proxy_symbol' => null]),
            $this->resolveProxyData($validated)
        ));

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

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'             => ['required', 'string', 'max:200'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'asset_class'      => ['required', 'in:' . implode(',', array_keys(self::ASSET_CLASSES))],
            'cost_basis'       => ['nullable', 'numeric', 'min:0'],
            'currency'         => ['required', 'string', 'size:3'],
            'tracking_method'  => ['nullable', 'in:static,proxy_ticker'],
            'proxy_symbol'     => ['required_if:tracking_method,proxy_ticker', 'nullable', 'string', 'max:20'],
            'anchor_value'     => ['required_if:tracking_method,proxy_ticker', 'nullable', 'numeric', 'gt:0'],
            'anchor_date'      => ['required_if:tracking_method,proxy_ticker', 'nullable', 'date'],
        ]);
    }

    private function resolveProxyData(array $validated): array
    {
        if (($validated['tracking_method'] ?? 'static') !== 'proxy_ticker') {
            return [
                'proxy_asset_id'          => null,
                'anchor_value'            => null,
                'anchor_date'             => null,
                'anchor_synthetic_shares' => null,
            ];
        }

        $symbol     = strtoupper(trim($validated['proxy_symbol']));
        $proxyAsset = AssetService::findOrCreateBySymbol($symbol, AssetType::Stock);

        $proxyPrice = AssetPrice::where('asset_id', $proxyAsset->id)
            ->where('recorded_at', '<=', $validated['anchor_date'] . ' 23:59:59')
            ->orderByDesc('recorded_at')
            ->value('price');

        return [
            'proxy_asset_id'          => $proxyAsset->id,
            'anchor_value'            => $validated['anchor_value'],
            'anchor_date'             => $validated['anchor_date'],
            'anchor_synthetic_shares' => ($proxyPrice && $proxyPrice > 0)
                ? (float) $validated['anchor_value'] / (float) $proxyPrice
                : null,
        ];
    }
}
