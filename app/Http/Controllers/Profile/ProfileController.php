<?php

namespace App\Http\Controllers\Profile;

use App\Http\Controllers\Controller;
use App\Models\FarmProfile;
use App\Models\LoginHistory;
use App\Models\SpkRanking;
use App\Support\SpkDssActorId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = session('user', []);
        $userId = SpkDssActorId::resolve($request);
        $farmProfile = $userId
            ? FarmProfile::query()->where('user_id', $userId)->first()
            : null;

        $loginHistories = LoginHistory::where('email', $user['email'] ?? '')
            ->orderByDesc('createdAt')
            ->take(10)
            ->get();

        return view('profile.show', compact('user', 'farmProfile', 'loginHistories'));
    }

    public function updateFarm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'farm_name' => 'nullable|string|max:255',
            'address' => 'required|string|max:1000',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $userId = SpkDssActorId::resolve($request);
        if (! $userId) {
            return back()->with('error', 'User tidak dapat dipetakan untuk menyimpan lokasi peternakan.');
        }

        FarmProfile::query()->updateOrCreate(
            ['user_id' => $userId],
            [
                'farm_name' => $validated['farm_name'] ?? null,
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
            ]
        );

        SpkRanking::query()->where('user_id', $userId)->update(['is_valid' => false]);

        return back()->with('success', 'Lokasi peternakan berhasil diperbarui. Ranking supplier akan dihitung ulang.');
    }
}
