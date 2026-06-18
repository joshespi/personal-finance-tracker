<?php

namespace App\Http\Controllers;

use App\Models\Envelope;
use App\Services\CashflowService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashflowController extends Controller
{
    public function __invoke(Request $request, CashflowService $cashflow): View
    {
        $data = $cashflow->compute($request->user(), $request->input('month'));

        $envelopeGroups = collect(Envelope::CATEGORY_ORDER)
            ->mapWithKeys(fn ($cat) => [
                $cat => $data['envelopeRows']
                    ->filter(fn ($r) => $r['envelope']->category() === $cat)
                    ->values(),
            ])
            ->filter(fn ($rows) => $rows->isNotEmpty());

        ['prevMonth' => $prevMonth, 'nextMonth' => $nextMonth, 'isCurrentMonth' => $isCurrentMonth] = $this->monthNav($data['month']);

        return view('cashflow', [
            'month'          => $data['month'],
            'income'         => $data['income'],
            'totalSpent'     => $data['totalSpent'],
            'net'            => $data['net'],
            'incomeRows'     => $data['incomeRows'],
            'envelopeGroups' => $envelopeGroups,
            'history'        => $data['history'],
            'prevMonth'      => $prevMonth,
            'nextMonth'      => $nextMonth,
            'isCurrentMonth' => $isCurrentMonth,
        ]);
    }
}
