<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_suppliers')) {
            Schema::table('master_suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('master_suppliers', 'deskripsi')) {
                    $table->text('deskripsi')->nullable()->after('kontak');
                }
                if (! Schema::hasColumn('master_suppliers', 'kategori')) {
                    $table->string('kategori')->nullable()->after('deskripsi');
                }
                if (! Schema::hasColumn('master_suppliers', 'rating')) {
                    $table->decimal('rating', 3, 1)->default(0)->after('kategori');
                }
                if (! Schema::hasColumn('master_suppliers', 'jarak_km')) {
                    $table->unsignedInteger('jarak_km')->nullable()->after('rating');
                }
                if (! Schema::hasColumn('master_suppliers', 'logo_url')) {
                    $table->string('logo_url')->nullable()->after('jarak_km');
                }
            });
        }

        if (Schema::hasTable('spk_parameters') && ! Schema::hasColumn('spk_parameters', 'deskripsi')) {
            Schema::table('spk_parameters', function (Blueprint $table) {
                $table->text('deskripsi')->nullable()->after('tipe');
            });
        }

        if (! Schema::hasTable('spk_ahp_configurations')) {
            Schema::create('spk_ahp_configurations', function (Blueprint $table) {
                $table->id();
                $table->char('user_id', 36)->charset('utf8mb4')->collation('utf8mb4_bin');
                $table->float('cr');
                $table->boolean('is_valid')->default(false);
                $table->unsignedInteger('version')->default(1);
                $table->json('weights_snapshot')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'version']);
                $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('spk_supplier_selection_logs')) {
            Schema::create('spk_supplier_selection_logs', function (Blueprint $table) {
                $table->id();
                $table->char('user_id', 36)->charset('utf8mb4')->collation('utf8mb4_bin');
                $table->foreignId('supplier_id')->constrained('master_suppliers')->cascadeOnDelete();
                $table->foreignId('produk_id')->constrained('master_produks')->cascadeOnDelete();
                $table->float('final_score')->nullable();
                $table->unsignedTinyInteger('ranking')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'created_at']);
                $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
            });
        }
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
