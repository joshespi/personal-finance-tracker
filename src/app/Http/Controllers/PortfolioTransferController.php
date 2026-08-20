<?php

namespace App\Http\Controllers;

use App\Enums\AssetType;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Services\AssetService;
use App\Services\RealizedGainService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortfolioTransferController extends Controller
{
    public function create(Request $request): View
    {
        $portfolios = $request->user()->portfolios()->orderBy('name')->get();

        return view('transfers.create', compact('portfolios'));
    }

    public function store(Request $request, RealizedGainService $gainService): RedirectResponse
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

        $symbol     = AssetService::normalizeSymbol($validated['symbol']);
        $asset      = AssetService::findOrCreateBySymbol($symbol, $validated['asset_type']);
        $fees       = (float) ($validated['fees'] ?? 0);
        $feeInAsset = (bool) ($validated['fee_in_asset'] ?? false);
        $qtySent    = (float) $validated['quantity'];

        // transfer_in records what actually landed — fee was consumed on the sending side
        $receivedQty = Transaction::netOfFee($qtySent, $fees, $feeInAsset);

        // carry the original cost basis from source portfolio into the destination lot
        $fromPortfolio   = $request->user()->portfolios()->findOrFail($validated['from_portfolio_id']);
        $openLots        = $gainService->openLotsForAsset($fromPortfolio, $asset->id, $validated['transacted_at']);
        $transferInPrice = $gainService->transferInCostPerUnit($openLots, $qtySent, $receivedQty)
            ?? (float) $validated['price_per_unit'];

        $common = [
            'asset_id'       => $asset->id,
            'price_per_unit' => $validated['price_per_unit'],
            'fees'           => $fees,
            'fee_in_asset'   => $feeInAsset,
            'currency'       => $validated['currency'],
            'transacted_at'  => $validated['transacted_at'],
            'notes'          => $validated['notes'] ?? null,
        ];

        DB::transaction(function () use ($common, $validated, $qtySent, $receivedQty, $transferInPrice) {
            $transferOut = Transaction::create(array_merge($common, [
                'portfolio_id' => $validated['from_portfolio_id'],
                'type'         => TransactionType::TransferOut,
                'quantity'     => $qtySent,
            ]));

            Transaction::create(array_merge($common, [
                'portfolio_id'       => $validated['to_portfolio_id'],
                'type'               => TransactionType::TransferIn,
                'quantity'           => $receivedQty,
                'price_per_unit'     => $transferInPrice,
                'fee_in_asset'       => false,
                'fees'               => 0,
                'linked_transfer_id' => $transferOut->id,
            ]));
        });

        if ($request->boolean('add_another')) {
            return redirect()
                ->route('transfers.create', [
                    'from_portfolio_id' => $validated['from_portfolio_id'],
                    'to_portfolio_id'   => $validated['to_portfolio_id'],
                    'symbol'            => $symbol,
                    'asset_type'        => $validated['asset_type'],
                    'currency'          => $validated['currency'],
                    'transacted_at'     => $validated['transacted_at'],
                ])
                ->with('success', 'Transfer recorded.');
        }

        return redirect()
            ->route('dashboard')
            ->with('success', 'Transfer recorded.');
    }
}
