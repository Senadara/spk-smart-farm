<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spk_fuzzy_logs') || Schema::hasColumn('spk_fuzzy_logs', 'input_meta_json')) {
            return;
        }

        Schema::table('spk_fuzzy_logs', function (Blueprint $table) {
            $table->json('input_meta_json')->nullable()->after('input_json');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('spk_fuzzy_logs') || ! Schema::hasColumn('spk_fuzzy_logs', 'input_meta_json')) {
            return;
        }

        Schema::table('spk_fuzzy_logs', function (Blueprint $table) {
            $table->dropColumn('input_meta_json');
        });
    }
};
