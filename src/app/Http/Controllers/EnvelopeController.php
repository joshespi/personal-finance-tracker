<?php

namespace App\Http\Controllers;

use App\Models\Envelope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnvelopeController extends Controller
{
    public function index(Request $request): View
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth   = now()->endOfMonth();

        $envelopes = $request->user()
            ->envelopes()
            ->withSum(['transactions as funds_total' => fn ($q) => $q->where('type', 'fund')], 'amount')
            ->withSum(['transactions as spends_total' => fn ($q) => $q->where('type', 'spend')], 'amount')
            ->withSum([
                'transactions as month_spend_total' => fn ($q) => $q
                    ->where('type', 'spend')
                    ->whereBetween('occurred_at', [$startOfMonth, $endOfMonth]),
            ], 'amount')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function ($e) {
                $e->current_balance  = (float) ($e->funds_total ?? 0) - (float) ($e->spends_total ?? 0);
                $e->spent_this_month = (float) ($e->month_spend_total ?? 0);
            });

        $totalBalance       = $envelopes->sum('current_balance');
        $totalMonthlyTarget = $envelopes->sum(fn ($e) => (float) ($e->monthly_target ?? 0));
        $totalSpentMonth    = $envelopes->sum('spent_this_month');

        return view('envelopes.index', compact('envelopes', 'totalBalance', 'totalMonthlyTarget', 'totalSpentMonth'));
    }

    public function create(): View
    {
        return view('envelopes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $envelope = $request->user()->envelopes()->create($validated);

        return redirect()->route('envelopes.show', $envelope)->with('success', 'Envelope created.');
    }

    public function show(Request $request, Envelope $envelope): View
    {
        abort_unless($envelope->user_id === $request->user()->id, 403);

        $envelope->load(['transactions' => fn ($q) => $q->orderByDesc('occurred_at')->orderByDesc('id')]);
        $envelope->current_balance = $envelope->balance();
        $envelope->spent_this_month = $envelope->spentInMonth();

        return view('envelopes.show', compact('envelope'));
    }

    public function edit(Request $request, Envelope $envelope): View
    {
        abort_unless($envelope->user_id === $request->user()->id, 403);

        return view('envelopes.edit', compact('envelope'));
    }

    public function update(Request $request, Envelope $envelope): RedirectResponse
    {
        abort_unless($envelope->user_id === $request->user()->id, 403);

        $validated = $this->validatePayload($request);
        $envelope->update($validated);

        return redirect()->route('envelopes.show', $envelope)->with('success', 'Envelope updated.');
    }

    public function destroy(Request $request, Envelope $envelope): RedirectResponse
    {
        abort_unless($envelope->user_id === $request->user()->id, 403);

        $envelope->delete();

        return redirect()->route('envelopes.index')->with('success', 'Envelope deleted.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:200'],
            'monthly_target' => ['nullable', 'numeric', 'gte:0'],
            'color'          => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order'     => ['nullable', 'integer', 'gte:0'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
