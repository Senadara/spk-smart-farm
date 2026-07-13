<?php

namespace Database\Seeders;

use App\Models\SupplierProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SupplierProductCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (SupplierProductCategory::defaultRows() as $index => $row) {
            SupplierProductCategory::query()->updateOrCreate(
                ['slug' => Str::slug($row['name'])],
                [
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
