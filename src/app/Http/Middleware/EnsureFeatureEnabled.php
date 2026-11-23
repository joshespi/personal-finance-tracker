<?php

namespace App\Http\Middleware;

use App\Enums\Feature;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Usage: ->middleware('feature:investing'); stack it to require several. 404s (not 403) so a hidden module looks absent. */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless($request->user()?->hasFeature(Feature::from($feature)), 404);

        return $next($request);
    }
}
