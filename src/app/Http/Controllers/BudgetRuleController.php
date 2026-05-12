<?php

namespace App\Http\Controllers;

use App\Services\BudgetRuleService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BudgetRuleController extends Controller
{
    public function __invoke(Request $request, BudgetRuleService $service): View
    {
        return view('budget-rule', ['data' => $service->compute($request->user())]);
    }
}
