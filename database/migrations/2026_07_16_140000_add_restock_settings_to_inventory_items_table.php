<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_items', 'safety_stock_days')) {
                $table->unsignedInteger('safety_stock_days')->default(5)->after('lead_time_days');
            }

            if (! Schema::hasColumn('inventory_items', 'reorder_point_override')) {
                $table->decimal('reorder_point_override', 12, 2)->nullable()->after('safety_stock_days');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_items', 'reorder_point_override')) {
                $table->dropColumn('reorder_point_override');
            }

            if (Schema::hasColumn('inventory_items', 'safety_stock_days')) {
                $table->dropColumn('safety_stock_days');
            }
        });
    }
};
