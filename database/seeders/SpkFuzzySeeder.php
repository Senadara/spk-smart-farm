<?php

namespace Database\Seeders;

use App\Services\Fuzzy\LayerChickenFuzzyDefaultTemplateService;
use Illuminate\Database\Seeder;

class SpkFuzzySeeder extends Seeder
{
    public function run(): void
    {
        $stats = app(LayerChickenFuzzyDefaultTemplateService::class)->reset();

        $this->command?->info(
            'SpkFuzzySeeder: default ayam petelur berhasil di-seed. '.
            "{$stats['variables']} variabel, {$stats['sets']} sets, {$stats['sources']} sumber, {$stats['rules']} rules."
        );
    }
}
