<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateHdp
{
    public function handle(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): float
    {
        $today = now()->toDateString();
        $coopIds = FuzzyScope::coopIds($coopId, $commodityId);

        if (empty($coopIds)) {
            return 0.0;
        }

        $populasi = FuzzyScope::population($coopIds);
        if ($populasi <= 0) {
            return 0.0;
        }

        $totalTelur = (float) DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->sum('panen.jumlah');

        return round(($totalTelur / $populasi) * 100, 2);
    }
}