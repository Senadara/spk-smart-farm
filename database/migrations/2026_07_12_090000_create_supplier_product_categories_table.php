<?php

use App\Models\SupplierProductCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_product_categories')) {
            Schema::create('supplier_product_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->string('slug', 100)->unique();
                $table->string('description', 255)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('createdAt')->nullable();
                $table->timestamp('updatedAt')->nullable();
            });
        }

        $now = now();
        foreach (SupplierProductCategory::defaultRows() as $index => $row) {
            DB::table('supplier_product_categories')->updateOrInsert(
                ['slug' => Str::slug($row['name'])],
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

        if (Schema::hasTable('produk') && Schema::hasColumn('produk', 'kategori')) {
            $validCategories = collect(SupplierProductCategory::defaultNames());

            DB::table('produk')
                ->orderBy('id')
                ->get(['id', 'nama', 'deskripsi', 'kategori'])
                ->each(function ($product) use ($validCategories) {
                    $category = trim((string) $product->kategori);
                    if ($category !== '' && $validCategories->contains($category)) {
                        return;
                    }

                    DB::table('produk')
                        ->where('id', $product->id)
                        ->update([
                            'kategori' => SupplierProductCategory::inferForProduct($category.' '.($product->nama ?? '').' '.($product->deskripsi ?? '')),
                            'updatedAt' => now(),
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_product_categories');
    }
};
