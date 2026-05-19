<?php

namespace App\Http\Controllers;

use App\Models\ScheduledTransaction;
use App\Services\ScheduledTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduledTransactionController extends Controller
{
    public function index(Request $request, ScheduledTransactionService $service): View
    {
        $count = $service->materializeForUser($request->user())->count();

        $scheduled = $request->user()
            ->scheduledTransactions()
            ->with(['envelope', 'cashAccount'])
            ->orderBy('next_due_at')
            ->get();

        return view('scheduled-transactions.index', compact('scheduled', 'count'));
    }

    public function create(Request $request): View
    {
        $envelopes   = $request->user()->envelopes()->orderBy('sort_order')->get();
        $cashAccounts = $request->user()->cashAccounts()->orderBy('name')->get();
        return view('scheduled-transactions.create', compact('envelopes', 'cashAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $request->user()->scheduledTransactions()->create($data);
        return redirect()->route('scheduled-transactions.index')
            ->with('success', 'Scheduled transaction created.');
    }

    public function edit(Request $request, ScheduledTransaction $scheduledTransaction): View
    {
        $this->authorize('update', $scheduledTransaction);
        $envelopes   = $request->user()->envelopes()->orderBy('sort_order')->get();
        $cashAccounts = $request->user()->cashAccounts()->orderBy('name')->get();
        return view('scheduled-transactions.edit', compact('scheduledTransaction', 'envelopes', 'cashAccounts'));
    }

    public function update(Request $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('update', $scheduledTransaction);
        $scheduledTransaction->update($this->validated($request));
        return redirect()->route('scheduled-transactions.index')
            ->with('success', 'Scheduled transaction updated.');
    }

    public function destroy(Request $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('delete', $scheduledTransaction);
        $scheduledTransaction->delete();
        return redirect()->route('scheduled-transactions.index')
            ->with('success', 'Scheduled transaction deleted.');
    }

    public function toggle(Request $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('update', $scheduledTransaction);
        $scheduledTransaction->update(['is_active' => ! $scheduledTransaction->is_active]);
        return redirect()->route('scheduled-transactions.index');
    }

    private function validated(Request $request): array
    {
        $type   = $request->input('type');
        $userId = $request->user()->id;

        $envelopeRule     = Rule::exists('envelopes', 'id')->where('user_id', $userId);
        $cashAccountRule  = Rule::exists('cash_accounts', 'id')->where('user_id', $userId);

        return $request->validate([
            'description'    => 'required|string|max:500',
            'amount'         => 'required|numeric|min:0.01',
            'type'           => 'required|in:envelope_fund,envelope_spend,cash_deposit,cash_withdrawal',
            'recurrence'     => 'required|in:monthly,weekly,biweekly',
            'next_due_at'    => 'required|date',
            'envelope_id'    => in_array($type, ['envelope_fund', 'envelope_spend'])
                ? ['required', $envelopeRule]
                : 'nullable',
            'cash_account_id' => in_array($type, ['cash_deposit', 'cash_withdrawal', 'envelope_spend'])
                ? ['required', $cashAccountRule]
                : ['nullable', $cashAccountRule],
            'is_active'      => 'boolean',
        ]);
    }
}
