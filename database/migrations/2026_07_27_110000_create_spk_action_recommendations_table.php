<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('spk_action_recommendations')) {
            return;
        }

        Schema::create('spk_action_recommendations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('spk_fuzzy_log_id')->nullable()->unique();
            $table->uuid('unit_budidaya_id')->nullable()->index();
            $table->uuid('commodity_id')->nullable()->index();
            $table->uuid('owner_id')->nullable()->index();
            $table->uuid('assigned_task_id')->nullable()->index();
            $table->string('status', 30)->default('open')->index();
            $table->string('priority', 20)->default('medium')->index();
            $table->decimal('score', 6, 2)->nullable();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('fingerprint', 255)->index();
            $table->string('disable_reason', 160)->nullable();
            $table->timestamp('assigned_at')->nullable()->index();
            $table->timestamp('disabled_at')->nullable()->index();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent()->useCurrentOnUpdate();

            $table->index(['status', 'unit_budidaya_id']);
            $table->index(['status', 'commodity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_action_recommendations');
    }
};
