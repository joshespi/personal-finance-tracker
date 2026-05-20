<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Models\Transaction;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortfolioTransferController extends Controller
{
    public function create(Request $request): View
    {
        $portfolios = $request->user()->portfolios()->orderBy('name')->get();

        return view('transfers.create', compact('portfolios'));
    }

    public function store(Request $request): RedirectResponse
    {
        $portfolioIds = $request->user()->portfolios()->pluck('id');

        $validated = $request->validate([
            'from_portfolio_id' => ['required', 'integer', Rule::in($portfolioIds)],
            'to_portfolio_id'   => ['required', 'integer', 'different:from_portfolio_id', Rule::in($portfolioIds)],
            'symbol'            => ['required', 'string', 'max:20'],
            'asset_type'        => ['required', Rule::enum(AssetType::class)],
            'quantity'          => ['required', 'numeric', 'gt:0'],
            'price_per_unit'    => ['required', 'numeric', 'gte:0'],
            'fees'              => ['nullable', 'numeric', 'gte:0'],
            'fee_in_asset'      => ['nullable', 'boolean'],
            'currency'          => ['required', 'string', 'size:3'],
            'transacted_at'     => ['required', 'date'],
            'notes'             => ['nullable', 'string', 'max:1000'],
        ]);

        $symbol     = strtoupper(trim($validated['symbol']));
        $asset      = AssetService::findOrCreateBySymbol($symbol, $validated['asset_type']);
        $fees       = (float) ($validated['fees'] ?? 0);
        $feeInAsset = (bool) ($validated['fee_in_asset'] ?? false);

        $common = [
            'asset_id'       => $asset->id,
            'price_per_unit' => $validated['price_per_unit'],
            'fees'           => $fees,
            'fee_in_asset'   => $feeInAsset,
            'currency'       => $validated['currency'],
            'transacted_at'  => $validated['transacted_at'],
            'notes'          => $validated['notes'] ?? null,
        ];

        $transferOut = Transaction::create(array_merge($common, [
            'portfolio_id' => $validated['from_portfolio_id'],
            'type'         => 'transfer_out',
            'quantity'     => $validated['quantity'],
        ]));

        // When fee is paid in the asset, the destination receives quantity − fee.
        $receivedQty = $feeInAsset
            ? max(0, (float) $validated['quantity'] - $fees)
            : (float) $validated['quantity'];

        Transaction::create(array_merge($common, [
            'portfolio_id'       => $validated['to_portfolio_id'],
            'type'               => 'transfer_in',
            'quantity'           => $receivedQty,
            'linked_transfer_id' => $transferOut->id,
        ]));

        return redirect()
            ->route('dashboard')
            ->with('success', 'Transfer recorded.');
    }
}
