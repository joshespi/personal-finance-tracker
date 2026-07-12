<?php

namespace App\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared search/type/date-range filtering and sort resolution for the
 * per-portfolio and cross-portfolio transaction list pages. Accepts the
 * Eloquent builder contract rather than the concrete Builder class since
 * callers pass either a plain query (`Transaction::whereIn(...)`) or a
 * relation query (`$portfolio->transactions()`), and only the interface is
 * common to both.
 */
trait FiltersTransactionQuery
{
    private function applyTransactionFilters(Builder $query, Request $request): void
    {
        if ($search = $request->input('search')) {
            $query->whereHas('asset', fn ($q) => $q->where('symbol', 'like', strtoupper($search).'%'));
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
    }

    /**
     * Resolves sort column (must be in $allowed, else falls back to
     * transacted_at) + direction from the request, applies it to $query, and
     * returns [sortCol, sortDir] for the view. symbol/portfolio aren't real
     * transactions columns, so they sort via a join instead of orderBy.
     *
     * @param  string[]  $allowed
     * @return array{0: string, 1: string}
     */
    private function applyTransactionSort(Builder $query, Request $request, array $allowed): array
    {
        $sortCol = in_array($request->input('sort'), $allowed) ? $request->input('sort') : 'transacted_at';
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

        return [$sortCol, $sortDir];
    }
}
