<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Models\ActivityLog;
use App\Models\Portfolio;
use App\Models\Transaction;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public const TYPES = [
        'buy'            => 'Buy',
        'sell'           => 'Sell',
        'dividend'       => 'Dividend',
        'staking_reward' => 'Staking Reward',
        'transfer_in'    => 'Transfer In',
        'transfer_out'   => 'Transfer Out',
    ];

    private function typeRule(): string
    {
        return 'in:' . implode(',', array_keys(self::TYPES));
    }

    public function index(Request $request, Portfolio $portfolio): View
    {
        $this->authorize('view', $portfolio);

        $query = $portfolio->transactions()->with([
            'asset',
            'linkedFrom.portfolio',
            'linkedTo.portfolio',
        ]);

        if ($search = $request->input('search')) {
            $query->whereHas('asset', fn ($q) => $q->where('symbol', 'like', strtoupper($search) . '%'));
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->input('from')) {
            $query->whereDate('transacted_at', '>=', $from);
        }

        if ($to = $request->input('to')) {
            $query->whereDate('transacted_at', '<=', $to);
        }

        $sortCol = in_array($request->input('sort'), ['transacted_at', 'symbol', 'type', 'quantity'])
            ? $request->input('sort')
            : 'transacted_at';

        $sortDir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        if ($sortCol === 'symbol') {
            $query->join('assets', 'assets.id', '=', 'transactions.asset_id')
                  ->orderBy('assets.symbol', $sortDir)
                  ->select('transactions.*');
        } else {
            $query->orderBy($sortCol, $sortDir);
        }

        $transactions = $query->paginate(50)->withQueryString();

        return view('transactions.index', compact('portfolio', 'transactions', 'sortCol', 'sortDir'));
    }

    public function create(Request $request, Portfolio $portfolio): View|RedirectResponse
    {
        $this->authorize('update', $portfolio);

        if ($portfolio->isClosed()) {
            return redirect()->route('portfolios.show', $portfolio)
                ->with('error', 'This portfolio is closed. Reopen it to add transactions.');
        }

        return view('transactions.create', ['portfolio' => $portfolio, 'types' => self::TYPES]);
    }

    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        $this->authorize('update', $portfolio);

        if ($portfolio->isClosed()) {
            return redirect()->route('portfolios.show', $portfolio)
                ->with('error', 'This portfolio is closed. Reopen it to add transactions.');
        }

        $validated = $request->validate([
            'symbol'         => ['required', 'string', 'max:20'],
            'asset_type'     => ['required', Rule::enum(AssetType::class)],
            'type'           => ['required', $this->typeRule()],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'gte:0'],
            'fees'           => ['nullable', 'numeric', 'gte:0'],
            'currency'       => ['required', 'string', 'size:3'],
            'transacted_at'  => ['required', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $symbol = strtoupper(trim($validated['symbol']));
        $asset  = AssetService::findOrCreateBySymbol($symbol, $validated['asset_type']);

        $portfolio->transactions()->create([
            'asset_id'       => $asset->id,
            'type'           => $validated['type'],
            'quantity'       => $validated['quantity'],
            'price_per_unit' => $validated['price_per_unit'],
            'fees'           => $validated['fees'] ?? 0,
            'currency'       => $validated['currency'],
            'transacted_at'  => $validated['transacted_at'],
            'notes'          => $validated['notes'] ?? null,
        ]);

        ActivityLog::record('transaction.created', null, ['symbol' => $symbol, 'type' => $validated['type']]);

        return redirect()
            ->route('portfolios.transactions.index', $portfolio)
            ->with('success', "Transaction for {$symbol} added.");
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        $this->authorize('update', $transaction);

        $transaction->load('asset', 'portfolio');
        $request->user()->applyAssetClassifications(collect([$transaction->asset]));

        return view('transactions.edit', [
            'transaction' => $transaction,
            'portfolio'   => $transaction->portfolio,
            'types'       => self::TYPES,
            'assetTypes'  => AssetType::cases(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate([
            'type'           => ['required', $this->typeRule()],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'gte:0'],
            'fees'           => ['nullable', 'numeric', 'gte:0'],
            'fee_in_asset'   => ['nullable', 'boolean'],
            'currency'       => ['required', 'string', 'size:3'],
            'transacted_at'  => ['required', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $isTransfer  = in_array($validated['type'], ['transfer_in', 'transfer_out']);
        $feeInAsset  = $isTransfer && ($validated['fee_in_asset'] ?? false);
        $fees        = (float) ($validated['fees'] ?? 0);

        // quantity field on transfer_out edit shows gross (sent + fee); strip fee back out for storage
        // since holdings logic adds fees back when deducting from position
        $quantity = (float) $validated['quantity'];
        if ($feeInAsset && $validated['type'] === 'transfer_out') {
            $quantity = max(0, $quantity - $fees);
        }

        $transaction->update([
            'type'           => $validated['type'],
            'quantity'       => $quantity,
            'price_per_unit' => $validated['price_per_unit'],
            'fees'           => $fees,
            'fee_in_asset'   => $feeInAsset,
            'currency'       => $validated['currency'],
            'transacted_at'  => $validated['transacted_at'],
            'notes'          => $validated['notes'] ?? null,
        ]);

        return redirect()
            ->route('portfolios.transactions.index', $transaction->portfolio_id)
            ->with('success', 'Transaction updated.');
    }

    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $portfolioId = $transaction->portfolio_id;
        $symbol = $transaction->asset->symbol ?? 'unknown';

        ActivityLog::record('transaction.deleted', null, ['symbol' => $symbol]);

        $transaction->delete();

        return redirect()
            ->route('portfolios.transactions.index', $portfolioId)
            ->with('success', 'Transaction deleted.');
    }
}
