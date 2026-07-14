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

        if (! Schema::hasColumn('inventory_items', 'mobile_inventaris_id')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->char('mobile_inventaris_id', 36)->nullable()->unique()->after('sku');
            });
        }

        if (! Schema::hasColumn('inventory_items', 'synced_from_mobile_at')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->timestamp('synced_from_mobile_at')->nullable()->after('last_restock_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            return;
        }

        if (Schema::hasColumn('inventory_items', 'mobile_inventaris_id')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropUnique(['mobile_inventaris_id']);
                $table->dropColumn('mobile_inventaris_id');
            });
        }

        if (Schema::hasColumn('inventory_items', 'synced_from_mobile_at')) {
            Schema::table('inventory_items', function (Blueprint $table) {
                $table->dropColumn('synced_from_mobile_at');
            });
        }
    }
};
