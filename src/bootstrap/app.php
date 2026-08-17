<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleImpersonation;
use App\Http\Middleware\MaterializeDueScheduledTransactions;
use App\Http\Middleware\ShareDemoMode;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        $schedule->command('portfolios:snapshot')->dailyAt('00:05')->onOneServer();
        $schedule->command('transactions:materialize')->dailyAt('00:10')->onOneServer();
        // After the snapshot/materialize jobs above so the summary reflects that day's data.
        $schedule->command('email:send-summaries')->dailyAt('06:00')->onOneServer();
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', null));
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
        $middleware->appendToGroup('web', HandleImpersonation::class);
        $middleware->appendToGroup('web', ShareDemoMode::class);
        $middleware->appendToGroup('web', MaterializeDueScheduledTransactions::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
