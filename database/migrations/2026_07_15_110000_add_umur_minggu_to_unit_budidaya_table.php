<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('unitBudidaya') || Schema::hasColumn('unitBudidaya', 'umurMinggu')) {
            return;
        }

        Schema::table('unitBudidaya', function (Blueprint $table) {
            $table->integer('umurMinggu')->nullable()->default(0)->after('jumlah');
        });

        DB::table('unitBudidaya')
            ->where(function ($query) {
                $query->whereNull('umurMinggu')->orWhere('umurMinggu', 0);
            })
            ->whereNotNull('createdAt')
            ->update([
                'umurMinggu' => DB::raw('GREATEST(TIMESTAMPDIFF(WEEK, createdAt, NOW()), 0)'),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('unitBudidaya') || ! Schema::hasColumn('unitBudidaya', 'umurMinggu')) {
            return;
        }

        Schema::table('unitBudidaya', function (Blueprint $table) {
            $table->dropColumn('umurMinggu');
        });
    }
};
