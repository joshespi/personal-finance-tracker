<?php

namespace App\Http\Controllers;

use App\Services\AllocatorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllocatorController extends Controller
{
    public function __invoke(Request $request, AllocatorService $allocator): View
    {
        $amount = null;
        if ($request->has('amount')) {
            $request->validate(['amount' => ['required', 'numeric', 'gt:0', 'max:10000000']]);
            $amount = round((float) $request->input('amount'), 2);
        }

        return view('allocator', $allocator->compute($request->user(), $amount));
    }
}
