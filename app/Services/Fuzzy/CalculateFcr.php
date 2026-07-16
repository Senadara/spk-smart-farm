<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateFcr
{
    public function handle(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): float
    {
        $today = now()->toDateString();
        $coopIds = FuzzyScope::coopIds($coopId, $commodityId);

        if (empty($coopIds)) {
            return 0.0;
        }

        $pakan = (float) DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->sum('harianTernak.pakan');

        $eggMass = (float) DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('SUM(COALESCE(panen.berat, 0)) as totalMass')
            ->value('totalMass');

        if ($eggMass <= 0) {
            return 0.0;
        }

        return round($pakan / $eggMass, 3);
    }
}
