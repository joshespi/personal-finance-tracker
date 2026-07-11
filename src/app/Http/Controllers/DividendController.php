<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DividendController extends Controller
{
    public function __invoke(Request $request): View
    {
        $portfolioIds = $request->user()->portfolios()->pluck('id');

        $dividendQuery = fn () => Transaction::whereIn('portfolio_id', $portfolioIds)
            ->where('type', TransactionType::Dividend->value);

        // Slim pass over all history for the year list and all-time total — no asset
        // eager-load, three columns. Per-year grouping stays PHP-side (no DATE_FORMAT)
        // so it works on both SQLite and MariaDB.
        $yearTotals = $dividendQuery()
            ->get(['transacted_at', 'quantity', 'price_per_unit'])
            ->groupBy(fn ($t) => (int) $t->transacted_at->format('Y'))
            ->map(fn ($g) => $g->sum(fn ($t) => $t->dividendValue()))
            ->sortKeys();

        $years        = $yearTotals->keys()->all();
        $allTimeTotal = round($yearTotals->sum(), 2);

        $selectedYear = $request->integer('year', now()->year);
        if (! in_array($selectedYear, $years, true) && count($years) > 0) {
            $selectedYear = last($years);
        }

        $yearDividends = $dividendQuery()
            ->whereBetween('transacted_at', [
                sprintf('%d-01-01 00:00:00', $selectedYear),
                sprintf('%d-12-31 23:59:59', $selectedYear),
            ])
            ->with('asset')
            ->orderBy('transacted_at')
            ->get();

        $byAsset = $yearDividends
            ->groupBy('asset_id')
            ->map(fn ($txns) => [
                'symbol'     => $txns->first()->asset->symbol,
                'asset_type' => $txns->first()->asset->asset_type,
                'payments'   => $txns->count(),
                'total'      => round($txns->sum(fn ($t) => $t->dividendValue()), 2),
            ])
            ->sortByDesc('total')
            ->values();

        // Month labels → total (PHP-side grouping; no DATE_FORMAT)
        $byMonth = [];
        for ($m = 1; $m <= 12; $m++) {
            $byMonth[sprintf('%d-%02d', $selectedYear, $m)] = 0.0;
        }
        foreach ($yearDividends as $t) {
            $key = $t->transacted_at->format('Y-m');
            if (array_key_exists($key, $byMonth)) {
                $byMonth[$key] += $t->dividendValue();
            }
        }
        $byMonth = array_map(fn ($v) => round($v, 2), $byMonth);

        $totalIncome   = round($yearDividends->sum(fn ($t) => $t->dividendValue()), 2);
        $totalPayments = $yearDividends->count();
        $totalTickers  = $byAsset->count();

        return view('dividends', compact(
            'years', 'selectedYear', 'byAsset', 'byMonth',
            'totalIncome', 'totalPayments', 'totalTickers', 'allTimeTotal'
        ));
    }
}
