<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\Envelope;
use App\Models\EnvelopeTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class EnvelopeController extends Controller
{
    public function index(Request $request): View
    {
        try {
            $month = $request->filled('month')
                ? Carbon::createFromFormat('Y-m', $request->input('month'))->startOfMonth()
                : now()->startOfMonth();
        } catch (\Exception) {
            $month = now()->startOfMonth();
        }

        $endOfMonth = $month->copy()->endOfMonth();

        $envelopes = $request->user()
            ->envelopes()
            ->withSum(['transactions as funds_total' => fn ($q) => $q->where('type', 'fund')], 'amount')
            ->withSum('spendTransactions as spends_total', 'amount')
            ->withSum([
                'spendTransactions as month_spend_total' => fn ($q) => $q
                    ->whereBetween('occurred_at', [$month, $endOfMonth]),
            ], 'amount')
            ->withSum([
                'transactions as month_fund_total' => fn ($q) => $q
                    ->where('type', 'fund')
                    ->whereBetween('occurred_at', [$month, $endOfMonth]),
            ], 'amount')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->each(function ($e) {
                $e->current_balance   = (float) ($e->funds_total ?? 0) - (float) ($e->spends_total ?? 0);
                $e->spent_this_month  = (float) ($e->month_spend_total ?? 0);
                $e->funded_this_month = (float) ($e->month_fund_total ?? 0);
            });

        $totalBalance     = $envelopes->sum('current_balance');
        $totalSpentMonth  = $envelopes->sum('spent_this_month');
        $totalFundedMonth = $envelopes->sum('funded_this_month');

        $grouped = $envelopes->groupBy(fn ($e) => $e->category());
        $groups = collect(Envelope::CATEGORY_ORDER)
            ->mapWithKeys(fn ($key) => $grouped->has($key)
                ? [$key => $grouped[$key]->sortByDesc(fn ($e) => (float) ($e->monthly_target ?? $e->current_balance))->values()]
                : []);

        $groupTotals = $groups->map(fn ($g) => [
            'assigned'  => round($g->sum('funded_this_month'), 2),
            'activity'  => round($g->sum('spent_this_month'), 2),
            'available' => round($g->sum('current_balance'), 2),
        ]);

        $prevMonthEnd              = $month->copy()->subMonth()->endOfMonth();
        $envelopeIds               = $envelopes->pluck('id');
        $leftOverFromLastMonth     = round(
            (float) EnvelopeTransaction::whereIn('envelope_id', $envelopeIds)
                ->where('type', 'fund')
                ->where('occurred_at', '<=', $prevMonthEnd)
                ->sum('amount')
            - (float) CashTransaction::whereIn('envelope_id', $envelopeIds)
                ->where('type', 'withdrawal')
                ->where('occurred_at', '<=', $prevMonthEnd)
                ->sum('amount'),
            2
        );

        $readyToAssign = round($request->user()->totalCash() - $totalBalance, 2);

        ['prevMonth' => $prevMonth, 'nextMonth' => $nextMonth, 'isCurrentMonth' => $isCurrentMonth] = $this->monthNav($month);

        $isFutureMonth = $month->gt(now()->startOfMonth());

        return view('envelopes.index', compact(
            'envelopes', 'groups', 'groupTotals', 'totalBalance', 'totalSpentMonth', 'totalFundedMonth',
            'leftOverFromLastMonth', 'readyToAssign', 'month', 'prevMonth', 'nextMonth', 'isCurrentMonth', 'isFutureMonth',
        ));
    }

    public function create(): View
    {
        return view('envelopes.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        if ($validated['is_emergency_fund']) {
            $this->clearEmergencyFundFlag($request);
        }

        $envelope = $request->user()->envelopes()->create($validated);

        return redirect()->route('envelopes.index')->with('success', 'Envelope created.');
    }

    public function show(Request $request, Envelope $envelope): View
    {
        $this->authorize('view', $envelope);

        $envelope->load([
            'transactions'     => fn ($q) => $q->where('type', 'fund')->orderByDesc('occurred_at')->orderByDesc('id'),
            'spendTransactions' => fn ($q) => $q->with('cashAccount:id,name')->orderByDesc('occurred_at')->orderByDesc('id'),
        ]);
        $envelope->current_balance  = $envelope->balance();
        $envelope->spent_this_month = $envelope->spentInMonth();

        $cashAccounts = $request->user()->cashAccounts()->orderBy('name')->get(['id', 'name']);

        return view('envelopes.show', compact('envelope', 'cashAccounts'));
    }

    public function edit(Request $request, Envelope $envelope): View
    {
        $this->authorize('update', $envelope);

        return view('envelopes.edit', compact('envelope'));
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
            'amount'      => ['required', 'numeric', 'gt:0'],
            'month'       => ['nullable', 'date_format:Y-m'],
        ]);

        $envelope = $user->envelopes()
            ->where('id', $validated['envelope_id'])
            ->withSum(['transactions as funds_total' => fn ($q) => $q->where('type', 'fund')], 'amount')
            ->withSum('spendTransactions as spends_total', 'amount')
            ->first();

        abort_unless($envelope !== null, 403);

        // Default to today; when assigning into a past month, date it to that month's 1st
        // so it counts toward that month's "Assigned" total. RTA stays all-time either way.
        $occurredAt = now()->toDateString();
        if (! empty($validated['month'])) {
            $assignMonth = Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth();
            if ($assignMonth->lt(now()->startOfMonth())) {
                $occurredAt = $assignMonth->toDateString();
            }
        }

        EnvelopeTransaction::create([
            'envelope_id' => $validated['envelope_id'],
            'type'        => 'fund',
            'amount'      => $validated['amount'],
            'description' => 'Ready to assign',
            'occurred_at' => $occurredAt,
        ]);

        $envelopeBalance = (float) ($envelope->funds_total ?? 0) - (float) ($envelope->spends_total ?? 0);
        $readyToAssign   = $user->readyToAssign();

        return response()->json([
            'ready_to_assign'  => $readyToAssign,
            'envelope_balance' => $envelopeBalance,
        ]);
    }

    private function clearEmergencyFundFlag(Request $request, ?int $except = null): void
    {
        $request->user()
            ->envelopes()
            ->where('is_emergency_fund', true)
            ->when($except !== null, fn ($q) => $q->where('id', '!=', $except))
            ->update(['is_emergency_fund' => false]);
    }

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'name'           => ['required', 'string', 'max:200'],
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

        return $validated;
    }
}
