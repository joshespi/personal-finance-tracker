<?php

namespace App\Http\Controllers;

use App\Models\IncomeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IncomeEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount'      => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date'],
        ]);

        $request->user()->incomeEntries()->create($validated);

        return redirect()->route('ready-to-assign')->with('success', 'Income recorded.');
    }

    public function destroy(Request $request, IncomeEntry $incomeEntry): RedirectResponse
    {
        abort_unless($incomeEntry->user_id === $request->user()->id, 403);

        $incomeEntry->delete();

        return redirect()->route('ready-to-assign')->with('success', 'Income entry removed.');
    }
}
