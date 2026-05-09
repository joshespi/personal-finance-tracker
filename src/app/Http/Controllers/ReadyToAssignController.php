<?php

namespace App\Http\Controllers;

use App\Models\EnvelopeTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadyToAssignController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $envelopes = $user->envelopes()
            ->withSum(['transactions as funds_total' => fn ($q) => $q->where('type', 'fund')], 'amount')
            ->withSum(['transactions as spends_total' => fn ($q) => $q->where('type', 'spend')], 'amount')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function ($e) {
                $e->current_balance = (float) ($e->funds_total ?? 0) - (float) ($e->spends_total ?? 0);
            });

        $recentIncome = $user->incomeEntries()
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->take(30)
            ->get();

        $readyToAssign = $user->readyToAssign();

        return view('ready-to-assign', compact('readyToAssign', 'envelopes', 'recentIncome'));
    }

    public function assign(Request $request): RedirectResponse
    {
        $user    = $request->user();
        $amounts = $request->input('amounts', []);

        $envelopeIds = $user->envelopes()->pluck('id')->flip();
        $today       = now()->toDateString();
        $created     = 0;

        foreach ($amounts as $envelopeId => $raw) {
            $amount = (float) $raw;
            if ($amount <= 0 || !$envelopeIds->has((int) $envelopeId)) {
                continue;
            }

            EnvelopeTransaction::create([
                'envelope_id' => (int) $envelopeId,
                'type'        => 'fund',
                'amount'      => $amount,
                'description' => 'Ready to assign',
                'occurred_at' => $today,
            ]);
            $created++;
        }

        if ($created === 0) {
            return back()->with('info', 'No amounts entered.');
        }

        return redirect()->route('ready-to-assign')
            ->with('success', 'Assigned to ' . $created . ' envelope(s).');
    }
}
