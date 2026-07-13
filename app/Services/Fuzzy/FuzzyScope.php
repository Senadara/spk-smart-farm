<?php

namespace App\Services\Fuzzy;

use Illuminate\Support\Facades\DB;

class FuzzyScope
{
    /**
     * @return array<int, string>
     */
    public static function coopIds(?string $coopId = null, ?string $commodityId = null): array
    {
        if ($coopId) {
            return [$coopId];
        }

        $jenisBudidayaId = null;

        if ($commodityId) {
            $jenisBudidayaId = DB::table('komoditas')
                ->where('id', $commodityId)
                ->where('isDeleted', 0)
                ->value('jenisBudidayaId');
        }

        if (! $jenisBudidayaId) {
            $jenisBudidayaId = DB::table('jenisBudidaya')
                ->where('nama', 'like', '%Ayam Petelur%')
                ->where('isDeleted', 0)
                ->value('id');
        }

        if (! $jenisBudidayaId) {
            return [];
        }

        return DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $jenisBudidayaId)
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();
    }

    /**
     * @param  array<int, string>  $coopIds
     */
    public static function population(array $coopIds): float
    {
        if (empty($coopIds)) {
            return 0.0;
        }

        return (float) DB::table('unitBudidaya')
            ->whereIn('id', $coopIds)
            ->where('isDeleted', 0)
            ->sum('jumlah');
    }
}
