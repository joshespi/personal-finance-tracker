<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\Portfolio;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function index(Request $request, Portfolio $portfolio): View
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $transactions = $portfolio->transactions()
            ->with('asset')
            ->orderByDesc('transacted_at')
            ->paginate(50);

        return view('transactions.index', compact('portfolio', 'transactions'));
    }

    public function create(Request $request, Portfolio $portfolio): View
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        return view('transactions.create', ['portfolio' => $portfolio, 'types' => self::TYPES]);
    }

    public function store(Request $request, Portfolio $portfolio): RedirectResponse
    {
        abort_unless($portfolio->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'symbol'         => ['required', 'string', 'max:20'],
            'asset_type'     => ['required', 'in:stock,crypto'],
            'type'           => ['required', 'in:' . implode(',', array_keys(self::TYPES))],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'gte:0'],
            'fees'           => ['nullable', 'numeric', 'gte:0'],
            'currency'       => ['required', 'string', 'size:3'],
            'transacted_at'  => ['required', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $symbol = strtoupper(trim($validated['symbol']));
        $asset  = Asset::firstOrCreate(
            ['symbol' => $symbol],
            ['name' => $symbol, 'asset_type' => $validated['asset_type']]
        );

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

        return redirect()
            ->route('portfolios.transactions.index', $portfolio)
            ->with('success', "Transaction for {$symbol} added.");
    }

    public function edit(Request $request, Transaction $transaction): View
    {
        abort_unless($transaction->portfolio->user_id === $request->user()->id, 403);

        $transaction->load('asset', 'portfolio');

        return view('transactions.edit', [
            'transaction' => $transaction,
            'portfolio'   => $transaction->portfolio,
            'types'       => self::TYPES,
        ]);
    }

    public function update(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->portfolio->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'type'           => ['required', 'in:' . implode(',', array_keys(self::TYPES))],
            'quantity'       => ['required', 'numeric', 'gt:0'],
            'price_per_unit' => ['required', 'numeric', 'gte:0'],
            'fees'           => ['nullable', 'numeric', 'gte:0'],
            'currency'       => ['required', 'string', 'size:3'],
            'transacted_at'  => ['required', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $transaction->update([
            'type'           => $validated['type'],
            'quantity'       => $validated['quantity'],
            'price_per_unit' => $validated['price_per_unit'],
            'fees'           => $validated['fees'] ?? 0,
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
        abort_unless($transaction->portfolio->user_id === $request->user()->id, 403);

        $portfolioId = $transaction->portfolio_id;
        $transaction->delete();

        return redirect()
            ->route('portfolios.transactions.index', $portfolioId)
            ->with('success', 'Transaction deleted.');
    }
}
