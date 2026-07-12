<?php

namespace App\Http\Controllers;

use App\Enums\Recurrence;
use App\Enums\ScheduledTransactionType;
use App\Models\Envelope;
use App\Models\ScheduledTransaction;
use App\Services\ScheduledTransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ScheduledTransactionController extends Controller
{
    public function create(Request $request): View
    {
        $envelopes    = $request->user()->envelopes()->orderBy('sort_order')->get();
        $cashAccounts = $request->user()->cashAccounts()->orderBy('name')->get();

        return view('scheduled-transactions.create', compact('envelopes', 'cashAccounts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $request->user()->scheduledTransactions()->create($data);

        return redirect()->route('cash-accounts.all')
            ->with('success', 'Scheduled transaction created.');
    }

    public function edit(Request $request, ScheduledTransaction $scheduledTransaction): View
    {
        $this->authorize('update', $scheduledTransaction);
        $envelopes    = $request->user()->envelopes()->orderBy('sort_order')->get();
        $cashAccounts = $request->user()->cashAccounts()->orderBy('name')->get();

        return view('scheduled-transactions.edit', compact('scheduledTransaction', 'envelopes', 'cashAccounts'));
    }

    public function update(Request $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('update', $scheduledTransaction);
        $scheduledTransaction->update($this->validated($request, $scheduledTransaction));

        return redirect()->route('cash-accounts.all')
            ->with('success', 'Scheduled transaction updated.');
    }

    public function destroy(Request $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('delete', $scheduledTransaction);
        $scheduledTransaction->delete();

        return redirect()->route('cash-accounts.all')
            ->with('success', 'Scheduled transaction deleted.');
    }

    public function toggle(Request $request, ScheduledTransaction $scheduledTransaction): RedirectResponse
    {
        $this->authorize('update', $scheduledTransaction);
        $scheduledTransaction->update(['is_active' => ! $scheduledTransaction->is_active]);

        return redirect()->route('cash-accounts.all');
    }

    /** Record the next occurrence now (it happened early) and advance the schedule. */
    public function enterNow(Request $request, ScheduledTransaction $scheduledTransaction, ScheduledTransactionService $service): RedirectResponse
    {
        $this->authorize('update', $scheduledTransaction);
        $service->enterNow($scheduledTransaction);

        return back()->with('success', "Recorded \"{$scheduledTransaction->description}\" — next due {$scheduledTransaction->next_due_at->format('M j, Y')}.");
    }

    /** Skip the next occurrence without recording, advancing the schedule. */
    public function skip(Request $request, ScheduledTransaction $scheduledTransaction, ScheduledTransactionService $service): RedirectResponse
    {
        $this->authorize('update', $scheduledTransaction);
        $service->skipNext($scheduledTransaction);

        return back()->with('success', "Skipped — next due {$scheduledTransaction->next_due_at->format('M j, Y')}.");
    }

    private function validated(Request $request, ?ScheduledTransaction $existing = null): array
    {
        $type   = ScheduledTransactionType::tryFrom($request->input('type'));
        $userId = $request->user()->id;

        $envelopeRule    = Envelope::ownershipRule($userId);
        $cashAccountRule = Rule::exists('cash_accounts', 'id')->where('user_id', $userId);

        // Users may never create system-managed types (mortgage payments come from
        // LiabilityController::syncSchedule), but re-saving an existing mortgage
        // schedule must keep its type — mirror the edit form's dropdown exactly.
        $allowedTypes = ScheduledTransactionType::userSelectableValues();
        if ($existing !== null && ! $existing->type->userSelectable()) {
            $allowedTypes[] = $existing->type->value;
        }

        return $request->validate([
            'description' => 'required|string|max:500',
            'amount'      => 'required|numeric|min:0.01',
            'type'        => ['required', Rule::in($allowedTypes)],
            'recurrence'  => ['required', Rule::in(Recurrence::values())],
            'next_due_at' => 'required|date',
            'envelope_id' => $type?->needsEnvelope()
                ? ['required', $envelopeRule]
                : 'nullable',
            'cash_account_id' => $type?->requiresCashAccount()
                ? ['required', $cashAccountRule]
                : ['nullable', $cashAccountRule],
            'is_active' => 'boolean',
        ]);
    }
}
