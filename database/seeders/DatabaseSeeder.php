<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * PENTING: Hanya tambahkan seeder untuk tabel domain Laravel (spk_melon_*).
     * Tabel domain Ryan (user, unitBudidaya, dll.) di-seed via Sequelize di smart-farming-api.
     */
    public function run(): void
    {
        $this->call([
            SpkMelonKriteriaSeeder::class,
        ]);
    }
}
