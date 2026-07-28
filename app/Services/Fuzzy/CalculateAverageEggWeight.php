<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateAverageEggWeight
{
    public function handle(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): float
    {
        $today = now()->toDateString();
        $coopIds = FuzzyScope::coopIds($coopId, $commodityId);

        if (empty($coopIds)) {
            return 0.0;
        }

        $row = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as total_eggs, COALESCE(SUM(COALESCE(panen.berat, 0)), 0) as total_weight')
            ->first();

        $totalEggs = (float) ($row->total_eggs ?? 0);
        $totalWeight = (float) ($row->total_weight ?? 0);

        if ($totalEggs <= 0) {
            return 0.0;
        }

        return round(($totalWeight * 1000) / $totalEggs, 2);
    }
}
