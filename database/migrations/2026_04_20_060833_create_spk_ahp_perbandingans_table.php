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
        Schema::create('spk_ahp_perbandingans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parameter_1_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->foreignId('parameter_2_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->float('nilai_skala');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_ahp_perbandingans');
    }
};
