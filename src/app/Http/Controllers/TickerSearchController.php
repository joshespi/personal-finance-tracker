<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\CoinGeckoClient;
use App\Services\FinnhubClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TickerSearchController extends Controller
{
    public function __invoke(Request $request, FinnhubClient $finnhub, CoinGeckoClient $coinGecko): JsonResponse
    {
        $query     = trim($request->input('q', ''));
        $assetType = $request->input('type', 'stock');

        if (strlen($query) < 1) {
            return response()->json([]);
        }

        $local = Asset::where('symbol', 'like', strtoupper($query).'%')
            ->where('asset_type', $assetType)
            ->limit(5)
            ->get(['symbol', 'name', 'asset_type'])
            ->map(fn ($a) => [
                'symbol' => $a->symbol,
                'name'   => $a->name,
                'type'   => $a->asset_type,
                'source' => 'local',
            ]);

        if ($local->count() >= 5) {
            return response()->json($local->values());
        }

        $remote = match ($assetType) {
            'crypto' => $this->searchCrypto($query, $coinGecko),
            default  => $this->searchStock($query, $assetType, $finnhub),
        };

        // Merge local + remote, deduplicate by symbol, limit to 8
        $symbols  = $local->pluck('symbol')->map('strtoupper')->all();
        $combined = $local->concat(
            collect($remote)->filter(fn ($r) => ! in_array(strtoupper($r['symbol']), $symbols))
        )->take(8)->values();

        return response()->json($combined);
    }

    private function searchStock(string $query, string $resultType, FinnhubClient $finnhub): array
    {
        if (! $finnhub->configured()) {
            return [];
        }

        try {
            $response = $finnhub->search($query);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('result') ?? [])
                ->filter(fn ($r) => ($r['type'] ?? '') === 'Common Stock')
                ->take(6)
                ->map(fn ($r) => [
                    'symbol' => $r['symbol'],
                    'name'   => $r['description'],
                    'type'   => $resultType,
                    'source' => 'finnhub',
                ])
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::warning('Ticker search failed', ['error' => FinnhubClient::redact($e->getMessage())]);

            return [];
        }
    }

    private function searchCrypto(string $query, CoinGeckoClient $coinGecko): array
    {
        try {
            $response = $coinGecko->search($query);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('coins') ?? [])
                ->take(6)
                ->map(fn ($c) => [
                    'symbol' => strtoupper($c['symbol']),
                    'name'   => $c['name'],
                    'type'   => 'crypto',
                    'source' => 'coingecko',
                ])
                ->values()
                ->all();
        } catch (\Exception $e) {
            Log::warning('Crypto search failed', ['error' => $e->getMessage()]);

            return [];
        }
    }
}
