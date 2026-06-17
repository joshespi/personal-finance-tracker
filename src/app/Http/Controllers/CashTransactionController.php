<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'envelope_id'        => ['nullable', 'integer', 'exists:envelopes,id'],
            'income_category_id' => [
                'nullable', 'integer',
                Rule::exists('income_categories', 'id')->where('user_id', $request->user()->id),
            ],
            'cleared'            => ['nullable', 'boolean'],
        ]);

        $validated['cleared'] = $request->boolean('cleared');

        if (! empty($validated['envelope_id'])) {
            abort_unless(
                $validated['type'] === 'withdrawal' &&
                Envelope::where('id', $validated['envelope_id'])->value('user_id') === $request->user()->id,
                403
            );
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
