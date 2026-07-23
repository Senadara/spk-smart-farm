<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserAccountSeeder::class,
            AyamPetelurSeeder::class,
            SupplierProductCategorySeeder::class,
            SpkSupplierSeeder::class,
            SupplierPortalSeeder::class,
            SpkDssDemoSeeder::class,
            SpkFuzzySeeder::class,
            SpkFuzzyLogHistorySeeder::class,
            InventorySeeder::class,
            DailyReportInventoryUsageSeeder::class,
        ]);
    }
}
