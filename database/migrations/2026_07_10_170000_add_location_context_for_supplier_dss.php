<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farm_profiles')) {
            Schema::create('farm_profiles', function (Blueprint $table) {
                $table->id();
                $table->char('user_id', 36)->charset('utf8mb4')->collation('utf8mb4_bin')->unique();
                $table->string('farm_name')->nullable();
                $table->text('address')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('user')->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('master_suppliers')) {
            Schema::table('master_suppliers', function (Blueprint $table) {
                if (! Schema::hasColumn('master_suppliers', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('alamat');
                }
                if (! Schema::hasColumn('master_suppliers', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }

        if (Schema::hasTable('toko')) {
            Schema::table('toko', function (Blueprint $table) {
                if (! Schema::hasColumn('toko', 'latitude')) {
                    $table->decimal('latitude', 10, 7)->nullable()->after('alamat');
                }
                if (! Schema::hasColumn('toko', 'longitude')) {
                    $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('toko')) {
            Schema::table('toko', function (Blueprint $table) {
                if (Schema::hasColumn('toko', 'longitude')) {
                    $table->dropColumn('longitude');
                }
                if (Schema::hasColumn('toko', 'latitude')) {
                    $table->dropColumn('latitude');
                }
            });
        }

        if (Schema::hasTable('master_suppliers')) {
            Schema::table('master_suppliers', function (Blueprint $table) {
                if (Schema::hasColumn('master_suppliers', 'longitude')) {
                    $table->dropColumn('longitude');
                }
                if (Schema::hasColumn('master_suppliers', 'latitude')) {
                    $table->dropColumn('latitude');
                }
            });
        }

        Schema::dropIfExists('farm_profiles');
    }
};
