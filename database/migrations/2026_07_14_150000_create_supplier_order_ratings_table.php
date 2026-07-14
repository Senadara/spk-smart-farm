<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_order_ratings')) {
            return;
        }

        Schema::create('supplier_order_ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('order_id', 36)->index();
            $table->char('user_id', 36)->index();
            $table->char('store_id', 36)->nullable()->index();
            $table->foreignId('supplier_id')->nullable()->constrained('master_suppliers')->nullOnDelete();
            $table->char('product_id', 36)->nullable()->index();
            $table->foreignId('master_produk_id')->nullable()->constrained('master_produks')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_order_ratings');
    }
};
