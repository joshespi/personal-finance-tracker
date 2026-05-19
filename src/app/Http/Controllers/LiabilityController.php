<?php

namespace App\Http\Controllers;

use App\Models\Liability;
use App\Models\ManualAsset;
use App\Models\ScheduledTransaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LiabilityController extends Controller
{
    public const LIABILITY_TYPES = [
        'mortgage'      => 'Mortgage',
        'credit_card'   => 'Credit Card',
        'auto_loan'     => 'Auto Loan',
        'student_loan'  => 'Student Loan',
        'personal_loan' => 'Personal Loan',
        'other'         => 'Other',
    ];

    public function index(Request $request): View
    {
        $liabilities = $request->user()
            ->liabilities()
            ->with(['latestBalance', 'manualAsset'])
            ->orderBy('name')
            ->get();

        $totalDebt = $liabilities->sum(fn ($l) => $l->currentBalance());

        return view('liabilities.index', [
            'liabilities'    => $liabilities,
            'totalDebt'      => $totalDebt,
            'liabilityTypes' => self::LIABILITY_TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('liabilities.create', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        if (! empty($validated['manual_asset_id'])) {
            $this->ensureOwnsManualAsset($request, (int) $validated['manual_asset_id']);
        }

        $liability = $request->user()->liabilities()->create($validated);
        $this->syncSchedule($liability);

        return redirect()->route('liabilities.show', $liability)->with('success', 'Liability created.');
    }

    public function show(Request $request, Liability $liability): View
    {
        abort_unless($liability->user_id === $request->user()->id, 403);

        $liability->load([
            'manualAsset.portfolio',
            'balances' => fn ($q) => $q->orderByDesc('recorded_at'),
        ]);

        return view('liabilities.show', [
            'liability'      => $liability,
            'liabilityTypes' => self::LIABILITY_TYPES,
        ]);
    }

    public function edit(Request $request, Liability $liability): View
    {
        abort_unless($liability->user_id === $request->user()->id, 403);

        return view('liabilities.edit', $this->formData($request, $liability));
    }

    public function update(Request $request, Liability $liability): RedirectResponse
    {
        abort_unless($liability->user_id === $request->user()->id, 403);

        $validated = $this->validatePayload($request);

        if (! empty($validated['manual_asset_id'])) {
            $this->ensureOwnsManualAsset($request, (int) $validated['manual_asset_id']);
        }

        $liability->update($validated);
        $this->syncSchedule($liability);

        return redirect()->route('liabilities.show', $liability)->with('success', 'Liability updated.');
    }

    public function destroy(Request $request, Liability $liability): RedirectResponse
    {
        abort_unless($liability->user_id === $request->user()->id, 403);

        $liability->delete();

        return redirect()->route('liabilities.index')->with('success', 'Liability deleted.');
    }

    private function validatePayload(Request $request): array
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:200'],
            'liability_type'          => ['required', 'in:' . implode(',', array_keys(self::LIABILITY_TYPES))],
            'manual_asset_id'         => ['nullable', 'integer', 'exists:manual_assets,id'],
            'interest_rate'           => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'minimum_payment'         => ['nullable', 'numeric', 'gte:0'],
            'escrow_amount'           => ['nullable', 'numeric', 'gte:0'],
            'payment_day'             => ['nullable', 'integer', 'min:1', 'max:28'],
            'payment_envelope_id'     => ['nullable', 'integer', Rule::exists('envelopes', 'id')->where('user_id', $userId)],
            'payment_cash_account_id' => ['nullable', 'integer', Rule::exists('cash_accounts', 'id')->where('user_id', $userId)],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            'currency'                => ['required', 'string', 'size:3'],
        ]);

        $validated['minimum_payment'] = $request->filled('minimum_payment')
            ? (float) $request->input('minimum_payment')
            : null;

        $validated['escrow_amount'] = $request->filled('escrow_amount')
            ? (float) $request->input('escrow_amount')
            : null;

        $validated['payment_day'] = $request->filled('payment_day')
            ? (int) $request->input('payment_day')
            : null;

        return $validated;
    }

    private function syncSchedule(Liability $liability): void
    {
        if (! $liability->payment_day) {
            $liability->linkedSchedule()->delete();
            return;
        }

        $today = Carbon::today();
        $day   = $liability->payment_day;
        $due   = $today->copy()->day($day);
        if ($today->day > $day) {
            $due->addMonthNoOverflow();
        }

        ScheduledTransaction::updateOrCreate(
            ['liability_id' => $liability->id],
            [
                'user_id'          => $liability->user_id,
                'type'             => 'mortgage_payment',
                'description'      => $liability->name . ' payment',
                'amount'           => $liability->totalMonthlyPayment(),
                'recurrence'       => 'monthly',
                'next_due_at'      => $due->toDateString(),
                'envelope_id'      => $liability->payment_envelope_id,
                'cash_account_id'  => $liability->payment_cash_account_id,
                'is_active'        => true,
            ]
        );
    }

    private function formData(Request $request, ?Liability $liability = null): array
    {
        return [
            'liability'      => $liability,
            'liabilityTypes' => self::LIABILITY_TYPES,
            'manualAssets'   => $this->userManualAssets($request),
            'envelopes'      => $request->user()->envelopes()->orderBy('sort_order')->get(['id', 'name']),
            'cashAccounts'   => $request->user()->cashAccounts()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function userManualAssets(Request $request)
    {
        return ManualAsset::whereHas('portfolio', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function ensureOwnsManualAsset(Request $request, int $manualAssetId): void
    {
        $owns = ManualAsset::where('id', $manualAssetId)
            ->whereHas('portfolio', fn ($q) => $q->where('user_id', $request->user()->id))
            ->exists();

        abort_unless($owns, 403);
    }
}
