<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersTransactionQuery;
use App\Enums\AssetType;
use App\Enums\TransactionType;
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
    use FiltersTransactionQuery;

    public function index(Request $request, Portfolio $portfolio): View
    {
        $this->authorize('view', $portfolio);

        $query = $portfolio->transactions()->with([
            'asset',
            'linkedFrom.portfolio',
            'linkedTo.portfolio',
        ]);

        $this->applyTransactionFilters($query, $request);

        [$sortCol, $sortDir] = $this->applyTransactionSort($query, $request, ['transacted_at', 'symbol', 'type', 'quantity']);

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

        return view('transactions.create', ['portfolio' => $portfolio, 'types' => TransactionType::options()]);
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
            'type'           => ['required', Rule::enum(TransactionType::class)],
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
            'types'       => TransactionType::options(),
            'assetTypes'  => AssetType::cases(),
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $validated = $request->validate([
            'type'           => ['required', Rule::enum(TransactionType::class)],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'gte:0'],
            'fees'           => ['nullable', 'numeric', 'gte:0'],
            'fee_in_asset'   => ['nullable', 'boolean'],
            'currency'       => ['required', 'string', 'size:3'],
            'transacted_at'  => ['required', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $type       = TransactionType::from($validated['type']);
        $feeInAsset = $type->isTransfer() && ($validated['fee_in_asset'] ?? false);
        $fees       = (float) ($validated['fees'] ?? 0);

        // quantity field on transfer_out edit shows gross (sent + fee); strip fee back out for storage
        // since holdings logic adds fees back when deducting from position
        $quantity = (float) $validated['quantity'];
        if ($feeInAsset && $type === TransactionType::TransferOut) {
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
        $symbol      = $transaction->asset->symbol ?? 'unknown';

        ActivityLog::record('transaction.deleted', null, ['symbol' => $symbol]);

        $transaction->delete();

        return redirect()
            ->route('portfolios.transactions.index', $portfolioId)
            ->with('success', 'Transaction deleted.');
    }
}
