<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleImpersonation;
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
        $schedule->command('assets:fetch-prices')->hourly();
        $schedule->command('assets:process-backfill-queue')->hourly();
        $schedule->command('portfolios:snapshot')->dailyAt('00:05');
        $schedule->command('transactions:materialize')->dailyAt('00:10');
    })
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', null));
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);
        $middleware->appendToGroup('web', HandleImpersonation::class);
        $middleware->appendToGroup('web', ShareDemoMode::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
