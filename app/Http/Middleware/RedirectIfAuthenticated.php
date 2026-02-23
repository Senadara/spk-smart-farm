<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('api_token') && session()->has('user')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
