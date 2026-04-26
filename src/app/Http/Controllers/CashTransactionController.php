<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CashTransactionController extends Controller
{
    public function store(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        abort_unless($cashAccount->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'type'        => ['required', 'in:deposit,withdrawal'],
            'amount'      => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:500'],
            'occurred_at' => ['required', 'date'],
        ]);

        $cashAccount->transactions()->create($validated);

        return redirect()->route('cash-accounts.show', $cashAccount)->with('success', 'Transaction recorded.');
    }

    public function destroy(Request $request, CashTransaction $transaction): RedirectResponse
    {
        abort_unless($transaction->cashAccount->user_id === $request->user()->id, 403);

        $accountId = $transaction->cash_account_id;
        $transaction->delete();

        return redirect()->route('cash-accounts.show', $accountId)->with('success', 'Transaction deleted.');
    }
}
