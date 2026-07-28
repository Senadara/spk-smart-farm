<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_SETTING_ID = '9f0b1800-0000-4000-8000-000000000601';

    public function up(): void
    {
        if (! Schema::hasTable('spk_health_scheduler_settings')) {
            Schema::create('spk_health_scheduler_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->boolean('is_enabled')->default(false)->index();
                $table->json('schedule_times')->nullable();
                $table->unsignedTinyInteger('days')->default(7);
                $table->decimal('threshold_percent', 5, 2)->default(40);
                $table->string('target_role', 30)->default('petugas');
                $table->timestamp('last_run_at')->nullable();
                $table->string('last_run_key', 40)->nullable();
                $table->string('last_status', 30)->nullable();
                $table->json('last_summary')->nullable();
                $table->uuid('configured_by')->nullable()->index();
                $table->timestamp('createdAt')->useCurrent();
                $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
            });
        }

        DB::table('spk_health_scheduler_settings')->updateOrInsert(
            ['id' => self::DEFAULT_SETTING_ID],
            [
                'is_enabled' => false,
                'schedule_times' => json_encode(['07:00', '16:00']),
                'days' => 7,
                'threshold_percent' => 40,
                'target_role' => 'petugas',
                'updatedAt' => now(),
                'createdAt' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_health_scheduler_settings');
    }
};
