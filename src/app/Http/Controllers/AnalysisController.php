<?php

namespace App\Http\Controllers;

use App\Services\BudgetRuleService;
use App\Services\CashflowService;
use App\Services\SpendingTrendsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalysisController extends Controller
{
    public function __invoke(Request $request, BudgetRuleService $budgetRule, CashflowService $cashflow, SpendingTrendsService $trends): View
    {
        return match ($request->input('tab', 'cashflow')) {
            'trends'      => $this->trends($request, $trends),
            'budget-rule' => $this->budgetRule($request, $budgetRule),
            default       => $this->cashflow($request, $cashflow),
        };
    }

    private function cashflow(Request $request, CashflowService $cashflow): View
    {
        $data = $cashflow->compute($request->user(), $request->input('month'));

        ['prevMonth' => $prevMonthParam, 'nextMonth' => $nextMonthParam, 'isCurrentMonth' => $isCurrentMonth] = $this->monthNav($data['month']);

        return view('analysis', [
            'tab'            => 'cashflow',
            'month'          => $data['month'],
            'income'         => $data['income'],
            'totalSpent'     => $data['totalSpent'],
            'net'            => $data['net'],
            'incomeRows'     => $data['incomeRows'],
            'envelopeRows'   => $data['envelopeRows'],
            'history'        => $data['history'],
            'prevMonthParam' => $prevMonthParam,
            'nextMonthParam' => $nextMonthParam,
            'isCurrentMonth' => $isCurrentMonth,
        ]);
    }

    private function trends(Request $request, SpendingTrendsService $trends): View
    {
        return view('analysis', [
            'tab' => 'trends',
            ...$trends->compute($request->user(), $request->input('months')),
        ]);
    }

    private function budgetRule(Request $request, BudgetRuleService $service): View
    {
        return view('analysis', [
            'tab'  => 'budget-rule',
            'data' => $service->compute($request->user()),
        ]);
    }
}
