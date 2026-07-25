<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateEggMass
{
    public function handle(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): float
    {
        $today = now()->toDateString();
        $coopIds = FuzzyScope::coopIds($coopId, $commodityId);

        if (empty($coopIds)) {
            return 0.0;
        }

        $totalWeight = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('COALESCE(SUM(COALESCE(panen.berat, 0)), 0) as total_weight')
            ->value('total_weight');

        return round((float) $totalWeight, 2);
    }
}
