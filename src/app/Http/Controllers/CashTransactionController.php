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
    public function store(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $this->authorize('update', $cashAccount);

        $validated = $request->validate([
            'type'               => ['required', 'in:deposit,withdrawal'],
            'amount'             => ['required', 'numeric', 'gt:0'],
            'description'        => ['nullable', 'string', 'max:500'],
            'occurred_at'        => ['required', 'date'],
            'envelope_id'        => ['nullable', 'integer', Envelope::ownershipRule($request->user()->id)],
            'income_category_id' => [
                'nullable', 'integer',
                IncomeCategory::ownershipRule($request->user()->id),
            ],
            'cleared' => ['nullable', 'boolean'],
        ]);

        $validated['cleared'] = $request->boolean('cleared');

        // An envelope only applies to withdrawals; ownership is enforced by the validation rule above.
        if (! empty($validated['envelope_id'])) {
            abort_unless($validated['type'] === 'withdrawal', 403);
        } else {
            $validated['envelope_id'] = null;
        }

        // A category only applies to deposits; ownership is enforced by the validation rule above.
        if ($validated['type'] !== 'deposit') {
            $validated['income_category_id'] = null;
        }

        $cashAccount->transactions()->create($validated);

        return redirect()->route('cash-accounts.show', $cashAccount)->with('success', 'Transaction recorded.');
    }

    public function destroy(Request $request, CashTransaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $accountId = $transaction->cash_account_id;
        $transaction->delete();

        return redirect()->route('cash-accounts.show', $accountId)->with('success', 'Transaction deleted.');
    }
}
