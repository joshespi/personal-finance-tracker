<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    public const ACCOUNT_TYPES = [
        'checking'      => 'Checking',
        'savings'       => 'Savings',
        'credit_card'   => 'Credit Card',
        'cash'          => 'Cash',
        'money_market'  => 'Money Market',
        'cd'            => 'CD',
        'other'         => 'Other',
    ];

    public function index(Request $request): View
    {
        $accounts = $request->user()
            ->cashAccounts()
            ->withSum(['transactions as deposits_total' => fn ($q) => $q->where('type', 'deposit')], 'amount')
            ->withSum(['transactions as withdrawals_total' => fn ($q) => $q->where('type', 'withdrawal')], 'amount')
            ->orderBy('name')
            ->get()
            ->each(function ($a) {
                $a->current_balance = (float) ($a->deposits_total ?? 0) - (float) ($a->withdrawals_total ?? 0);
            });

        $totalCash = $accounts->sum('current_balance');

        return view('cash-accounts.index', [
            'accounts'     => $accounts,
            'totalCash'    => $totalCash,
            'accountTypes' => self::ACCOUNT_TYPES,
        ]);
    }

    public function create(): View
    {
        return view('cash-accounts.create', [
            'accountTypes' => self::ACCOUNT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $account = $request->user()->cashAccounts()->create($validated);

        return redirect()->route('cash-accounts.show', $account)->with('success', 'Account created.');
    }

    public function show(CashAccount $cashAccount): View
    {
        $this->authorize('view', $cashAccount);

        $cashAccount->current_balance = $cashAccount->balance();

        return view('cash-accounts.show', [
            'account'      => $cashAccount,
            'accountTypes' => self::ACCOUNT_TYPES,
        ]);
    }

    public function edit(CashAccount $cashAccount): View
    {
        $this->authorize('update', $cashAccount);

        return view('cash-accounts.edit', [
            'account'      => $cashAccount,
            'accountTypes' => self::ACCOUNT_TYPES,
        ]);
    }

    public function update(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $this->authorize('update', $cashAccount);

        $validated = $this->validatePayload($request);
        $cashAccount->update($validated);

        return redirect()->route('cash-accounts.show', $cashAccount)->with('success', 'Account updated.');
    }

    public function destroy(CashAccount $cashAccount): RedirectResponse
    {
        $this->authorize('delete', $cashAccount);

        $cashAccount->delete();

        return redirect()->route('cash-accounts.index')->with('success', 'Account deleted.');
    }

    public function reconcile(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        $this->authorize('update', $cashAccount);

        $validated = $request->validate([
            'actual_balance' => ['required', 'numeric'],
            'occurred_at'    => ['required', 'date'],
        ]);

        $current    = $cashAccount->balance();
        $actual     = (float) $validated['actual_balance'];
        $difference = round($actual - $current, 2);

        if ($difference == 0.0) {
            return redirect()->route('cash-accounts.show', $cashAccount)
                ->with('success', 'Already balanced — no adjustment needed.');
        }

        $cashAccount->transactions()->create([
            'type'        => $difference > 0 ? 'deposit' : 'withdrawal',
            'amount'      => abs($difference),
            'description' => 'Reconciliation adjustment',
            'occurred_at' => $validated['occurred_at'],
        ]);

        $sign = $difference > 0 ? '+' : '−';
        $amt  = number_format(abs($difference), 2);

        return redirect()->route('cash-accounts.show', $cashAccount)
            ->with('success', "Reconciled — {$sign}\${$amt} adjustment recorded.");
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:200'],
            'account_type'  => ['required', 'in:' . implode(',', array_keys(self::ACCOUNT_TYPES))],
            'currency'      => ['required', 'string', 'size:3'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'billing_day'   => ['nullable', 'integer', 'min:1', 'max:28'],
        ]);
    }
}
