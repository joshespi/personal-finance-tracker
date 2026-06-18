<?php

namespace App\Http\Controllers;

use App\Services\SpendingTrendsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SpendingTrendsController extends Controller
{
    public function __invoke(Request $request, SpendingTrendsService $trends): View
    {
        return view('spending-trends', $trends->compute($request->user(), $request->input('months')));
    }
}
