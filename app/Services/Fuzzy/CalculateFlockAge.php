<?php

namespace App\Services\Fuzzy;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CalculateFlockAge
{
    public function handle(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): float
    {
        $coopIds = FuzzyScope::coopIds($coopId, $commodityId);

        if (empty($coopIds)) {
            return 0.0;
        }

        $columns = ['createdAt'];
        if (Schema::hasColumn('unitBudidaya', 'umurMinggu')) {
            $columns[] = 'umurMinggu';
        }

        $ages = DB::table('unitBudidaya')
            ->whereIn('id', $coopIds)
            ->where('isDeleted', 0)
            ->get($columns)
            ->map(function ($coop) {
                if (property_exists($coop, 'umurMinggu') && $coop->umurMinggu !== null && is_numeric($coop->umurMinggu)) {
                    return max(0, (float) $coop->umurMinggu);
                }

                if (! empty($coop->createdAt)) {
                    return max(0, (float) floor(Carbon::parse($coop->createdAt)->diffInWeeks(now())));
                }

                return 0.0;
            });

        return round((float) $ages->avg(), 1);
    }
}
