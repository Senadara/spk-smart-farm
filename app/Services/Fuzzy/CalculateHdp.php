<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateHdp
{
   public function handle(?string $coopId = null, ?string $commodityId = null): float
{
    $coopIds  = FuzzyScope::coopIds($coopId, $commodityId);
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
        ->whereDate('laporan.createdAt', $today = now()->toDateString())
        ->sum('panen.jumlah');

    return $this->persenHdp($totalTelur, (float) $populasi);   
}

public function persenHdp(float $totalTelur, float $populasi): float   
{
    if ($populasi <= 0) {
        return 0.0;
    }

    return round(($totalTelur / $populasi) * 100, 2);
}
}