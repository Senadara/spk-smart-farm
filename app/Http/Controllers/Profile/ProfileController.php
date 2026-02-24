<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;

use App\Models\LoginHistory;

class ProfileController extends Controller
{
    public function show()
    {
        $user = session('user', []);

        $loginHistories = LoginHistory::where('email', $user['email'] ?? '')
            ->orderByDesc('login_at')
            ->take(10)
            ->get();

        return view('profile.show', compact('user', 'loginHistories'));
    }
}
