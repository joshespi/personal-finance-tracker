<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AllTransactionsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $portfolioIds = $request->user()->portfolios()->pluck('id');

        $query = Transaction::whereIn('portfolio_id', $portfolioIds)
            ->with(['asset', 'portfolio', 'linkedFrom.portfolio', 'linkedTo.portfolio']);

        if ($search = $request->input('search')) {
            $query->whereHas('asset', fn ($q) => $q->where('symbol', 'like', strtoupper($search) . '%'));
        }

        if ($portfolioId = $request->integer('portfolio_id', 0)) {
            $query->where('portfolio_id', $portfolioId);
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

        $sortCol = in_array($request->input('sort'), ['transacted_at', 'symbol', 'type', 'quantity', 'portfolio'])
            ? $request->input('sort')
            : 'transacted_at';

        $sortDir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        if ($sortCol === 'symbol') {
            $query->join('assets', 'assets.id', '=', 'transactions.asset_id')
                  ->orderBy('assets.symbol', $sortDir)
                  ->select('transactions.*');
        } elseif ($sortCol === 'portfolio') {
            $query->join('portfolios', 'portfolios.id', '=', 'transactions.portfolio_id')
                  ->orderBy('portfolios.name', $sortDir)
                  ->select('transactions.*');
        } else {
            $query->orderBy($sortCol, $sortDir);
        }

        $transactions = $query->paginate(50)->withQueryString();
        $portfolios   = $request->user()->portfolios()->orderBy('name')->get(['id', 'name']);

        return view('transactions.all', compact('transactions', 'portfolios', 'sortCol', 'sortDir'));
    }
}
