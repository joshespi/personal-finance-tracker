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

        $accountId = $transaction->cash_account_id;
        $transaction->delete();

        return redirect()->route('cash-accounts.show', $accountId)->with('success', 'Transaction deleted.');
    }
}
