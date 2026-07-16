<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('daily_report_metrics')) {
            Schema::create('daily_report_metrics', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('laporan_id')->index();
                $table->string('metric_code', 100)->index();
                $table->double('value')->default(0);
                $table->string('unit', 30)->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('isDeleted')->default(false)->index();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

                $table->unique(['laporan_id', 'metric_code'], 'daily_report_metrics_report_metric_unique');
            });
        }

        if (Schema::hasTable('iot_device')) {
            Schema::table('iot_device', function (Blueprint $table) {
                if (! Schema::hasColumn('iot_device', 'lastSeenAt')) {
                    $table->timestamp('lastSeenAt')->nullable()->after('installedAt')->index();
                }

                if (! Schema::hasColumn('iot_device', 'lastMissedAt')) {
                    $table->timestamp('lastMissedAt')->nullable()->after('lastSeenAt');
                }

                if (! Schema::hasColumn('iot_device', 'missedCount')) {
                    $table->unsignedInteger('missedCount')->default(0)->after('lastMissedAt');
                }

                if (! Schema::hasColumn('iot_device', 'offlineAfterMisses')) {
                    $table->unsignedInteger('offlineAfterMisses')->default(3)->after('missedCount');
                }

                if (! Schema::hasColumn('iot_device', 'offlineAfterMinutes')) {
                    $table->unsignedInteger('offlineAfterMinutes')->default(30)->after('offlineAfterMisses');
                }
            });
        }

        $this->extendInputSourceTypes();
    }

    public function down(): void
    {
        if (Schema::hasTable('iot_device')) {
            Schema::table('iot_device', function (Blueprint $table) {
                foreach (['offlineAfterMinutes', 'offlineAfterMisses', 'missedCount', 'lastMissedAt', 'lastSeenAt'] as $column) {
                    if (Schema::hasColumn('iot_device', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('daily_report_metrics');

        if (Schema::hasTable('spk_fuzzy_input_sources') && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE spk_fuzzy_input_sources MODIFY source_type ENUM('iot','database','function') NOT NULL");
        }
    }

    private function extendInputSourceTypes(): void
    {
        if (! Schema::hasTable('spk_fuzzy_input_sources') || ! Schema::hasColumn('spk_fuzzy_input_sources', 'source_type')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE spk_fuzzy_input_sources MODIFY source_type ENUM('iot','database','function','report_metric') NOT NULL");
        }
    }
};
