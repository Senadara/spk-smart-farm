<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

/**
 * Service kalkulasi HDP (Hen-Day Production).
 *
 * HDP = (Total Telur Hari Ini / Populasi Hidup) × 100
 *
 * Digunakan oleh InputResolver ketika source_type = 'function'.
 * Interface: handle(?string $coopId = null): float
 */
class CalculateHdp
{
    /**
     * @param  string|null $coopId UUID unitBudidaya. Null = semua kandang ayam petelur aktif.
     * @return float HDP dalam persen (0–100+)
     */
    public function handle(?string $coopId = null): float
    {
        $today = now()->toDateString();

        if ($coopId) {
            // Per-kandang
            $coop = DB::table('unitBudidaya')->where('id', $coopId)->first(['jumlah']);
            $populasi = (float) ($coop->jumlah ?? 0);

            $totalTelur = (float) DB::table('panen')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)
                ->where('laporan.isDeleted', 0)
                ->where('panen.isDeleted', 0)
                ->whereDate('laporan.createdAt', $today)
                ->sum('panen.jumlah');
        } else {
            // Global — ambil semua kandang ayam petelur aktif
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

            $populasi = (float) DB::table('unitBudidaya')
                ->whereIn('id', $coopIds)
                ->sum('jumlah');

            $totalTelur = empty($coopIds) ? 0.0 : (float) DB::table('panen')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->whereIn('laporan.unitBudidayaId', $coopIds)
                ->where('laporan.isDeleted', 0)
                ->where('panen.isDeleted', 0)
                ->whereDate('laporan.createdAt', $today)
                ->sum('panen.jumlah');
        }

        if ($populasi <= 0) {
            return 0.0;
        }

        return round(($totalTelur / $populasi) * 100, 2);
    }
}
