<?php

namespace App\Http\Controllers;

use App\Services\NetWorthService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, NetWorthService $netWorth): View
    {
        return view('dashboard', $netWorth->compute($request->user()));
    }
}
