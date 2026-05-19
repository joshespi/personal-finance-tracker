<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnvelopeTransactionController extends Controller
{
    public function store(Request $request, Envelope $envelope): RedirectResponse
    {
        $this->authorize('update', $envelope);

        $validated = $request->validate([
            'type'            => ['required', 'in:fund'],
            'amount'          => ['required', 'numeric', 'gt:0'],
            'description'     => ['nullable', 'string', 'max:500'],
            'occurred_at'     => ['required', 'date'],
            'cash_account_id' => ['nullable', 'integer', 'exists:cash_accounts,id'],
        ]);

        $cashAccount = null;
        if (! empty($validated['cash_account_id'])) {
            $cashAccount = CashAccount::find($validated['cash_account_id']);
            $this->authorize('update', $cashAccount);
        }

        $envelopeName = $envelope->name;
        $userDesc     = $validated['description'] ?? null;

        DB::transaction(function () use ($envelope, $validated, $cashAccount, $envelopeName, $userDesc) {
            if ($cashAccount) {
                $cashAccount->transactions()->create([
                    'type'        => 'withdrawal',
                    'amount'      => $validated['amount'],
                    'occurred_at' => $validated['occurred_at'],
                    'description' => 'Funded envelope: ' . $envelopeName
                        . ($userDesc ? ' — ' . $userDesc : ''),
                ]);
            }

            $envelope->transactions()->create([
                'type'        => $validated['type'],
                'amount'      => $validated['amount'],
                'description' => $userDesc,
                'occurred_at' => $validated['occurred_at'],
            ]);
        });

        $msg = $cashAccount
            ? "Funded from {$cashAccount->name} — paired transaction recorded."
            : 'Transaction recorded.';

        return redirect()->route('envelopes.show', $envelope)->with('success', $msg);
    }

    public function destroy(Request $request, EnvelopeTransaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $envelopeId = $transaction->envelope_id;
        $transaction->delete();

        return redirect()->route('envelopes.show', $envelopeId)->with('success', 'Transaction deleted.');
    }
}
