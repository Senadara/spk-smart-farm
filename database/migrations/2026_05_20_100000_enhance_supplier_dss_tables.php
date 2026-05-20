<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_suppliers', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('kontak');
            $table->string('kategori')->nullable()->after('deskripsi');
            $table->decimal('rating', 3, 1)->default(0)->after('kategori');
            $table->unsignedInteger('jarak_km')->nullable()->after('rating');
            $table->string('logo_url')->nullable()->after('jarak_km');
        });

        Schema::table('spk_parameters', function (Blueprint $table) {
            $table->text('deskripsi')->nullable()->after('tipe');
        });

        Schema::create('spk_ahp_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->float('cr');
            $table->boolean('is_valid')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->json('weights_snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'version']);
        });

        Schema::create('spk_supplier_selection_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreignId('supplier_id')->constrained('master_suppliers')->cascadeOnDelete();
            $table->foreignId('produk_id')->constrained('master_produks')->cascadeOnDelete();
            $table->float('final_score')->nullable();
            $table->unsignedTinyInteger('ranking')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spk_supplier_selection_logs');
        Schema::dropIfExists('spk_ahp_configurations');
        Schema::table('spk_parameters', function (Blueprint $table) {
            $table->dropColumn('deskripsi');
        });
        Schema::table('master_suppliers', function (Blueprint $table) {
            $table->dropColumn(['deskripsi', 'kategori', 'rating', 'jarak_km', 'logo_url']);
        });
    }
};
