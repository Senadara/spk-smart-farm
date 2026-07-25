<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spk_alert_events')) {
            return;
        }

        Schema::table('spk_alert_events', function (Blueprint $table) {
            if (! Schema::hasColumn('spk_alert_events', 'read_at')) {
                $table->timestamp('read_at')->nullable()->index()->after('sent_at');
            }

            if (! Schema::hasColumn('spk_alert_events', 'read_by')) {
                $table->uuid('read_by')->nullable()->index()->after('read_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spk_alert_events')) {
            return;
        }

        Schema::table('spk_alert_events', function (Blueprint $table) {
            foreach (['read_by', 'read_at'] as $column) {
                if (Schema::hasColumn('spk_alert_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
