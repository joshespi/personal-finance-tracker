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

        $allDividends = Transaction::whereIn('portfolio_id', $portfolioIds)
            ->where('type', TransactionType::Dividend->value)
            ->with('asset')
            ->orderBy('transacted_at')
            ->get();

        $years = $allDividends
            ->map(fn ($t) => (int) $t->transacted_at->format('Y'))
            ->unique()->sort()->values()->all();

        $selectedYear = $request->integer('year', now()->year);
        if (! in_array($selectedYear, $years, true) && count($years) > 0) {
            $selectedYear = last($years);
        }

        $yearDividends = $allDividends->filter(
            fn ($t) => (int) $t->transacted_at->format('Y') === $selectedYear
        );

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
        $allTimeTotal  = round($allDividends->sum(fn ($t) => $t->dividendValue()), 2);

        return view('dividends', compact(
            'years', 'selectedYear', 'byAsset', 'byMonth',
            'totalIncome', 'totalPayments', 'totalTickers', 'allTimeTotal'
        ));
    }
}
