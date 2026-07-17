<?php

namespace App\Observers;

use App\Models\SpkAhpBobot;
use App\Models\SpkRanking;
use Illuminate\Support\Facades\Log;

class SpkAhpBobotObserver
{
    public function saved(SpkAhpBobot $bobot)
    {
        try {
            if ($bobot->user_id && SpkRanking::where('user_id', $bobot->user_id)->exists()) {
                SpkRanking::where('user_id', $bobot->user_id)->update(['is_valid' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning('SpkAhpBobotObserver: gagal update ranking', [
                'user_id' => $bobot->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function deleted(SpkAhpBobot $bobot)
    {
        try {
            if ($bobot->user_id && SpkRanking::where('user_id', $bobot->user_id)->exists()) {
                SpkRanking::where('user_id', $bobot->user_id)->update(['is_valid' => false]);
            }
        } catch (\Throwable $e) {
            Log::warning('SpkAhpBobotObserver: gagal update ranking saat hapus', [
                'user_id' => $bobot->user_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
