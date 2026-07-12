<?php

namespace App\Http\Controllers;

use App\Concerns\FiltersTransactionQuery;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllTransactionsController extends Controller
{
    use FiltersTransactionQuery;

    public function __invoke(Request $request): View
    {
        $portfolioIds = $request->user()->portfolios()->pluck('id');

        $query = Transaction::whereIn('portfolio_id', $portfolioIds)
            ->with(['asset', 'portfolio', 'linkedFrom.portfolio', 'linkedTo.portfolio']);

        $this->applyTransactionFilters($query, $request);

        if ($portfolioId = $request->integer('portfolio_id', 0)) {
            $query->where('portfolio_id', $portfolioId);
        }

        [$sortCol, $sortDir] = $this->applyTransactionSort($query, $request, ['transacted_at', 'symbol', 'type', 'quantity', 'portfolio']);

        $transactions = $query->paginate(50)->withQueryString();
        $portfolios   = $request->user()->portfolios()->orderBy('name')->get(['id', 'name']);

        return view('transactions.all', compact('transactions', 'portfolios', 'sortCol', 'sortDir'));
    }
}
