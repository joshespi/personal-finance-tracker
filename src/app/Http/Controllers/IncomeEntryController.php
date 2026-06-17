<?php

namespace App\Http\Controllers;

use App\Models\IncomeEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncomeEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'amount'             => ['required', 'numeric', 'gt:0'],
            'description'        => ['nullable', 'string', 'max:500'],
            'occurred_at'        => ['required', 'date'],
            'income_category_id' => [
                'nullable', 'integer',
                Rule::exists('income_categories', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $request->user()->incomeEntries()->create($validated);

        return redirect()->route('ready-to-assign')->with('success', 'Income recorded.');
    }

    public function destroy(Request $request, IncomeEntry $incomeEntry): RedirectResponse
    {
        $this->authorize('delete', $incomeEntry);

        $incomeEntry->delete();

        return redirect()->route('ready-to-assign')->with('success', 'Income entry removed.');
    }
}
