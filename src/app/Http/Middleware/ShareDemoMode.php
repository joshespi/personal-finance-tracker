<?php

namespace App\Http\Middleware;

use App\Services\DemoMode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareDemoMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $demo = app(DemoMode::class);
        View::share('demo', $demo);

        return $next($request);
    }
}
