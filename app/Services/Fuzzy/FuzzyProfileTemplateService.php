<?php

namespace App\Services\Fuzzy;

use App\Models\SpkFuzzyInputSource;
use App\Models\SpkFuzzyProfile;
use App\Models\SpkFuzzyRule;
use App\Models\SpkFuzzyRuleCondition;
use App\Models\SpkFuzzySet;
use App\Models\SpkFuzzyVariable;
use App\Services\LivestockMasterConfigService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class FuzzyProfileTemplateService
{
    public function __construct(
        private readonly LivestockMasterConfigService $livestockMasterConfigService
    ) {}

    public function syncFromMaster(SpkFuzzyProfile $profile): array
    {
        $stats = [
            'master_ready' => false,
            'jenis_budidaya_id' => null,
            'commodity_id' => $profile->commodity_id,
            'environment_count' => 0,
            'productivity_count' => 0,
            'variables_created' => 0,
            'variables_updated' => 0,
            'sets_created' => 0,
            'sources_synced' => 0,
            'rules_created' => 0,
            'variables_removed' => 0,
            'master_variable_names' => [],
        ];

        if (! Schema::hasTable('spk_fuzzy_variables') || ! Schema::hasTable('spk_fuzzy_sets')) {
            return $stats;
        }

        $jenisBudidayaId = $this->resolveJenisBudidayaId($profile);
        $commodityId = $profile->commodity_id
            ?: $this->livestockMasterConfigService->resolveLivestockCommodityIdForJenis($jenisBudidayaId);

        DB::transaction(function () use ($profile, $jenisBudidayaId, $commodityId, &$stats) {
            $this->fillProfileContext($profile, $jenisBudidayaId, $commodityId);
            $this->ensureDefaultOutputs($profile, $stats);
            $this->ensureCausalityDefaults($profile, $stats);

            if (! $jenisBudidayaId) {
                return;
            }

            $readiness = $this->livestockMasterConfigService->readinessForJenis($jenisBudidayaId);
            $stats['master_ready'] = (bool) ($readiness['configured'] ?? false);
            $stats['jenis_budidaya_id'] = $jenisBudidayaId;
            $stats['commodity_id'] = $commodityId;

            $environmentRows = $this->livestockMasterConfigService
                ->fuzzyEnvironmentParametersForJenis($jenisBudidayaId, false);
            $productivityRows = $this->livestockMasterConfigService
                ->fuzzyProductivityFunctionsForJenis($jenisBudidayaId);

            $stats['environment_count'] = $environmentRows->count();
            $stats['productivity_count'] = $productivityRows->count();

            foreach ($environmentRows as $row) {
                $name = $this->environmentVariableName($row);
                $stats['master_variable_names'][] = $name;
                $variable = $this->ensureVariable($profile, [
                    'name' => $name,
                    'group' => 'lingkungan',
                    'type' => 'input',
                    'unit' => $row->unit,
                    'description' => 'Sensor '.$row->parameter_name.' dari Data Master ternak.',
                ], $stats);

                $this->ensureSets($variable, $this->environmentSets($row), $stats);
                $this->ensureInputSource($profile, $variable, [
                    'source_type' => 'iot',
                    'source_name' => 'iot_sensor_data',
                    'field_name' => 'value',
                    'function_name' => null,
                    'extra_config' => [
                        'parameterCode' => $row->parameter_code,
                        'maxAgeMinutes' => (int) ($row->stale_minutes ?? 30),
                        'offlineAfterMisses' => 3,
                        'fallbackValue' => is_numeric($row->fallback_value ?? null) ? (float) $row->fallback_value : null,
                        'minValue' => is_numeric($row->min_value ?? null) ? (float) $row->min_value : null,
                        'maxValue' => is_numeric($row->max_value ?? null) ? (float) $row->max_value : null,
                    ],
                ], $stats);
            }

            foreach ($productivityRows as $row) {
                $name = $this->productivityVariableName((string) $row->code);
                $stats['master_variable_names'][] = $name;
                $variable = $this->ensureVariable($profile, [
                    'name' => $name,
                    'group' => 'kesehatan',
                    'type' => 'input',
                    'unit' => $row->output_unit,
                    'description' => $row->description ?: $row->name,
                ], $stats);

                $this->ensureSets($variable, $this->productivitySets((string) $row->code), $stats);
                $this->ensureInputSource($profile, $variable, [
                    'source_type' => 'function',
                    'source_name' => null,
                    'field_name' => null,
                    'function_name' => $row->service_class,
                    'extra_config' => null,
                ], $stats);
            }

            $this->pruneInactiveMasterInputs($profile, $stats['master_variable_names'], $stats);
        });

        $stats['master_variable_names'] = collect($stats['master_variable_names'])
            ->merge(['status_lingkungan', 'indeks_kesehatan', 'label_lingkungan', 'label_kesehatan', 'diagnosis_kausalitas'])
            ->unique()
            ->values()
            ->all();

        if (($stats['variables_created'] + $stats['variables_updated'] + $stats['sets_created'] + $stats['sources_synced'] + $stats['rules_created'] + $stats['variables_removed']) > 0) {
            MamdaniEngine::clearCache($profile->id);
        }

        return $stats;
    }

    private function fillProfileContext(SpkFuzzyProfile $profile, ?string $jenisBudidayaId, ?string $commodityId): void
    {
        $payload = [];

        if ($jenisBudidayaId
            && Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')
            && $profile->jenis_budidaya_id !== $jenisBudidayaId) {
            $payload['jenis_budidaya_id'] = $jenisBudidayaId;
        }

        if ($commodityId && $profile->commodity_id !== $commodityId) {
            $payload['commodity_id'] = $commodityId;
        }

        if (! empty($payload)) {
            $profile->forceFill($payload)->save();
        }
    }

    private function ensureDefaultOutputs(SpkFuzzyProfile $profile, array &$stats): void
    {
        $statusLingkungan = $this->ensureVariable($profile, [
            'name' => 'status_lingkungan',
            'group' => 'lingkungan',
            'type' => 'output',
            'unit' => 'score',
            'description' => 'Output Engine 1 untuk status kondisi lingkungan kandang.',
        ], $stats);
        $this->ensureSets($statusLingkungan, LayerChickenFuzzyTemplateDefinition::environmentOutputSets(), $stats);

        $indeksKesehatan = $this->ensureVariable($profile, [
            'name' => 'indeks_kesehatan',
            'group' => 'kesehatan',
            'type' => 'output',
            'unit' => 'score',
            'description' => 'Output Engine 2 untuk produktivitas dan kesehatan ternak.',
        ], $stats);
        $this->ensureSets($indeksKesehatan, LayerChickenFuzzyTemplateDefinition::healthOutputSets(), $stats);
    }

    private function ensureCausalityDefaults(SpkFuzzyProfile $profile, array &$stats): void
    {
        $labelLingkungan = $this->ensureVariable($profile, [
            'name' => 'label_lingkungan',
            'group' => 'kausalitas',
            'type' => 'input',
            'unit' => 'label',
            'description' => 'Label hasil Engine 1 sebagai input Engine 3.',
        ], $stats);
        $this->ensureSets($labelLingkungan, LayerChickenFuzzyTemplateDefinition::causalityInputSets(), $stats);

        $labelKesehatan = $this->ensureVariable($profile, [
            'name' => 'label_kesehatan',
            'group' => 'kausalitas',
            'type' => 'input',
            'unit' => 'label',
            'description' => 'Label hasil Engine 2 sebagai input Engine 3.',
        ], $stats);
        $this->ensureSets($labelKesehatan, LayerChickenFuzzyTemplateDefinition::causalityInputSets(), $stats);

        $diagnosis = $this->ensureVariable($profile, [
            'name' => 'diagnosis_kausalitas',
            'group' => 'kausalitas',
            'type' => 'output',
            'unit' => 'label',
            'description' => 'Diagnosis akhir dari kombinasi lingkungan dan produktivitas.',
        ], $stats);
        $this->ensureSets($diagnosis, LayerChickenFuzzyTemplateDefinition::causalityOutputSets(), $stats);
    }

    private function ensureDefaultCausalityRules(
        SpkFuzzyProfile $profile,
        SpkFuzzyVariable $labelLingkungan,
        SpkFuzzyVariable $labelKesehatan,
        SpkFuzzyVariable $diagnosis,
        array &$stats
    ): void {
        if (! Schema::hasTable('spk_fuzzy_rules') || ! Schema::hasTable('spk_fuzzy_rule_conditions')) {
            return;
        }

        if (SpkFuzzyRule::where('profile_id', $profile->id)->where('group', 'kausalitas')->exists()) {
            return;
        }

        $sets = [
            'label_lingkungan' => $labelLingkungan->sets()->pluck('id', 'name'),
            'label_kesehatan' => $labelKesehatan->sets()->pluck('id', 'name'),
            'diagnosis_kausalitas' => $diagnosis->sets()->pluck('id', 'name'),
        ];

        foreach ($this->causalityRules() as $index => $row) {
            $outputSetId = $sets['diagnosis_kausalitas'][$row['output']] ?? null;
            $environmentSetId = $sets['label_lingkungan'][$row['environment']] ?? null;
            $productivitySetId = $sets['label_kesehatan'][$row['productivity']] ?? null;

            if (! $outputSetId || ! $environmentSetId || ! $productivitySetId) {
                continue;
            }

            $payload = [
                'profile_id' => $profile->id,
                'name' => 'Rule-kausalitas-'.($index + 1),
                'operator' => 'AND',
                'output_set_id' => $outputSetId,
                'group' => 'kausalitas',
                'diagnosis' => $row['diagnosis'],
            ];

            if (Schema::hasColumn('spk_fuzzy_rules', 'recommendation')) {
                $payload['recommendation'] = $row['recommendation'];
            }

            if (Schema::hasColumn('spk_fuzzy_rules', 'is_active')) {
                $payload['is_active'] = true;
            }

            $rule = SpkFuzzyRule::create($payload);
            SpkFuzzyRuleCondition::create([
                'rule_id' => $rule->id,
                'variable_id' => $labelLingkungan->id,
                'set_id' => $environmentSetId,
            ]);
            SpkFuzzyRuleCondition::create([
                'rule_id' => $rule->id,
                'variable_id' => $labelKesehatan->id,
                'set_id' => $productivitySetId,
            ]);

            $stats['rules_created']++;
        }
    }

    private function ensureVariable(SpkFuzzyProfile $profile, array $payload, array &$stats): SpkFuzzyVariable
    {
        $variable = SpkFuzzyVariable::firstOrNew([
            'profile_id' => $profile->id,
            'group' => $payload['group'],
            'name' => $payload['name'],
        ]);

        $isNew = ! $variable->exists;
        $variable->fill([
            'type' => $payload['type'],
            'unit' => $payload['unit'] ?? null,
            'description' => $payload['description'] ?? null,
        ]);

        if ($isNew || $variable->isDirty()) {
            $variable->save();
            $stats[$isNew ? 'variables_created' : 'variables_updated']++;
        }

        return $variable;
    }

    private function pruneInactiveMasterInputs(SpkFuzzyProfile $profile, array $activeMasterNames, array &$stats): void
    {
        if (! Schema::hasTable('spk_fuzzy_variables') || ! Schema::hasTable('spk_fuzzy_input_sources')) {
            return;
        }

        $protectedNames = collect($activeMasterNames)
            ->filter()
            ->unique()
            ->values()
            ->all();

        $staleVariables = SpkFuzzyVariable::query()
            ->select('spk_fuzzy_variables.*')
            ->join('spk_fuzzy_input_sources', 'spk_fuzzy_input_sources.variable_id', '=', 'spk_fuzzy_variables.id')
            ->where('spk_fuzzy_variables.profile_id', $profile->id)
            ->where('spk_fuzzy_variables.type', 'input')
            ->whereIn('spk_fuzzy_variables.group', ['lingkungan', 'kesehatan'])
            ->whereIn('spk_fuzzy_input_sources.source_type', ['iot', 'function'])
            ->when(! empty($protectedNames), fn ($query) => $query->whereNotIn('spk_fuzzy_variables.name', $protectedNames))
            ->get();

        foreach ($staleVariables as $variable) {
            $this->deleteVariableCascade($variable);
            $stats['variables_removed']++;
        }
    }

    private function deleteVariableCascade(SpkFuzzyVariable $variable): void
    {
        $setIds = SpkFuzzySet::where('variable_id', $variable->id)->pluck('id');
        $ruleIds = SpkFuzzyRuleCondition::where('variable_id', $variable->id)
            ->pluck('rule_id')
            ->merge(SpkFuzzyRule::whereIn('output_set_id', $setIds)->pluck('id'))
            ->filter()
            ->unique()
            ->values();

        if ($ruleIds->isNotEmpty()) {
            SpkFuzzyRuleCondition::whereIn('rule_id', $ruleIds)->delete();
            SpkFuzzyRule::whereIn('id', $ruleIds)->delete();
        }

        SpkFuzzyRuleCondition::where('variable_id', $variable->id)->delete();
        SpkFuzzyInputSource::where('variable_id', $variable->id)->delete();
        SpkFuzzySet::where('variable_id', $variable->id)->delete();
        $variable->delete();
    }

    private function ensureSets(SpkFuzzyVariable $variable, array $sets, array &$stats): void
    {
        foreach ($sets as $setPayload) {
            $set = SpkFuzzySet::firstOrNew([
                'variable_id' => $variable->id,
                'name' => $setPayload['name'],
            ]);

            if ($set->exists) {
                continue;
            }

            $set->fill([
                'shape' => $setPayload['shape'],
                'a' => $setPayload['a'],
                'b' => $setPayload['b'],
                'c' => $setPayload['c'],
                'd' => $setPayload['d'] ?? null,
            ])->save();
            $stats['sets_created']++;
        }
    }

    private function ensureInputSource(SpkFuzzyProfile $profile, SpkFuzzyVariable $variable, array $payload, array &$stats): void
    {
        if (! Schema::hasTable('spk_fuzzy_input_sources')) {
            return;
        }

        $source = SpkFuzzyInputSource::firstOrNew(['variable_id' => $variable->id]);
        $source->fill([
            'profile_id' => $profile->id,
            'source_type' => $payload['source_type'],
            'source_name' => $payload['source_name'],
            'field_name' => $payload['field_name'],
            'function_name' => $payload['function_name'],
            'extra_config' => $payload['extra_config'],
        ]);

        if (! $source->exists || $source->isDirty()) {
            $source->save();
            $stats['sources_synced']++;
        }
    }

    private function resolveJenisBudidayaId(SpkFuzzyProfile $profile): ?string
    {
        if ($profile->jenis_budidaya_id) {
            return (string) $profile->jenis_budidaya_id;
        }

        return SpkFuzzyProfile::resolveJenisBudidayaIdFromCommodity($profile->commodity_id)
            ?: $this->livestockMasterConfigService->firstLivestockJenisBudidayaId();
    }

    private function environmentVariableName(object $row): string
    {
        $code = strtoupper((string) ($row->parameter_code ?? ''));
        $label = strtolower((string) ($row->parameter_name ?? ''));

        return match (true) {
            in_array($code, ['TEMP', 'TEMPERATURE', 'SUHU'], true) || str_contains($label, 'suhu') => 'suhu',
            in_array($code, ['HUMID', 'HUMIDITY', 'RH'], true) || str_contains($label, 'lembap') || str_contains($label, 'kelembaban') => 'kelembapan',
            in_array($code, ['AMMON', 'AMMONIA', 'NH3'], true) || str_contains($label, 'amonia') => 'amonia',
            in_array($code, ['LIGHT', 'LUX', 'CAHAYA'], true) || str_contains($label, 'cahaya') => 'cahaya',
            default => Str::snake(strtolower($code ?: (string) $row->parameter_name)),
        };
    }

    private function productivityVariableName(string $code): string
    {
        return match (strtolower($code)) {
            'feed_intake' => 'pakan',
            default => Str::snake(strtolower($code)),
        };
    }

    private function environmentSets(object $row): array
    {
        $code = strtoupper((string) ($row->parameter_code ?? ''));
        $min = is_numeric($row->min_value ?? null) ? (float) $row->min_value : null;
        $max = is_numeric($row->max_value ?? null) ? (float) $row->max_value : null;
        $validatedSets = LayerChickenFuzzyTemplateDefinition::environmentSetsForCode($code);

        if (! empty($validatedSets)) {
            return $validatedSets;
        }

        return match (true) {
            in_array($code, ['TEMP', 'TEMPERATURE', 'SUHU'], true) => [
                ['name' => 'Dingin', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => max(($min ?? 20) - 4, 0), 'd' => $min ?? 22],
                ['name' => 'Nyaman', 'shape' => 'triangle', 'a' => ($min ?? 20) - 2, 'b' => (($min ?? 20) + ($max ?? 28)) / 2, 'c' => ($max ?? 28) + 2],
                ['name' => 'Panas', 'shape' => 'trapezoid', 'a' => $max ?? 28, 'b' => ($max ?? 28) + 4, 'c' => 60, 'd' => 60],
            ],
            in_array($code, ['HUMID', 'HUMIDITY', 'RH'], true) => [
                ['name' => 'Kering', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => max(($min ?? 50) - 10, 0), 'd' => ($min ?? 50) + 5],
                ['name' => 'Ideal', 'shape' => 'triangle', 'a' => $min ?? 50, 'b' => (($min ?? 50) + ($max ?? 70)) / 2, 'c' => $max ?? 70],
                ['name' => 'Basah', 'shape' => 'trapezoid', 'a' => max(($max ?? 70) - 5, 0), 'b' => ($max ?? 70) + 10, 'c' => 100, 'd' => 100],
            ],
            in_array($code, ['AMMON', 'AMMONIA', 'NH3'], true) => [
                ['name' => 'Aman', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => max(($max ?? 15) * 0.55, 1), 'd' => $max ?? 15],
                ['name' => 'Tinggi', 'shape' => 'trapezoid', 'a' => max(($max ?? 15) * 0.8, 1), 'b' => ($max ?? 15) + 5, 'c' => 50, 'd' => 50],
            ],
            default => $this->thresholdSets($min, $max, ['Rendah', 'Ideal', 'Tinggi']),
        };
    }

    private function productivitySets(string $code): array
    {
        $validatedSets = LayerChickenFuzzyTemplateDefinition::productivitySetsForCode($code);

        if (! empty($validatedSets)) {
            return $validatedSets;
        }

        return match (strtolower($code)) {
            'hdp', 'hhep' => [
                ['name' => 'Rendah', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 55, 'd' => 70],
                ['name' => 'Sedang', 'shape' => 'triangle', 'a' => 60, 'b' => 78, 'c' => 92],
                ['name' => 'Tinggi', 'shape' => 'trapezoid', 'a' => 86, 'b' => 93, 'c' => 100, 'd' => 100],
            ],
            'feed_intake' => [
                ['name' => 'Kurang', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 90, 'd' => 108],
                ['name' => 'Normal', 'shape' => 'triangle', 'a' => 100, 'b' => 115, 'c' => 130],
                ['name' => 'Berlebih', 'shape' => 'trapezoid', 'a' => 122, 'b' => 138, 'c' => 200, 'd' => 200],
            ],
            'mortalitas' => [
                ['name' => 'Wajar', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 0.3, 'd' => 0.8],
                ['name' => 'Tinggi', 'shape' => 'trapezoid', 'a' => 0.5, 'b' => 1.2, 'c' => 10, 'd' => 10],
            ],
            'fcr' => [
                ['name' => 'Baik', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 1.9, 'd' => 2.2],
                ['name' => 'Sedang', 'shape' => 'triangle', 'a' => 2, 'b' => 2.4, 'c' => 2.8],
                ['name' => 'Buruk', 'shape' => 'trapezoid', 'a' => 2.6, 'b' => 3, 'c' => 6, 'd' => 6],
            ],
            'egg_mass' => $this->thresholdSets(0, 60, ['Rendah', 'Sedang', 'Tinggi']),
            'avg_egg_weight' => [
                ['name' => 'Kecil', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 48, 'd' => 55],
                ['name' => 'Normal', 'shape' => 'triangle', 'a' => 52, 'b' => 60, 'c' => 68],
                ['name' => 'Besar', 'shape' => 'trapezoid', 'a' => 65, 'b' => 72, 'c' => 100, 'd' => 100],
            ],
            'flock_age' => [
                ['name' => 'Muda', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 16, 'd' => 20],
                ['name' => 'Produktif', 'shape' => 'triangle', 'a' => 18, 'b' => 35, 'c' => 70],
                ['name' => 'Tua', 'shape' => 'trapezoid', 'a' => 65, 'b' => 80, 'c' => 120, 'd' => 120],
            ],
            default => $this->thresholdSets(0, 100, ['Rendah', 'Sedang', 'Tinggi']),
        };
    }

    private function scoreSets(): array
    {
        return [
            ['name' => 'Buruk', 'shape' => 'trapezoid', 'a' => 0, 'b' => 0, 'c' => 15, 'd' => 28],
            ['name' => 'Waspada', 'shape' => 'triangle', 'a' => 22, 'b' => 38, 'c' => 54],
            ['name' => 'Baik', 'shape' => 'triangle', 'a' => 48, 'b' => 64, 'c' => 78],
            ['name' => 'Optimal', 'shape' => 'trapezoid', 'a' => 72, 'b' => 88, 'c' => 100, 'd' => 100],
        ];
    }

    private function labelSets(): array
    {
        return LayerChickenFuzzyTemplateDefinition::causalityInputSets();
    }

    private function causalityOutputSets(): array
    {
        return LayerChickenFuzzyTemplateDefinition::causalityOutputSets();
    }

    private function causalityRules(): array
    {
        return collect(LayerChickenFuzzyTemplateDefinition::causalityRules())
            ->map(fn (array $row) => [
                'environment' => $row['environment'],
                'productivity' => $row['productivity'],
                'output' => $row['output_set'],
                'diagnosis' => $row['output_set'],
                'recommendation' => $row['recommendation'],
            ])
            ->all();
    }

    private function thresholdSets(?float $min, ?float $max, array $labels): array
    {
        $min ??= 0.0;
        $max ??= 100.0;
        if ($max <= $min) {
            $max = $min + 100.0;
        }

        $span = max($max - $min, 1.0);
        $universeMin = max(0, $min - $span);
        $universeMax = $max + $span;
        $mid = ($min + $max) / 2;

        return [
            ['name' => $labels[0], 'shape' => 'trapezoid', 'a' => $universeMin, 'b' => $universeMin, 'c' => $min, 'd' => $mid],
            ['name' => $labels[1], 'shape' => 'triangle', 'a' => $min, 'b' => $mid, 'c' => $max],
            ['name' => $labels[2], 'shape' => 'trapezoid', 'a' => $mid, 'b' => $max, 'c' => $universeMax, 'd' => $universeMax],
        ];
    }
}
