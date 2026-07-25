<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_units')) {
            Schema::create('product_units', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->string('symbol', 30)->unique();
                $table->string('description', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();
            });
        }

        $now = now();
        foreach ($this->defaultProductUnits() as $index => $row) {
            DB::table('product_units')->updateOrInsert(
                ['symbol' => $row['symbol']],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                    'updatedAt' => $now,
                    'createdAt' => $now,
                ]
            );
        }

        if (Schema::hasTable('toko')) {
            Schema::table('toko', function (Blueprint $table) {
                if (! Schema::hasColumn('toko', 'notificationEmail')) {
                    $table->string('notificationEmail')->nullable()->after('phone');
                }
                if (! Schema::hasColumn('toko', 'approvalReason')) {
                    $table->text('approvalReason')->nullable()->after('tokoStatus');
                }
                if (! Schema::hasColumn('toko', 'registrationNotifiedAt')) {
                    $table->timestamp('registrationNotifiedAt')->nullable()->after('approvalReason');
                }
                if (! Schema::hasColumn('toko', 'approvalNotifiedAt')) {
                    $table->timestamp('approvalNotifiedAt')->nullable()->after('registrationNotifiedAt');
                }
            });
        }

        if (Schema::hasTable('pesanan')) {
            Schema::table('pesanan', function (Blueprint $table) {
                if (! Schema::hasColumn('pesanan', 'statusReason')) {
                    $table->text('statusReason')->nullable()->after('status');
                }
                if (! Schema::hasColumn('pesanan', 'statusChangedAt')) {
                    $table->timestamp('statusChangedAt')->nullable()->after('statusReason');
                }
            });
        }

        if (Schema::hasTable('produk')) {
            Schema::table('produk', function (Blueprint $table) {
                if (! Schema::hasColumn('produk', 'minimum_stock')) {
                    $table->unsignedInteger('minimum_stock')->default(10)->after('stok');
                }
                if (! Schema::hasColumn('produk', 'restock_quantity')) {
                    $table->unsignedInteger('restock_quantity')->default(0)->after('minimum_stock');
                }
            });
        }

        if (! Schema::hasTable('supplier_product_stock_movements')) {
            Schema::create('supplier_product_stock_movements', function (Blueprint $table) {
                $table->id();
                $table->string('supplier_product_id', 191)->index();
                $table->string('supplier_store_id', 191)->index();
                $table->string('type', 40);
                $table->integer('quantity');
                $table->integer('stock_before');
                $table->integer('stock_after');
                $table->string('actor_id', 191)->nullable()->index();
                $table->string('note', 500)->nullable();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_stock_movements');

        if (Schema::hasTable('produk')) {
            Schema::table('produk', function (Blueprint $table) {
                foreach (['restock_quantity', 'minimum_stock'] as $column) {
                    if (Schema::hasColumn('produk', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('pesanan')) {
            Schema::table('pesanan', function (Blueprint $table) {
                foreach (['statusChangedAt', 'statusReason'] as $column) {
                    if (Schema::hasColumn('pesanan', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('toko')) {
            Schema::table('toko', function (Blueprint $table) {
                foreach (['approvalNotifiedAt', 'registrationNotifiedAt', 'approvalReason', 'notificationEmail'] as $column) {
                    if (Schema::hasColumn('toko', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('product_units');
    }

    private function defaultProductUnits(): array
    {
        return [
            ['name' => 'Piece', 'symbol' => 'Pcs', 'description' => 'Satuan item umum.'],
            ['name' => 'Kilogram', 'symbol' => 'Kg', 'description' => 'Berat dalam kilogram.'],
            ['name' => 'Gram', 'symbol' => 'Gram', 'description' => 'Berat kecil dalam gram.'],
            ['name' => 'Liter', 'symbol' => 'Liter', 'description' => 'Volume cairan.'],
            ['name' => 'Sak', 'symbol' => 'Sak', 'description' => 'Kemasan pakan atau pupuk.'],
            ['name' => 'Karung', 'symbol' => 'Karung', 'description' => 'Kemasan besar berbentuk karung.'],
            ['name' => 'Tray', 'symbol' => 'Tray', 'description' => 'Kemasan telur per tray.'],
            ['name' => 'Botol', 'symbol' => 'Botol', 'description' => 'Kemasan cair botol.'],
            ['name' => 'Pack', 'symbol' => 'Pack', 'description' => 'Kemasan paket.'],
        ];
    }
};
