<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculatePakan
{
    public function handle(?string $coopId = null): float
    {
        $today = now()->toDateString();

        if ($coopId) {
            $pakan    = (float) DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')->where('laporan.unitBudidayaId', $coopId)->where('laporan.isDeleted', 0)->where('harianTernak.isDeleted', 0)->whereDate('laporan.createdAt', $today)->sum('harianTernak.pakan');
            $populasi = (float) DB::table('unitBudidaya')->where('id', $coopId)->value('jumlah');
        } else {
            $jenis    = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
            $coopIds  = DB::table('unitBudidaya')->where('jenisBudidayaId', $jenis?->id)->where('status', 1)->where('isDeleted', 0)->pluck('id')->toArray();
            $populasi = empty($coopIds) ? 0.0 : (float) DB::table('unitBudidaya')->whereIn('id', $coopIds)->sum('jumlah');
            $pakan    = empty($coopIds) ? 0.0 : (float) DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')->whereIn('laporan.unitBudidayaId', $coopIds)->where('laporan.isDeleted', 0)->where('harianTernak.isDeleted', 0)->whereDate('laporan.createdAt', $today)->sum('harianTernak.pakan');
        }

        if ($populasi <= 0) return 0.0;

        // Konversi kg ke gram, bagi per ekor
        return round(($pakan * 1000) / $populasi, 1);
    }
}
