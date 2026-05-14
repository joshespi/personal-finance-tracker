<?php

namespace App\Http\Controllers;

use App\Models\Liability;
use App\Models\ManualAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $totalDebt = $liabilities->sum(fn ($l) => $l->latestBalance ? (float) $l->latestBalance->balance : 0);

        return view('liabilities.index', [
            'liabilities'    => $liabilities,
            'totalDebt'      => $totalDebt,
            'liabilityTypes' => self::LIABILITY_TYPES,
        ]);
    }

    public function create(Request $request): View
    {
        $manualAssets = ManualAsset::whereHas('portfolio', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('liabilities.create', [
            'liabilityTypes' => self::LIABILITY_TYPES,
            'manualAssets'   => $manualAssets,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        if (! empty($validated['manual_asset_id'])) {
            $this->ensureOwnsManualAsset($request, (int) $validated['manual_asset_id']);
        }

        $liability = $request->user()->liabilities()->create($validated);

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

        $manualAssets = ManualAsset::whereHas('portfolio', fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('liabilities.edit', [
            'liability'      => $liability,
            'liabilityTypes' => self::LIABILITY_TYPES,
            'manualAssets'   => $manualAssets,
        ]);
    }

    public function update(Request $request, Liability $liability): RedirectResponse
    {
        abort_unless($liability->user_id === $request->user()->id, 403);

        $validated = $this->validatePayload($request);

        if (! empty($validated['manual_asset_id'])) {
            $this->ensureOwnsManualAsset($request, (int) $validated['manual_asset_id']);
        }

        $liability->update($validated);

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
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:200'],
            'liability_type'  => ['required', 'in:' . implode(',', array_keys(self::LIABILITY_TYPES))],
            'manual_asset_id' => ['nullable', 'integer', 'exists:manual_assets,id'],
            'interest_rate'   => ['nullable', 'numeric', 'gte:0', 'lte:100'],
            'minimum_payment' => ['nullable', 'numeric', 'gte:0'],
            'notes'           => ['nullable', 'string', 'max:1000'],
            'currency'        => ['required', 'string', 'size:3'],
        ]);

        // Explicitly coerce omitted nullable fields so update() clears them
        $validated['minimum_payment'] = $request->filled('minimum_payment')
            ? (float) $request->input('minimum_payment')
            : null;

        return $validated;
    }

    private function ensureOwnsManualAsset(Request $request, int $manualAssetId): void
    {
        $owns = ManualAsset::where('id', $manualAssetId)
            ->whereHas('portfolio', fn ($q) => $q->where('user_id', $request->user()->id))
            ->exists();

        abort_unless($owns, 403);
    }
}
