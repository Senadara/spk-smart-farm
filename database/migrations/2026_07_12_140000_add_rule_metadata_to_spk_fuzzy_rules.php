<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('spk_fuzzy_rules')) {
            return;
        }

        Schema::table('spk_fuzzy_rules', function (Blueprint $table) {
            if (! Schema::hasColumn('spk_fuzzy_rules', 'recommendation')) {
                $table->text('recommendation')->nullable()->after('diagnosis');
            }

            if (! Schema::hasColumn('spk_fuzzy_rules', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('recommendation')->index();
            }
        });

        $this->backfillKausalitasRecommendations();
    }

    public function down(): void
    {
        if (! Schema::hasTable('spk_fuzzy_rules')) {
            return;
        }

        Schema::table('spk_fuzzy_rules', function (Blueprint $table) {
            if (Schema::hasColumn('spk_fuzzy_rules', 'is_active')) {
                $table->dropColumn('is_active');
            }

            if (Schema::hasColumn('spk_fuzzy_rules', 'recommendation')) {
                $table->dropColumn('recommendation');
            }
        });
    }

    private function backfillKausalitasRecommendations(): void
    {
        if (! Schema::hasColumn('spk_fuzzy_rules', 'recommendation')
            || ! Schema::hasColumn('spk_fuzzy_rules', 'diagnosis')
            || ! Schema::hasColumn('spk_fuzzy_rules', 'output_set_id')
            || ! Schema::hasTable('spk_fuzzy_sets')) {
            return;
        }

        $rules = DB::table('spk_fuzzy_rules')
            ->leftJoin('spk_fuzzy_sets', 'spk_fuzzy_rules.output_set_id', '=', 'spk_fuzzy_sets.id')
            ->where('spk_fuzzy_rules.group', 'kausalitas')
            ->whereNull('spk_fuzzy_rules.recommendation')
            ->get([
                'spk_fuzzy_rules.id',
                'spk_fuzzy_rules.diagnosis',
                'spk_fuzzy_sets.name as output_name',
            ]);

        foreach ($rules as $rule) {
            DB::table('spk_fuzzy_rules')
                ->where('id', $rule->id)
                ->update([
                    'diagnosis' => $rule->output_name ?: $rule->diagnosis,
                    'recommendation' => $rule->diagnosis,
                ]);
        }
    }
};
