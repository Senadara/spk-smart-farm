<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('spk_ahp_bobots')) {
            Schema::create('spk_ahp_bobots', function (Blueprint $table) {
                $table->id();
                $table->char('user_id', 36);
                $table->index('user_id');
                $table->foreignId('parameter_id')->constrained('spk_parameters')->onDelete('cascade');
                $table->float('bobot');
                $table->boolean('is_valid')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spk_ahp_bobots');
    }
};
