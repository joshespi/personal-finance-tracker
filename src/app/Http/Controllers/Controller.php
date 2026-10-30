<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Parse a `YYYY-MM` request param to the first of that month, falling back to the
     * current month when it's missing or unparseable.
     *
     * '!' resets unparsed fields (day) to 1 — plain 'Y-m' keeps today's day-of-month,
     * which overflows short months on the 29th–31st (e.g. '2026-02' on Mar 30 → Feb 30
     * → Mar 2 → wrong month).
     */
    protected function monthFromParam(?string $month): Carbon
    {
        if ($month === null || $month === '') {
            return now()->startOfMonth();
        }

        try {
            return Carbon::createFromFormat('!Y-m', $month)->startOfMonth();
        } catch (\Exception) {
            return now()->startOfMonth();
        }
    }

    /** Redirect back to the portfolio with an error when it's closed (read-only); null when it's open. */
    protected function redirectIfClosed(Portfolio $portfolio, string $addWhat): ?RedirectResponse
    {
        if (! $portfolio->isClosed()) {
            return null;
        }

        return redirect()->route('portfolios.show', $portfolio)
            ->with('error', "This portfolio is closed. Reopen it to add {$addWhat}.");
    }

    protected function monthNav(Carbon $month): array
    {
        return [
            'prevMonth'      => $month->copy()->subMonth()->format('Y-m'),
            'nextMonth'      => $month->copy()->addMonth()->format('Y-m'),
            'isCurrentMonth' => $month->isSameMonth(now()),
        ];
    }
}
