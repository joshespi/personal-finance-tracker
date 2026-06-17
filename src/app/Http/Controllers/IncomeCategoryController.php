<?php

namespace App\Http\Controllers;

use App\Models\IncomeCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IncomeCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $categories = $request->user()
            ->incomeCategories()
            ->withCount('cashTransactions')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('income-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('income-categories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $request->user()->incomeCategories()->create($validated);

        return redirect()->route('income-categories.index')->with('success', 'Income category created.');
    }

    public function edit(Request $request, IncomeCategory $incomeCategory): View
    {
        $this->authorize('update', $incomeCategory);

        return view('income-categories.edit', ['category' => $incomeCategory]);
    }

    public function update(Request $request, IncomeCategory $incomeCategory): RedirectResponse
    {
        $this->authorize('update', $incomeCategory);

        $incomeCategory->update($this->validatePayload($request, $incomeCategory->id));

        return redirect()->route('income-categories.index')->with('success', 'Income category updated.');
    }

    public function destroy(Request $request, IncomeCategory $incomeCategory): RedirectResponse
    {
        $this->authorize('delete', $incomeCategory);

        $incomeCategory->delete();

        return redirect()->route('income-categories.index')->with('success', 'Income category deleted.');
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('income_categories', 'name')
                    ->where('user_id', $request->user()->id)
                    ->ignore($ignoreId),
            ],
            'color'      => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'gte:0'],
        ], [
            'name.unique' => 'You already have an income category with that name.',
        ]);
    }
}
