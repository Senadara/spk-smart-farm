<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('produk') && ! Schema::hasColumn('produk', 'kategori')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->string('kategori', 80)->nullable()->after('deskripsi')->index();
            });
        }

        if (Schema::hasTable('toko') && ! Schema::hasColumn('toko', 'kategori')) {
            Schema::table('toko', function (Blueprint $table) {
                $table->string('kategori', 120)->nullable()->after('deskripsi');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('produk') && Schema::hasColumn('produk', 'kategori')) {
            Schema::table('produk', function (Blueprint $table) {
                $table->dropIndex(['kategori']);
            });

            Schema::table('produk', function (Blueprint $table) {
                $table->dropColumn('kategori');
            });
        }

        if (Schema::hasTable('toko') && Schema::hasColumn('toko', 'kategori')) {
            Schema::table('toko', function (Blueprint $table) {
                $table->dropColumn('kategori');
            });
        }
    }
};
