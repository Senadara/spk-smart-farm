<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateMortalitas
{
    public function handle(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): float
    {
        $startOfMonth = now()->startOfMonth()->toDateString();
        $coopIds = FuzzyScope::coopIds($coopId, $commodityId);

        if (empty($coopIds)) {
            return 0.0;
        }

        $populasi = FuzzyScope::population($coopIds);
        if ($populasi <= 0) {
            return 0.0;
        }

        $mati = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $coopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startOfMonth)
            ->count();

        return round(($mati / $populasi) * 100, 3);
    }
}
