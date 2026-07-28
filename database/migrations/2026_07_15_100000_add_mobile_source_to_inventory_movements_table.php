<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        if (! Schema::hasColumn('inventory_movements', 'source_table')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->string('source_table', 60)->nullable()->after('note');
            });
        }

        if (! Schema::hasColumn('inventory_movements', 'source_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->char('source_id', 36)->nullable()->after('source_table');
            });
        }

        $exists = DB::select("SHOW INDEX FROM inventory_movements WHERE KEY_NAME = 'inventory_movements_source_unique'");
        if (empty($exists)) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->unique(['source_table', 'source_id'], 'inventory_movements_source_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropUnique('inventory_movements_source_unique');
        });

        if (Schema::hasColumn('inventory_movements', 'source_id')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropColumn('source_id');
            });
        }

        if (Schema::hasColumn('inventory_movements', 'source_table')) {
            Schema::table('inventory_movements', function (Blueprint $table) {
                $table->dropColumn('source_table');
            });
        }
    }
};
