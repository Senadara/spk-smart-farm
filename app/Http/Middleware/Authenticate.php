<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;

class Authenticate
{
    public function handle(Request $request, Closure $next)
    {
        // Cek apakah session punya API token dan user data
        if (!session()->has('api_token') || !session()->has('user')) {
            session()->forget(['api_token', 'user', 'logged_in_at']);

            return redirect()->route('login')
                ->with('error', 'Silakan login terlebih dahulu.');
        }

        // Share user data ke semua views
        View::share('authUser', session('user'));

        return $next($request);
    }
}
