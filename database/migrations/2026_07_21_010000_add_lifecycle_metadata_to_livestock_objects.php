<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('objekBudidaya')) {
            return;
        }

        Schema::table('objekBudidaya', function (Blueprint $table) {
            if (! Schema::hasColumn('objekBudidaya', 'tanggalMasuk')) {
                $table->date('tanggalMasuk')->nullable()->after('deskripsi');
            }
            if (! Schema::hasColumn('objekBudidaya', 'umurMasukMinggu')) {
                $table->unsignedSmallInteger('umurMasukMinggu')->nullable()->after('tanggalMasuk');
            }
            if (! Schema::hasColumn('objekBudidaya', 'targetAfkirAt')) {
                $table->date('targetAfkirAt')->nullable()->after('umurMasukMinggu');
            }
            if (! Schema::hasColumn('objekBudidaya', 'batchKode')) {
                $table->string('batchKode', 80)->nullable()->after('targetAfkirAt')->index('objek_budidaya_batch_kode_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('objekBudidaya')) {
            return;
        }

        if (Schema::hasColumn('objekBudidaya', 'batchKode')) {
            try {
                Schema::table('objekBudidaya', function (Blueprint $table) {
                    $table->dropIndex('objek_budidaya_batch_kode_index');
                });
            } catch (\Throwable) {
                //
            }
        }

        Schema::table('objekBudidaya', function (Blueprint $table) {
            foreach (['batchKode', 'targetAfkirAt', 'umurMasukMinggu', 'tanggalMasuk'] as $column) {
                if (Schema::hasColumn('objekBudidaya', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
