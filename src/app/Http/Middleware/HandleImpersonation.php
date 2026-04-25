<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HandleImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('impersonate_user_id') && Auth::check()) {
            $user = User::find($request->session()->get('impersonate_user_id'));
            if ($user) {
                Auth::setUser($user);
            }
        }

        return $next($request);
    }
}
