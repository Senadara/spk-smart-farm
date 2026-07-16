<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('api_token') && session()->has('user')) {
            if (session('user.role') === 'supplier') {
                return redirect()->route('supplier.dashboard');
            }

            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
