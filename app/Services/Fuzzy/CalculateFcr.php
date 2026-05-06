<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

/**
 * Service kalkulasi FCR (Feed Conversion Ratio).
 *
 * FCR = Total Pakan (kg) / Total Egg Mass (kg)
 *
 * Digunakan oleh InputResolver ketika source_type = 'function'.
 * Interface: handle(?string $coopId = null): float
 */
class CalculateFcr
{
    /**
     * @param  string|null $coopId UUID unitBudidaya. Null = semua kandang ayam petelur aktif.
     * @return float FCR (biasanya 1.2 – 2.5+). 0 jika tidak ada data.
     */
    public function handle(?string $coopId = null): float
    {
        $today = now()->toDateString();

        if ($coopId) {
            // Per-kandang
            $pakan = (float) DB::table('harianTernak')
                ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)
                ->where('laporan.isDeleted', 0)
                ->where('harianTernak.isDeleted', 0)
                ->whereDate('laporan.createdAt', $today)
                ->sum('harianTernak.pakan');

            $eggMass = (float) DB::table('panen')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)
                ->where('laporan.isDeleted', 0)
                ->where('panen.isDeleted', 0)
                ->whereDate('laporan.createdAt', $today)
                ->selectRaw('SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as totalMass')
                ->value('totalMass');
        } else {
            // Global — semua kandang ayam petelur aktif
            $jenis = DB::table('jenisBudidaya')
                ->where('nama', 'like', '%Ayam Petelur%')
                ->where('isDeleted', 0)
                ->first();

            $coopIds = DB::table('unitBudidaya')
                ->where('jenisBudidayaId', $jenis?->id)
                ->where('status', 1)
                ->where('isDeleted', 0)
                ->pluck('id')
                ->toArray();

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
                ->selectRaw('SUM(COALESCE(panen.berat, panen.jumlah * 0.06)) as totalMass')
                ->value('totalMass');
        }

        if ($eggMass <= 0) {
            return 0.0;
        }

        return round($pakan / $eggMass, 3);
    }
}
