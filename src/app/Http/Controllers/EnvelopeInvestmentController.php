<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Models\ActivityLog;
use App\Models\CashAccount;
use App\Models\Envelope;
use App\Models\Transaction;
use App\Services\AssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * "Invest from an envelope": records a portfolio buy AND the envelope-tagged cash withdrawal
 * that paid for it, as one unit — so the envelope balance, total cash and portfolio value
 * all move together (ready-to-assign and net worth stay put) instead of the money being
 * counted both as cash and as stock.
 */
class EnvelopeInvestmentController extends Controller
{
    public function store(Request $request, Envelope $envelope): RedirectResponse
    {
        $this->authorize('update', $envelope);

        $userId = $request->user()->id;

        $validated = $request->validate(
            Arr::only(Transaction::fieldRules(), ['symbol', 'asset_type', 'quantity', 'price_per_unit', 'fees'])
            + [
                'portfolio_id'    => ['required', 'integer', 'exists:portfolios,id,user_id,'.$userId],
                'cash_account_id' => ['required', 'integer', CashAccount::ownershipRule($userId)],
                'transacted_at'   => ['required', 'date'],
            ]
        );

        $portfolio = $request->user()->portfolios()->findOrFail($validated['portfolio_id']);

        if ($closed = $this->redirectIfClosed($portfolio, 'transactions')) {
            return $closed;
        }

        $cashAccount = $request->user()->cashAccounts()->findOrFail($validated['cash_account_id']);
        $symbol      = AssetService::normalizeSymbol($validated['symbol']);
        $asset       = AssetService::findOrCreateBySymbol($symbol, $validated['asset_type']);

        $buy = DB::transaction(function () use ($validated, $portfolio, $cashAccount, $envelope, $asset, $symbol) {
            $buy = $portfolio->transactions()->create([
                'asset_id'       => $asset->id,
                'type'           => TransactionType::Buy,
                'quantity'       => $validated['quantity'],
                'price_per_unit' => $validated['price_per_unit'],
                'fees'           => $validated['fees'] ?? 0,
                'currency'       => 'USD',
                'transacted_at'  => $validated['transacted_at'],
                'envelope_id'    => $envelope->id,
            ]);

            $cashAccount->transactions()->create([
                'type'        => 'withdrawal',
                'amount'      => $buy->totalCost(),
                'envelope_id' => $envelope->id,
                'occurred_at' => $validated['transacted_at'],
                'description' => "Invested: {$symbol} ({$portfolio->name})",
            ]);

            return $buy;
        });

        ActivityLog::record('transaction.created', null, ['symbol' => $symbol, 'type' => 'buy']);

        return redirect()->route('envelopes.show', $envelope)
            ->with('success', 'Invested $'.number_format($buy->totalCost(), 2)." in {$symbol} from this envelope.");
    }
}
