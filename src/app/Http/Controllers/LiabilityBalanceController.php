<?php

namespace App\Http\Controllers;

use App\Models\Liability;
use App\Models\LiabilityBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LiabilityBalanceController extends Controller
{
    public function store(Request $request, Liability $liability): RedirectResponse
    {
        abort_unless($liability->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'balance'     => ['required', 'numeric', 'gte:0'],
            'notes'       => ['nullable', 'string', 'max:1000'],
            'recorded_at' => ['required', 'date'],
        ]);

        $liability->balances()->create($validated);

        return redirect()->route('liabilities.show', $liability)->with('success', 'Balance recorded.');
    }

    public function destroy(Request $request, LiabilityBalance $balance): RedirectResponse
    {
        abort_unless($balance->liability->user_id === $request->user()->id, 403);

        $liabilityId = $balance->liability_id;
        $balance->delete();

        return redirect()->route('liabilities.show', $liabilityId)->with('success', 'Balance deleted.');
    }
}
