<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('spk_rankings')) {
            return;
        }

        Schema::create('spk_rankings', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->foreignId('produk_id')->constrained('master_produks')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('master_suppliers')->onDelete('cascade');
            $table->float('final_score');
            $table->integer('ranking');
            $table->boolean('is_valid')->default(true);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_rankings');
    }
};
