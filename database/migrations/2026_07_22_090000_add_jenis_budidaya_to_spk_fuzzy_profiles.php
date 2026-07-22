<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spk_fuzzy_profiles')) {
            return;
        }

        if (! Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
            Schema::table('spk_fuzzy_profiles', function (Blueprint $table) {
                $table->uuid('jenis_budidaya_id')->nullable()->after('commodity_id')->index();
            });
        }

        if (! Schema::hasTable('komoditas')) {
            return;
        }

        $profiles = DB::table('spk_fuzzy_profiles')
            ->whereNull('jenis_budidaya_id')
            ->whereNotNull('commodity_id')
            ->get(['id', 'commodity_id']);

        foreach ($profiles as $profile) {
            $jenisBudidayaId = DB::table('komoditas')
                ->where('id', $profile->commodity_id)
                ->value('jenisBudidayaId');

            if ($jenisBudidayaId) {
                DB::table('spk_fuzzy_profiles')
                    ->where('id', $profile->id)
                    ->update(['jenis_budidaya_id' => $jenisBudidayaId]);
            }
        }

        if (! Schema::hasTable('jenisBudidaya')) {
            return;
        }

        $fallbackJenisId = DB::table('jenisBudidaya')
            ->where('isDeleted', 0)
            ->where('tipe', 'hewan')
            ->where(function ($query) {
                $query->where('nama', 'like', '%Petelur%')
                    ->orWhere('nama', 'like', '%Layer%')
                    ->orWhere('nama', 'like', '%Ayam%');
            })
            ->orderByRaw("CASE WHEN nama LIKE '%Petelur%' OR nama LIKE '%Layer%' THEN 0 ELSE 1 END")
            ->value('id');

        if (! $fallbackJenisId) {
            $fallbackJenisId = DB::table('jenisBudidaya')
                ->where('isDeleted', 0)
                ->where('tipe', 'hewan')
                ->orderBy('nama')
                ->value('id');
        }

        if ($fallbackJenisId) {
            DB::table('spk_fuzzy_profiles')
                ->whereNull('jenis_budidaya_id')
                ->update(['jenis_budidaya_id' => $fallbackJenisId]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('spk_fuzzy_profiles')
            || ! Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
            return;
        }

        Schema::table('spk_fuzzy_profiles', function (Blueprint $table) {
            $table->dropColumn('jenis_budidaya_id');
        });
    }
};
