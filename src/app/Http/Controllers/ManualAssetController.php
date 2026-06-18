<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Models\AssetPrice;
use App\Models\ManualAsset;
use App\Models\Portfolio;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManualAssetController extends Controller
{
    public function index(Request $request, Portfolio $portfolio): View
    {
        $this->authorize('update', $portfolio);

        $manualAssets = $portfolio->manualAssets()->with('latestValuation')->orderBy('name')->get();

        return view('manual-assets.index', compact('portfolio', 'manualAssets'));
    }

    public function create(Request $request, Portfolio $portfolio): View|RedirectResponse
    {
        $this->authorize('update', $portfolio);

        if ($portfolio->isClosed()) {
            return redirect()->route('portfolios.show', $portfolio)
                ->with('error', 'This portfolio is closed. Reopen it to add assets.');
        }

        return view('manual-assets.create', [
            'portfolio'    => $portfolio,
            'assetClasses' => ManualAsset::ASSET_CLASSES,
        ]);
    }

    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        if ($portfolio->isClosed()) {
            return redirect()->route('portfolios.show', $portfolio)
                ->with('error', 'This portfolio is closed. Reopen it to add assets.');
        }

        $validated = $this->validatePayload($request);
        $validated['tracking_method'] ??= 'static';
        $validated['include_in_chart']    = $request->boolean('include_in_chart');
        $validated['include_in_invested'] = $request->boolean('include_in_invested');

        $asset = $portfolio->manualAssets()->create(array_merge(
            array_diff_key($validated, ['proxy_symbol' => null]),
            $this->resolveProxyData($validated)
        ));

        return redirect()->route('manual-assets.show', $asset)->with('success', 'Asset created.');
    }

    public function show(Request $request, ManualAsset $manualAsset): View
    {
        $this->authorize('view', $manualAsset);

        $manualAsset->load([
            'portfolio',
            'valuations' => fn ($q) => $q->orderByDesc('valued_at'),
            'latestValuation',
            'proxyAsset.latestPrice',
            'liabilities.latestBalance',
        ]);

        return view('manual-assets.show', [
            'manualAsset'  => $manualAsset,
            'assetClasses' => ManualAsset::ASSET_CLASSES,
        ]);
    }

    public function edit(Request $request, ManualAsset $manualAsset): View
    {
        $this->authorize('update', $manualAsset);

        $manualAsset->load(['portfolio', 'proxyAsset']);

        return view('manual-assets.edit', [
            'manualAsset'  => $manualAsset,
            'portfolio'    => $manualAsset->portfolio,
            'assetClasses' => ManualAsset::ASSET_CLASSES,
        ]);
    }

    public function update(Request $request, ManualAsset $manualAsset): RedirectResponse
    {
        $this->authorize('update', $manualAsset);

        $validated = $this->validatePayload($request);
        $validated['tracking_method'] ??= 'static';
        $validated['include_in_chart']    = $request->boolean('include_in_chart');
        $validated['include_in_invested'] = $request->boolean('include_in_invested');

        $manualAsset->update(array_merge(
            array_diff_key($validated, ['proxy_symbol' => null]),
            $this->resolveProxyData($validated)
        ));

        return redirect()->route('manual-assets.show', $manualAsset)->with('success', 'Asset updated.');
    }

    public function destroy(Request $request, ManualAsset $manualAsset): RedirectResponse
    {
        $this->authorize('delete', $manualAsset);

        $portfolioId = $manualAsset->portfolio_id;
        $manualAsset->delete();

        return redirect()
            ->route('portfolios.manual-assets.index', $portfolioId)
            ->with('success', 'Asset deleted.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'                => ['required', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'asset_class'         => ['required', 'in:'.implode(',', array_keys(ManualAsset::ASSET_CLASSES))],
            'cost_basis'          => ['nullable', 'numeric', 'min:0'],
            'currency'            => ['required', 'string', 'size:3'],
            'include_in_chart'    => ['boolean'],
            'include_in_invested' => ['boolean'],
            'tracking_method'     => ['nullable', 'in:static,proxy_ticker'],
            'proxy_symbol'        => ['required_if:tracking_method,proxy_ticker', 'nullable', 'string', 'max:20'],
            'anchor_value'        => ['required_if:tracking_method,proxy_ticker', 'nullable', 'numeric', 'gt:0'],
            'anchor_date'         => ['required_if:tracking_method,proxy_ticker', 'nullable', 'date'],
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
            ->where('recorded_at', '<=', $validated['anchor_date'].' 23:59:59')
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
