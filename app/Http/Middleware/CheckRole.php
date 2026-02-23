<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = session('user');

        if (!$user || !isset($user['role'])) {
            return redirect()->route('login')
                ->with('error', 'Sesi tidak valid. Silakan login ulang.');
        }

        if (!in_array($user['role'], $roles)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        return $next($request);
    }
}
