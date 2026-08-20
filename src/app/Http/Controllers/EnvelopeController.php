<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EnvelopeController extends Controller
{
    public function index(Request $request): View
    {
        $month      = $this->monthFromParam($request->input('month'));
        $endOfMonth = $month->copy()->endOfMonth();

        $envelopes = $request->user()
            ->envelopes()
            ->withBalanceTotals()
            ->withSum([
                'spendTransactions as month_spend_total' => fn ($q) => $q
                    ->whereBetween('occurred_at', [$month, $endOfMonth]),
            ], 'amount')
            ->withMonthFundTotal($month)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function ($e) {
                $e->current_balance   = $e->balance();
                $e->spent_this_month  = (float) ($e->month_spend_total ?? 0);
                $e->funded_this_month = (float) ($e->month_fund_total ?? 0);
            });

        $totalBalance     = $envelopes->sum('current_balance');
        $totalSpentMonth  = $envelopes->sum('spent_this_month');
        $totalFundedMonth = $envelopes->sum('funded_this_month');

        $grouped = $envelopes->groupBy(fn ($e) => $e->category());
        $groups  = collect(Envelope::CATEGORY_ORDER)
            ->mapWithKeys(fn ($key) => $grouped->has($key)
                ? [$key => $grouped[$key]->sortByDesc(fn ($e) => (float) ($e->monthly_target ?? $e->current_balance))->values()]
                : []);

        $groupTotals = $groups->map(fn ($g) => [
            'assigned'  => round($g->sum('funded_this_month'), 2),
            'activity'  => round($g->sum('spent_this_month'), 2),
            'available' => round($g->sum('current_balance'), 2),
        ]);

        $prevMonthEnd          = $month->copy()->subMonth()->endOfMonth();
        $envelopeIds           = $envelopes->pluck('id');
        $leftOverFromLastMonth = round(
            (float) EnvelopeTransaction::whereIn('envelope_id', $envelopeIds)
                ->where('type', 'fund')
                ->where('occurred_at', '<=', $prevMonthEnd)
                ->sum('amount')
            - (float) CashTransaction::whereIn('envelope_id', $envelopeIds)
                ->withdrawals()
                ->where('occurred_at', '<=', $prevMonthEnd)
                ->sum('amount'),
            2
        );

        $readyToAssign = $request->user()->readyToAssign();

        ['prevMonth' => $prevMonth, 'nextMonth' => $nextMonth, 'isCurrentMonth' => $isCurrentMonth] = $this->monthNav($month);

        $isFutureMonth = $month->gt(now()->startOfMonth());

        // "Copy last month" only makes sense going forward, and only when there's something
        // to copy. The month test decides it outright for past months, so it short-circuits
        // ahead of the query — and the query only needs to know whether a row exists.
        $canCopyPreviousMonth = ($isCurrentMonth || $isFutureMonth)
            && EnvelopeTransaction::whereIn('envelope_id', $envelopeIds)
                ->where('type', 'fund')
                ->whereBetween('occurred_at', [$month->copy()->subMonth(), $prevMonthEnd])
                ->exists();

        return view('envelopes.index', compact(
            'envelopes', 'groups', 'groupTotals', 'totalBalance', 'totalSpentMonth', 'totalFundedMonth',
            'leftOverFromLastMonth', 'readyToAssign', 'month', 'prevMonth', 'nextMonth', 'isCurrentMonth', 'isFutureMonth',
            'canCopyPreviousMonth',
        ));
    }

    public function create(): View
    {
        return view('envelopes.create', [
            'savingsAccounts' => $this->savingsAccounts(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        if ($validated['is_emergency_fund']) {
            $this->clearEmergencyFundFlag($request);
        }

        $request->user()->envelopes()->create($validated);

        return redirect()->route('envelopes.index')->with('success', 'Envelope created.');
    }

    public function show(Request $request, Envelope $envelope): View
    {
        $this->authorize('view', $envelope);

        $envelope->load([
            'transactions'      => fn ($q) => $q->where('type', 'fund')->orderByDesc('occurred_at')->orderByDesc('id'),
            'spendTransactions' => fn ($q) => $q->with('cashAccount:id,name')->orderByDesc('occurred_at')->orderByDesc('id'),
            'cashAccount:id,name',
        ]);
        $envelope->current_balance  = $envelope->balance();
        $envelope->spent_this_month = $envelope->spentInMonth();

        $cashAccounts = $request->user()->cashAccounts()->orderBy('name')->get(['id', 'name']);

        return view('envelopes.show', compact('envelope', 'cashAccounts'));
    }

    public function edit(Request $request, Envelope $envelope): View
    {
        $this->authorize('update', $envelope);

        return view('envelopes.edit', [
            'envelope'        => $envelope,
            'savingsAccounts' => $this->savingsAccounts(),
        ]);
    }

    public function update(Request $request, Envelope $envelope): RedirectResponse
    {
        $this->authorize('update', $envelope);

        $validated = $this->validatePayload($request);

        if ($validated['is_emergency_fund']) {
            $this->clearEmergencyFundFlag($request, except: $envelope->id);
        }

        $envelope->update($validated);

        return redirect()->route('envelopes.show', $envelope)->with('success', 'Envelope updated.');
    }

    public function destroy(Request $request, Envelope $envelope): RedirectResponse
    {
        $this->authorize('delete', $envelope);

        $envelope->delete();

        return redirect()->route('envelopes.index')->with('success', 'Envelope deleted.');
    }

    public function assignOne(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'envelope_id' => ['required', 'integer'],
            'amount'      => ['required', 'numeric'],
            'month'       => ['nullable', 'date_format:Y-m'],
        ]);

        // `amount` is the *desired total assigned* for the month, not an increment.
        $targetMonth = $this->monthFromParam($validated['month'] ?? null);
        $occurredAt  = $this->fundDateFor($targetMonth);

        $envelope = $user->envelopes()
            ->where('id', $validated['envelope_id'])
            ->withBalanceTotals()
            ->withMonthFundTotal($targetMonth)
            ->first();

        abort_unless($envelope !== null, 403);

        $row   = $this->assignmentRow($envelope, (float) $validated['amount'], $occurredAt, 'Assigned');
        $delta = (float) ($row['amount'] ?? 0);

        if ($row !== null) {
            EnvelopeTransaction::create($row);
        }

        // funds_total was captured before the delta above, so fold it in for a fresh balance.
        $envelopeBalance = round((float) ($envelope->funds_total ?? 0) - (float) ($envelope->spends_total ?? 0) + $delta, 2);
        $readyToAssign   = $user->readyToAssign();

        return response()->json([
            'ready_to_assign'  => $readyToAssign,
            'envelope_balance' => $envelopeBalance,
        ]);
    }

    /**
     * Seed a month's budget from the month before it: set every envelope's assigned
     * total for the target month to whatever it was the previous month. Idempotent —
     * it records the same delta assignOne() does, so re-running lands on the same
     * totals instead of stacking.
     */
    public function copyPreviousMonth(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month' => ['required', 'date_format:Y-m'],
        ]);

        $targetMonth = $this->monthFromParam($validated['month']);

        // Past months already happened; copying over them would rewrite history.
        abort_if($targetMonth->lt(now()->startOfMonth()), 422, 'Cannot copy into a past month.');

        $sourceMonth = $targetMonth->copy()->subMonth();
        $occurredAt  = $this->fundDateFor($targetMonth);

        $envelopes = $request->user()
            ->envelopes()
            ->withMonthFundTotal($targetMonth)
            ->withMonthFundTotal($sourceMonth, 'source_fund_total')
            ->get();

        $rows = $envelopes
            ->map(fn ($envelope) => $this->assignmentRow(
                $envelope,
                (float) ($envelope->source_fund_total ?? 0),
                $occurredAt,
                'Copied from '.$sourceMonth->format('M Y')
            ))
            ->filter()
            ->values();

        // create() per row rather than one bulk insert(): insert() bypasses the model's
        // `date` cast on occurred_at, storing a bare 'Y-m-d' where every other write path
        // stores 'Y-m-d H:i:s' — which silently drops the row out of month-range queries.
        // Wrapped so a failure part-way can't leave the month half-copied.
        DB::transaction(function () use ($rows) {
            $rows->each(fn ($row) => EnvelopeTransaction::create($row));
        });

        $copied = $rows->count();

        return redirect()
            ->route('envelopes.index', ['month' => $targetMonth->format('Y-m')])
            ->with('success', $copied === 0
                ? $targetMonth->format('M Y').' already matches '.$sourceMonth->format('M Y').'.'
                : 'Copied '.$sourceMonth->format('M Y').' amounts into '.$targetMonth->format('M Y')
                    .' ('.$copied.' '.str('envelope')->plural($copied).' updated).');
    }

    /**
     * The 'fund' row that sets an envelope's assigned *total* for a month to $target, or
     * null when nothing needs writing.
     *
     * Both assign paths record only the delta against what the month already holds
     * (`month_fund_total`, from Envelope::scopeWithMonthFundTotal()) rather than the total
     * itself — that is what makes assigning idempotent, so repeated edits set the month's
     * total instead of stacking on top of it. Amounts are 2dp, so a delta under half a cent
     * has rounded away to nothing and no row is worth writing.
     */
    private function assignmentRow(Envelope $envelope, float $target, string $occurredAt, string $description): ?array
    {
        $delta = round($target - (float) ($envelope->month_fund_total ?? 0), 2);

        if (abs($delta) < 0.005) {
            return null;
        }

        return [
            'envelope_id' => $envelope->id,
            'type'        => 'fund',
            'amount'      => $delta,
            'description' => $description,
            'occurred_at' => $occurredAt,
        ];
    }

    /**
     * Where a 'fund' row for a given month belongs: today when that's the current month
     * (keeps the ledger honest for live assignments), otherwise the 1st of that month so
     * it counts toward the month it was budgeted for.
     */
    private function fundDateFor(CarbonInterface $month): string
    {
        return $month->isSameMonth(now())
            ? now()->toDateString()
            : $month->copy()->startOfMonth()->toDateString();
    }

    private function clearEmergencyFundFlag(Request $request, ?int $except = null): void
    {
        $request->user()
            ->envelopes()
            ->where('is_emergency_fund', true)
            ->when($except !== null, fn ($q) => $q->where('id', '!=', $except))
            ->update(['is_emergency_fund' => false]);
    }

    /** Accounts eligible to hold this envelope's balance (see CashAccount::SAVINGS_TYPES). */
    private function savingsAccounts(): Collection
    {
        return auth()->user()
            ->cashAccounts()
            ->whereIn('account_type', CashAccount::SAVINGS_TYPES)
            ->orderBy('name')
            ->get(['id', 'name', 'account_type']);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            // Ownership *and* type: the picker only offers savings-type accounts, so accepting
            // a checking account here would create a link the UI can neither show nor clear.
            'cash_account_id' => ['nullable', 'integer', CashAccount::ownershipRule($request->user()->id)
                ->whereIn('account_type', CashAccount::SAVINGS_TYPES)],
            'monthly_target' => ['nullable', 'numeric', 'gte:0'],
            'goal_amount'    => ['nullable', 'numeric', 'gte:0'],
            'goal_date'      => ['nullable', 'date'],
            'color'          => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order'     => ['nullable', 'integer', 'gte:0'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        // Checkboxes omit the key when unchecked; coerce explicitly
        $validated['is_mandatory']      = $request->boolean('is_mandatory');
        $validated['is_emergency_fund'] = $request->boolean('is_emergency_fund');
        $validated['is_savings']        = $request->boolean('is_savings') || $validated['is_emergency_fund'];
        // Necessities are always part of the EF target; the toggle adds non-mandatory ones.
        $validated['include_in_emergency_fund'] = $request->boolean('include_in_emergency_fund') || $validated['is_mandatory'];

        return $validated;
    }
}
