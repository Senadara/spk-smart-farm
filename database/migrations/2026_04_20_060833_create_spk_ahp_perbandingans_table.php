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
        if (Schema::hasTable('spk_ahp_perbandingans')) {
            return;
        }

        Schema::create('spk_ahp_perbandingans', function (Blueprint $table) {
            $table->id();
            $table->char('user_id', 36)->charset('utf8mb4')->collation('utf8mb4_bin');
            $table->foreignId('parameter_1_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->foreignId('parameter_2_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->float('nilai_skala');
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
        Schema::dropIfExists('spk_ahp_perbandingans');
    }
};
