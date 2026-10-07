<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\IncomeCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CashTransactionController extends Controller
{
    /**
     * Single-transaction entry from the layout's Quick Add modal. Same rules as the
     * Livewire ledger (ManagesCashTransactionForm::transactionRules): an envelope only
     * applies to a withdrawal, an income category only to a deposit.
     */
    public function store(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $this->authorize('update', $cashAccount);

        $userId = $request->user()->id;
        $data   = $request->validate([
            'type'               => ['required', 'in:deposit,withdrawal'],
            'amount'             => ['required', 'numeric', 'gt:0'],
            'description'        => ['nullable', 'string', 'max:500'],
            'occurred_at'        => ['required', 'date'],
            'envelope_id'        => ['nullable', 'integer', Envelope::ownershipRule($userId)],
            'income_category_id' => ['nullable', 'integer', IncomeCategory::ownershipRule($userId)],
        ]);

        $isDeposit = $data['type'] === 'deposit';

        $cashAccount->transactions()->create([
            'type'               => $data['type'],
            'amount'             => $data['amount'],
            'description'        => $data['description'] ?? null,
            'occurred_at'        => $data['occurred_at'],
            'envelope_id'        => $isDeposit ? null : ($data['envelope_id'] ?? null),
            'income_category_id' => $isDeposit ? ($data['income_category_id'] ?? null) : null,
        ]);

        return redirect()->route('cash-accounts.show', $cashAccount)->with('success', 'Transaction recorded.');
    }

    public function destroy(Request $request, CashTransaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $accountId   = $transaction->cash_account_id;
        $wasTransfer = $transaction->deleteWithCounterpart();

        return redirect()->route('cash-accounts.show', $accountId)
            ->with('success', $wasTransfer ? 'Transfer deleted from both accounts.' : 'Transaction deleted.');
    }
}
