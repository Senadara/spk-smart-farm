<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_supplier_product_links')) {
            return;
        }

        Schema::create('inventory_supplier_product_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->string('supplier_product_id', 36)->index();
            $table->decimal('conversion_qty', 12, 4)->default(1);
            $table->string('conversion_unit', 30)->nullable();
            $table->boolean('is_preferred')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['inventory_item_id', 'supplier_product_id'], 'inventory_supplier_product_unique');
            $table->index(['inventory_item_id', 'is_preferred'], 'inventory_supplier_product_preferred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_supplier_product_links');
    }
};
