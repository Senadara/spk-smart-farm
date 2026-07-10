<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inventory_items')) {
            Schema::create('inventory_items', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->unique();
                $table->string('name');
                $table->string('category')->default('Umum');
                $table->decimal('stock', 12, 2)->default(0);
                $table->string('unit', 30)->default('unit');
                $table->decimal('daily_usage', 12, 2)->default(0);
                $table->decimal('minimum_stock', 12, 2)->default(0);
                $table->decimal('reorder_point', 12, 2)->default(0);
                $table->unsignedInteger('lead_time_days')->default(1);
                $table->foreignId('supplier_id')->nullable()->constrained('master_suppliers')->nullOnDelete();
                $table->char('unit_budidaya_id', 36)->nullable()->index();
                $table->string('photo_path')->nullable();
                $table->text('notes')->nullable();
                $table->timestamp('last_restock_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['category', 'is_active']);
            });
        }

        if (! Schema::hasTable('inventory_movements')) {
            Schema::create('inventory_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
                $table->enum('type', ['inflow', 'outflow', 'adjustment']);
                $table->decimal('quantity', 12, 2);
                $table->decimal('stock_before', 12, 2)->default(0);
                $table->decimal('stock_after', 12, 2)->default(0);
                $table->string('unit', 30)->default('unit');
                $table->char('unit_budidaya_id', 36)->nullable()->index();
                $table->string('user_id')->nullable()->index();
                $table->text('note')->nullable();
                $table->timestamps();

                $table->index(['type', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');
    }
};
