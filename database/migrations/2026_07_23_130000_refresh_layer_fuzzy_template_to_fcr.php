<?php

use App\Services\Fuzzy\LayerChickenFuzzyDefaultTemplateService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'spk_fuzzy_profiles',
            'spk_fuzzy_variables',
            'spk_fuzzy_sets',
            'spk_fuzzy_rules',
            'spk_fuzzy_rule_conditions',
            'spk_fuzzy_input_sources',
        ] as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        app(LayerChickenFuzzyDefaultTemplateService::class)->reset();
    }

    public function down(): void
    {
        // Expert revision is intentionally one-way. Restore by running the older seed definition if needed.
    }
};
