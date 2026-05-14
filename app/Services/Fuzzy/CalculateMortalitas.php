<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class CalculateMortalitas
{
    public function handle(?string $coopId = null): float
    {
        $startOfMonth = now()->startOfMonth()->toDateString();

        if ($coopId) {
            $mati     = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')->where('laporan.unitBudidayaId', $coopId)->where('laporan.isDeleted', 0)->where('kematian.isDeleted', 0)->whereDate('kematian.tanggal', '>=', $startOfMonth)->count();
            $populasi = (float) DB::table('unitBudidaya')->where('id', $coopId)->value('jumlah');
        } else {
            $jenis    = DB::table('jenisBudidaya')->where('nama', 'like', '%Ayam Petelur%')->where('isDeleted', 0)->first();
            $coopIds  = DB::table('unitBudidaya')->where('jenisBudidayaId', $jenis?->id)->where('status', 1)->where('isDeleted', 0)->pluck('id')->toArray();
            $populasi = empty($coopIds) ? 0.0 : (float) DB::table('unitBudidaya')->whereIn('id', $coopIds)->sum('jumlah');
            $mati     = empty($coopIds) ? 0 : DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')->whereIn('laporan.unitBudidayaId', $coopIds)->where('laporan.isDeleted', 0)->where('kematian.isDeleted', 0)->whereDate('kematian.tanggal', '>=', $startOfMonth)->count();
        }

        if ($populasi <= 0) return 0.0;

        return round(($mati / $populasi) * 100, 3);
    }
}
