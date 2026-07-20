<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculatePakan
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

        $populasi = FuzzyScope::population($coopIds);

        if ($populasi <= 0) return 0.0;

        // Konversi kg ke gram, bagi per ekor
        // baris terakhir handle() menjadi:
return $this->pakanPerEkor($pakan, $populasi);
    }

    public function pakanPerEkor(float $pakan, float $populasi): float
{
    if ($populasi <= 0) {
        return 0.0;
    }

    // Konversi kg ke gram, bagi per ekor
    return round(($pakan * 1000) / $populasi, 1);
}
}
