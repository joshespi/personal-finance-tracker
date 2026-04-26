<?php

namespace App\Http\Controllers;

use App\Services\RealizedGainService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function transactions(Request $request): StreamedResponse
    {
        $user = $request->user();

        $transactions = \App\Models\Transaction::with(['asset', 'portfolio'])
            ->whereIn('portfolio_id', $user->portfolios()->pluck('id'))
            ->orderBy('transacted_at')
            ->get();

        return response()->streamDownload(function () use ($transactions) {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['Date', 'Portfolio', 'Symbol', 'Type', 'Quantity', 'Price Per Unit', 'Fees', 'Currency', 'Total']);

            foreach ($transactions as $t) {
                fputcsv($out, [
                    $t->transacted_at->format('Y-m-d'),
                    $t->portfolio->name,
                    $t->asset->symbol,
                    $t->type,
                    $t->quantity,
                    $t->price_per_unit,
                    $t->fees,
                    $t->currency,
                    round((float) $t->quantity * (float) $t->price_per_unit + (float) $t->fees, 2),
                ]);
            }

            fclose($out);
        }, 'transactions-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function realizedGains(Request $request): StreamedResponse
    {
        $user       = $request->user();
        $service    = new RealizedGainService();
        $portfolios = $user->portfolios()->with('transactions.asset')->get();

        $allLots = collect();
        foreach ($portfolios as $portfolio) {
            $result  = $service->compute($portfolio);
            $allLots = $allLots->concat($result['lots']->map(fn ($l) => array_merge($l, ['portfolio' => $portfolio])));
        }

        $allLots = $allLots->sortBy('sell_date');

        return response()->streamDownload(function () use ($allLots) {
            $out = fopen('php://output', 'w');

            fputcsv($out, [
                'Symbol', 'Portfolio', 'Quantity',
                'Buy Date', 'Sell Date', 'Days Held', 'Term',
                'Cost Basis', 'Proceeds', 'Gain / Loss',
            ]);

            foreach ($allLots as $lot) {
                fputcsv($out, [
                    $lot['asset']->symbol,
                    $lot['portfolio']->name,
                    $lot['quantity'],
                    $lot['buy_date']->format('Y-m-d'),
                    $lot['sell_date']->format('Y-m-d'),
                    $lot['holding_days'],
                    $lot['holding_days'] >= 365 ? 'Long-term' : 'Short-term',
                    $lot['cost_basis'],
                    $lot['proceeds'],
                    $lot['gain'],
                ]);
            }

            fclose($out);
        }, 'realized-gains-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
