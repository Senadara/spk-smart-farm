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
        Schema::table('panen', function (Blueprint $table) {
            $table->decimal('berat', 10, 2)->nullable()->after('jumlah')
                  ->comment('Berat panen dalam kg (egg mass). Fallback = jumlah * 0.06');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panen', function (Blueprint $table) {
            $table->dropColumn('berat');
        });
    }
};
