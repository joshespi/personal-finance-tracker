<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CashTransactionController extends Controller
{
    public function destroy(Request $request, CashTransaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $accountId   = $transaction->cash_account_id;
        $wasTransfer = $transaction->deleteWithCounterpart();

        return redirect()->route('cash-accounts.show', $accountId)
            ->with('success', $wasTransfer ? 'Transfer deleted from both accounts.' : 'Transaction deleted.');
    }
}
