<?php

namespace App\Http\Middleware;

use App\Services\ScheduledTransactionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Materializes the current user's due scheduled transactions on every authenticated
 * request, so envelope balances / Ready to Assign / debt figures are never stale just
 * because the user's first page of the session happened not to be All Transactions
 * (previously the only page that triggered this). The daily transactions:materialize
 * cron is still the backstop for accounts that never open the app, and is the sole
 * source of the ScheduledTransactionsSummary notification email — deliberately: an
 * active user materializing here on every page load would otherwise get emailed about
 * things they're already looking at, so email stays a once-a-day digest rather than
 * racing this middleware for who "fires" a transaction first.
 */
class MaterializeDueScheduledTransactions
{
    public function __construct(private ScheduledTransactionService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $this->service->materializeForUser(Auth::user());
        }

        return $next($request);
    }
}
