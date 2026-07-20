<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CashAccountController extends Controller
{
    public const ACCOUNT_TYPES = [
        'checking'     => 'Checking',
        'savings'      => 'Savings',
        'credit_card'  => 'Credit Card',
        'cash'         => 'Cash',
        'money_market' => 'Money Market',
        'cd'           => 'CD',
        'other'        => 'Other',
    ];

    public function index(Request $request): View
    {
        $accounts = $request->user()
            ->cashAccounts()
            ->withCurrentBalance()
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

    /** Consolidated ledger across every account, with upcoming scheduled transactions. */
    public function all(): View
    {
        return view('cash-accounts.all');
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

        $balances                     = $cashAccount->balances();
        $cashAccount->current_balance = $balances['working'];
        $cashAccount->cleared_balance = $balances['cleared'];

        return view('cash-accounts.show', [
            'account'      => $cashAccount,
            'accountTypes' => self::ACCOUNT_TYPES,
            ...$this->reconciliation($cashAccount),
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

        // Reconcile against the cleared balance — that's the figure your bank reports.
        $current    = $cashAccount->clearedBalance();
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
            'cleared'     => true,
        ]);

        $sign = $difference > 0 ? '+' : '−';
        $amt  = number_format(abs($difference), 2);

        return redirect()->route('cash-accounts.show', $cashAccount)
            ->with('success', "Reconciled — {$sign}\${$amt} adjustment recorded.");
    }

    /**
     * "Should hold vs. actually holds" for a savings-type account, derived from the
     * envelopes linked to it. Non-savings accounts get a null envelope collection so
     * the view can skip the whole panel.
     *
     * @return array{savingsEnvelopes: ?Collection, expectedTotal: float, delta: float, deltaStatus: string, untaggedThisMonth: int}
     */
    private function reconciliation(CashAccount $cashAccount): array
    {
        $empty = [
            'savingsEnvelopes'  => null,
            'expectedTotal'     => 0.0,
            'delta'             => 0.0,
            'deltaStatus'       => 'even',
            'untaggedThisMonth' => 0,
        ];

        if (! $cashAccount->isSavingsType()) {
            return $empty;
        }

        $envelopes = $cashAccount->envelopes()
            ->withBalanceTotals()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(fn ($e) => $e->current_balance = $e->balance());

        $expectedTotal = round($envelopes->sum('current_balance'), 2);
        $delta         = round($cashAccount->current_balance - $expectedTotal, 2);

        // Withdrawals not tagged to an envelope this month — a nudge that spend may have
        // come straight out of savings without being reconciled against a goal. Transfer
        // legs carry a linked_transaction_id and are excluded: moving money to another
        // account of your own isn't untracked spend.
        $untagged = $cashAccount->transactions()
            ->withdrawals()
            ->whereNull('envelope_id')
            ->whereNull('linked_transaction_id')
            ->whereBetween('occurred_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->count();

        return [
            ...$empty,
            'savingsEnvelopes'  => $envelopes,
            'expectedTotal'     => $expectedTotal,
            'delta'             => $delta,
            'deltaStatus'       => $delta < -0.005 ? 'short' : ($delta > 0.005 ? 'over' : 'even'),
            'untaggedThisMonth' => $untagged,
        ];
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:200'],
            'account_type'  => ['required', 'in:'.implode(',', array_keys(self::ACCOUNT_TYPES))],
            'currency'      => ['required', 'string', 'size:3'],
            'notes'         => ['nullable', 'string', 'max:1000'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'billing_day'   => ['nullable', 'integer', 'min:1', 'max:28'],
        ]);
    }
}
