<?php

namespace App\Http\Controllers;

use App\Services\EmergencyFundService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmergencyFundController extends Controller
{
    public function __invoke(Request $request, EmergencyFundService $emergencyFund): View
    {
        return view('emergency-fund', $emergencyFund->compute($request->user()));
    }
}
