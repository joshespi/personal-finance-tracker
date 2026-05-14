<?php

namespace App\Http\Controllers;

use App\Services\DebtPayoffService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DebtPayoffController extends Controller
{
    public function __invoke(Request $request, DebtPayoffService $service): View
    {
        return view('debt-payoff', ['data' => $service->compute($request->user())]);
    }
}
