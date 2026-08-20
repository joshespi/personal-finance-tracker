<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleImpersonation;
use App\Http\Middleware\MaterializeDueScheduledTransactions;
use App\Http\Middleware\ShareDemoMode;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule): void {
        // Two independent guards, hence both on the hourly entries. onOneServer(): the scheduler
        // is meant to run only in the in-stack `scheduler` service, but a leftover host crontab
        // running `schedule:run` against the same stack makes two schedulers fire the same command
        // in the same minute — that's how duplicate summary emails got sent; the shared lock
        // (CACHE_STORE=database, so the mutex is visible across containers) lets only the first
        // process claim each run. withoutOverlapping(): stops one slow run from colliding with its
        // own next tick — doubled price fetching/quota against the same hour, or a corrupted
        // write_cursor/status when a large backfill's write phase spans multiple ticks.
        $schedule->command('assets:fetch-prices')->hourly()->onOneServer()->withoutOverlapping();
        $schedule->command('assets:process-backfill-queue')->hourly()->onOneServer()->withoutOverlapping();
        // withoutOverlapping() on the daily entries too: onOneServer() only stops two schedulers
        // colliding on the same tick, it does nothing about a snapshot run on a long history
        // still working when the next day's tick fires.
        $schedule->command('portfolios:snapshot')->dailyAt('00:05')->onOneServer()->withoutOverlapping();
        $schedule->command('transactions:materialize')->dailyAt('00:10')->onOneServer()->withoutOverlapping();
        // After the snapshot/materialize jobs above so the summary reflects that day's data.
        $schedule->command('email:send-summaries')->dailyAt('06:00')->onOneServer()->withoutOverlapping();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        // headers: is explicit because Laravel's default set includes X_FORWARDED_HOST, which
        // feeds $request->root() and therefore every generated absolute URL — including the
        // password-reset and email-verification links. A request that can name its own host
        // can have a real reset mail sent to a victim pointing at an attacker's domain.
        // Host is never taken from a header; scheme/port/IP still are, so HTTPS detection and
        // client-IP recording keep working behind a real proxy.
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', null),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        // Pins the accepted Host to APP_URL. nginx has a single default server block that
        // answers to any Host and passes it through verbatim, and forceScheme() below rewrites
        // only the scheme — so without this nothing constrains the host at all. Automatically
        // a no-op in local/testing (see TrustHosts::shouldSpecifyTrustedHosts()).
        $middleware->trustHosts();
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
        $middleware->appendToGroup('web', HandleImpersonation::class);
        $middleware->appendToGroup('web', ShareDemoMode::class);
        $middleware->appendToGroup('web', MaterializeDueScheduledTransactions::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
