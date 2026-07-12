<?php

namespace App\Http\Controllers;

use App\Services\RealizedGainService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TaxSummaryController extends Controller
{
    public function __invoke(Request $request, RealizedGainService $realizedGainService): View
    {
        $allLots = $realizedGainService->allLotsForUser($request->user());

        $byYear = $allLots
            ->groupBy(fn ($l) => $l['sell_date']->year)
            ->map(function ($lots, $year) {
                $short = $lots->filter(fn ($l) => $l['holding_days'] < 365);
                $long  = $lots->filter(fn ($l) => $l['holding_days'] >= 365);

                return [
                    'year'       => $year,
                    'short_gain' => round($short->sum('gain'), 2),
                    'long_gain'  => round($long->sum('gain'), 2),
                    'total_gain' => round($lots->sum('gain'), 2),
                    'short_lots' => $short->sortByDesc('sell_date')->values(),
                    'long_lots'  => $long->sortByDesc('sell_date')->values(),
                ];
            })
            ->sortKeysDesc()
            ->values();

        $selectedYear = (int) $request->input('year', now()->year);
        $yearDetail   = $byYear->firstWhere('year', $selectedYear);

        return view('tax.summary', compact('byYear', 'selectedYear', 'yearDetail'));
    }
}
