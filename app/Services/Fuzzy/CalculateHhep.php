<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateHhep
{
    public function handle(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): float
    {
        $today = now()->toDateString();
        $coopIds = FuzzyScope::coopIds($coopId, $commodityId);

        if (empty($coopIds)) {
            return 0.0;
        }

        $currentPopulation = FuzzyScope::population($coopIds);
        $totalDeaths = (float) DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();
        $initialPopulation = $currentPopulation + $totalDeaths;

        if ($initialPopulation <= 0) {
            return 0.0;
        }

        $totalEggs = (float) DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->sum('panen.jumlah');

        return round(($totalEggs / $initialPopulation) * 100, 2);
    }
}
