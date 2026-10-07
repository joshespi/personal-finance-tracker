<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\ScheduledTransaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CashTransferController extends Controller
{
    /** How far ahead the form looks for scheduled activity on either account. */
    private const UPCOMING_DAYS = 30;

    public function create(Request $request): View
    {
        $accounts = $request->user()->cashAccounts()
            ->withCurrentBalance()
            ->orderBy('name')
            ->get();

        $upcoming = $request->user()->scheduledTransactions()
            ->active()
            ->whereIn('cash_account_id', $accounts->pluck('id'))
            ->whereDate('next_due_at', '<=', Carbon::today()->addDays(self::UPCOMING_DAYS))
            ->orderBy('next_due_at')
            ->get(['cash_account_id', 'description', 'amount', 'type', 'next_due_at'])
            ->groupBy('cash_account_id');

        $summaries = $accounts->mapWithKeys(function (CashAccount $a) use ($upcoming) {
            $balance = $a->currentBalanceFromSums();

            return [$a->id => [
                'isCard'    => $a->isCreditCard(),
                'balance'   => round($balance, 2),
                'statement' => $a->statementSummary($balance),
                'upcoming'  => ($upcoming[$a->id] ?? collect())->map(fn (ScheduledTransaction $s) => [
                    'description' => $s->description,
                    'amount'      => round($s->signedCashAmount(), 2),
                    'due'         => $s->next_due_at->format('M j'),
                ])->values(),
            ]];
        });

        return view('cash-transfers.create', [
            'accounts'     => $accounts,
            'summaries'    => $summaries,
            'upcomingDays' => self::UPCOMING_DAYS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $accountIds = $request->user()->cashAccounts()->pluck('id');

        $validated = $request->validate([
            'from_account_id'   => ['required', 'integer', Rule::in($accountIds)],
            'to_account_id'     => ['required', 'integer', 'different:from_account_id', Rule::in($accountIds)],
            'amount'            => ['required', 'numeric', 'gt:0'],
            'description'       => ['nullable', 'string', 'max:500'],
            'occurred_at'       => ['required', 'date'],
            'cleared'           => ['nullable', 'boolean'],
            'return_account_id' => ['nullable', 'integer', Rule::in($accountIds)],
        ]);

        // Account ids are already constrained to the user's own accounts by Rule::in above.
        CashTransaction::recordTransfer($validated['from_account_id'], $validated['to_account_id'], [
            'amount'      => $validated['amount'],
            'description' => $validated['description'] ?: null,
            'occurred_at' => $validated['occurred_at'],
            'cleared'     => $request->boolean('cleared'),
        ]);

        // Back to the account page the form was opened from; otherwise the receiving
        // account, so a card payment lands on the card it just paid down.
        $returnTo = $validated['return_account_id'] ?? $validated['to_account_id'];

        return redirect()->route('cash-accounts.show', $returnTo)->with('success', 'Transfer recorded.');
    }
}
