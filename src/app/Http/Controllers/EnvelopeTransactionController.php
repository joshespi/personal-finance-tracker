<?php

namespace App\Http\Controllers;

use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EnvelopeTransactionController extends Controller
{
    public function store(Request $request, Envelope $envelope): RedirectResponse
    {
        abort_unless($envelope->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'type'        => ['required', 'in:fund,spend'],
            'amount'      => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date'],
        ]);

        $envelope->transactions()->create($validated);

        return redirect()->route('envelopes.show', $envelope)->with('success', 'Transaction recorded.');
    }

    public function destroy(Request $request, EnvelopeTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->envelope->user_id === $request->user()->id, 403);

        $envelopeId = $transaction->envelope_id;
        $transaction->delete();

        return redirect()->route('envelopes.show', $envelopeId)->with('success', 'Transaction deleted.');
    }
}
