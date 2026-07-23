<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashTransferController extends Controller
{
    public function create(Request $request): View
    {
        $accounts = $request->user()->cashAccounts()->orderBy('name')->get(['id', 'name', 'account_type', 'currency']);

        return view('cash-transfers.create', compact('accounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $accountIds = $request->user()->cashAccounts()->pluck('id');

        $validated = $request->validate([
            'from_account_id' => ['required', 'integer', Rule::in($accountIds)],
            'to_account_id'   => ['required', 'integer', 'different:from_account_id', Rule::in($accountIds)],
            'amount'          => ['required', 'numeric', 'gt:0'],
            'description'     => ['nullable', 'string', 'max:500'],
            'occurred_at'     => ['required', 'date'],
            'cleared'         => ['nullable', 'boolean'],
        ]);

        // Account ids are already constrained to the user's own accounts by Rule::in above,
        // so cash_account_id can be set directly — no need to re-fetch either account.
        $common = [
            'amount'      => $validated['amount'],
            'description' => $validated['description'] ?: null,
            'occurred_at' => $validated['occurred_at'],
            'cleared'     => $request->boolean('cleared'),
        ];

        DB::transaction(function () use ($common, $validated) {
            $withdrawal = CashTransaction::create(array_merge($common, [
                'cash_account_id' => $validated['from_account_id'],
                'type'            => 'withdrawal',
            ]));

            CashTransaction::create(array_merge($common, [
                'cash_account_id'       => $validated['to_account_id'],
                'type'                  => 'deposit',
                'linked_transaction_id' => $withdrawal->id,
            ]));
        });

        return redirect()->route('cash-accounts.all')->with('success', 'Transfer recorded.');
    }
}
