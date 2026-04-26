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
        'cash'          => 'Cash',
        'money_market'  => 'Money Market',
        'cd'            => 'CD',
        'other'         => 'Other',
    ];

    public function index(Request $request): View
    {
        $accounts = $request->user()
            ->cashAccounts()
            ->orderBy('name')
            ->get()
            ->map(function ($a) {
                $a->current_balance = $a->balance();
                return $a;
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

    public function show(Request $request, CashAccount $cashAccount): View
    {
        abort_unless($cashAccount->user_id === $request->user()->id, 403);

        $cashAccount->load(['transactions' => fn ($q) => $q->orderByDesc('occurred_at')->orderByDesc('id')]);
        $cashAccount->current_balance = $cashAccount->balance();

        return view('cash-accounts.show', [
            'account'      => $cashAccount,
            'accountTypes' => self::ACCOUNT_TYPES,
        ]);
    }

    public function edit(Request $request, CashAccount $cashAccount): View
    {
        abort_unless($cashAccount->user_id === $request->user()->id, 403);

        return view('cash-accounts.edit', [
            'account'      => $cashAccount,
            'accountTypes' => self::ACCOUNT_TYPES,
        ]);
    }

    public function update(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        abort_unless($cashAccount->user_id === $request->user()->id, 403);

        $validated = $this->validatePayload($request);
        $cashAccount->update($validated);

        return redirect()->route('cash-accounts.show', $cashAccount)->with('success', 'Account updated.');
    }

    public function destroy(Request $request, CashAccount $cashAccount): RedirectResponse
    {
        abort_unless($cashAccount->user_id === $request->user()->id, 403);

        $cashAccount->delete();

        return redirect()->route('cash-accounts.index')->with('success', 'Account deleted.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:200'],
            'account_type' => ['required', 'in:' . implode(',', array_keys(self::ACCOUNT_TYPES))],
            'currency'     => ['required', 'string', 'size:3'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
