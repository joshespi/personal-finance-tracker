<?php

namespace App\Http\Controllers;

use App\Services\ForecastService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForecastController extends Controller
{
    public function __invoke(Request $request, ForecastService $forecast): View
    {
        $user = $request->user();

        [$defaultStartNw, $defaultMonthlySavings] = $forecast->computeDefaults($user);

        $startingNw     = (float) $request->input('starting_nw', $defaultStartNw);
        $monthlySavings = (float) $request->input('monthly_savings', $defaultMonthlySavings);
        $annualReturn   = (float) $request->input('annual_return', 7.0);
        $inflationRate  = (float) $request->input('inflation_rate', 2.5);
        $years          = (int) $request->input('years', 30);
        $fireTarget     = $request->filled('fire_target') ? max(0, (float) $request->input('fire_target')) : null;

        $annualReturn  = max(0, min(30, $annualReturn));
        $inflationRate = max(0, min(20, $inflationRate));
        $years         = in_array($years, [10, 20, 30, 40, 50]) ? $years : 30;

        return view('forecast', [
            ...$forecast->compute($startingNw, $monthlySavings, $annualReturn, $inflationRate, $years, $fireTarget),
            'defaultStartNw'        => $defaultStartNw,
            'defaultMonthlySavings' => $defaultMonthlySavings,
        ]);
    }
}
