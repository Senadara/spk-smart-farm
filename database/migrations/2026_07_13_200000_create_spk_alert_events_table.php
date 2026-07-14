<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spk_alert_events')) {
            return;
        }

        Schema::create('spk_alert_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('spk_fuzzy_log_id')->nullable()->index();
            $table->uuid('unit_budidaya_id')->nullable()->index();
            $table->uuid('commodity_id')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->string('alert_type', 60)->default('environment')->index();
            $table->string('severity', 30)->index();
            $table->string('status_lingkungan', 60)->nullable();
            $table->string('status_kesehatan', 60)->nullable();
            $table->string('diagnosis_kausalitas', 120)->nullable();
            $table->decimal('output_value', 6, 2)->nullable();
            $table->string('title', 180);
            $table->text('body');
            $table->json('data_json')->nullable();
            $table->string('fingerprint', 255)->index();
            $table->string('send_status', 30)->default('pending')->index();
            $table->json('response_json')->nullable();
            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_alert_events');
    }
};
