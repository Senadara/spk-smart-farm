<?php

namespace App\Observers;

use App\Models\SpkAhpBobot;
use App\Models\SpkRanking;

class SpkAhpBobotObserver
{
    public function saved(SpkAhpBobot $bobot)
    {
        SpkRanking::where('user_id', $bobot->user_id)->update(['is_valid' => false]);
    }

    public function deleted(SpkAhpBobot $bobot)
    {
        SpkRanking::where('user_id', $bobot->user_id)->update(['is_valid' => false]);
    }
}
