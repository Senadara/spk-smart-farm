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
        Schema::create('spk_supplier_parameter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('master_suppliers')->onDelete('cascade');
            $table->foreignId('produk_id')->constrained('master_produks')->onDelete('cascade');
            $table->foreignId('parameter_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->float('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_supplier_parameter_values');
    }
};
