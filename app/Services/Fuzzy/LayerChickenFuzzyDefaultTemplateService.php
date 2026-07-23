<?php

namespace App\Services\Fuzzy;

use App\Models\SpkFuzzyProfile;
use App\Services\LivestockMasterConfigService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LayerChickenFuzzyDefaultTemplateService
{
    public function __construct(
        private readonly LivestockMasterConfigService $livestockMasterConfigService
    ) {}

    public function reset(): array
    {
        $this->ensureSchema();
        $this->livestockMasterConfigService->ensureDefaultFunctionCatalog();

        $stats = DB::transaction(function () {
            [$jenisBudidayaId, $commodityId] = $this->resolveLayerContext();
            $this->syncMasterConfiguration($jenisBudidayaId, $commodityId);

            $profile = $this->ensureProfile($jenisBudidayaId, $commodityId);
            $this->clearProfileConfig($profile->id);

            $variableIds = $this->seedVariables($profile->id);
            $setIds = $this->seedSets($variableIds);
            $sourceCount = $this->seedInputSources($profile->id, $variableIds);
            $ruleCount = $this->seedRules($profile->id, $variableIds, $setIds);

            return [
                'profile_id' => $profile->id,
                'profile' => $profile->fresh(['commodity', 'jenisBudidaya']),
                'variables' => count($variableIds),
                'sets' => collect($setIds)->flatten()->count(),
                'sources' => $sourceCount,
                'rules' => $ruleCount,
                'jenis_budidaya_id' => $jenisBudidayaId,
                'commodity_id' => $commodityId,
            ];
        });

        MamdaniEngine::clearCache($stats['profile_id']);

        return $stats;
    }

    private function ensureSchema(): void
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
                throw new \RuntimeException("Tabel {$table} belum tersedia. Jalankan migration terlebih dahulu.");
            }
        }
    }

    private function resolveLayerContext(): array
    {
        $jenisBudidayaId = null;
        $commodityId = null;

        if (Schema::hasTable('jenisBudidaya')) {
            $jenisBudidayaId = DB::table('jenisBudidaya')
                ->where('isDeleted', 0)
                ->where('tipe', 'hewan')
                ->where(function ($query) {
                    $query->where('nama', 'like', '%Petelur%')
                        ->orWhere('nama', 'like', '%Layer%')
                        ->orWhere('nama', 'like', '%Ayam%');
                })
                ->orderByRaw("CASE WHEN nama LIKE '%Petelur%' OR nama LIKE '%Layer%' THEN 0 ELSE 1 END")
                ->value('id');

            if (! $jenisBudidayaId) {
                $jenisBudidayaId = (string) Str::uuid();
                $payload = [
                    'id' => $jenisBudidayaId,
                    'nama' => 'Ayam Petelur',
                    'tipe' => 'hewan',
                    'isDeleted' => 0,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ];

                if (Schema::hasColumn('jenisBudidaya', 'status')) {
                    $payload['status'] = true;
                }

                DB::table('jenisBudidaya')->insert($payload);
            }
        }

        if ($jenisBudidayaId && Schema::hasTable('komoditas')) {
            $commodityId = DB::table('komoditas')
                ->where('jenisBudidayaId', $jenisBudidayaId)
                ->where('isDeleted', 0)
                ->where(function ($query) {
                    $query->where('nama', 'like', '%Layer%')
                        ->orWhere('nama', 'like', '%Petelur%')
                        ->orWhere('nama', 'like', '%Ayam%');
                })
                ->orderByRaw("CASE WHEN nama LIKE '%Layer%' OR nama LIKE '%Petelur%' THEN 0 ELSE 1 END")
                ->value('id');

            if (! $commodityId) {
                $commodityId = (string) Str::uuid();
                DB::table('komoditas')->insert([
                    'id' => $commodityId,
                    'jenisBudidayaId' => $jenisBudidayaId,
                    'satuanId' => $this->ensureUnitEkor(),
                    'nama' => 'Ayam Layer',
                    'isDeleted' => 0,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ]);
            }
        }

        return [$jenisBudidayaId, $commodityId];
    }

    private function ensureUnitEkor(): ?string
    {
        if (! Schema::hasTable('satuan')) {
            return null;
        }

        $existing = DB::table('satuan')
            ->where('isDeleted', 0)
            ->where(function ($query) {
                $query->where('nama', 'like', '%Ekor%');
                if (Schema::hasColumn('satuan', 'lambang')) {
                    $query->orWhere('lambang', 'ekor');
                }
            })
            ->value('id');

        if ($existing) {
            return (string) $existing;
        }

        $id = (string) Str::uuid();
        $payload = [
            'id' => $id,
            'nama' => 'Ekor',
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ];

        if (Schema::hasColumn('satuan', 'lambang')) {
            $payload['lambang'] = 'ekor';
        }

        DB::table('satuan')->insert($payload);

        return $id;
    }

    private function syncMasterConfiguration(?string $jenisBudidayaId, ?string $commodityId): void
    {
        if (! $jenisBudidayaId || ! $this->livestockMasterConfigService->hasSchema()) {
            return;
        }

        $configId = DB::table('livestock_master_configs')
            ->where('jenis_budidaya_id', $jenisBudidayaId)
            ->value('id') ?: (string) Str::uuid();

        DB::table('livestock_master_configs')->updateOrInsert(
            ['jenis_budidaya_id' => $jenisBudidayaId],
            [
                'id' => $configId,
                'commodity_id' => $commodityId,
                'status' => 'configured',
                'notes' => 'Default ayam petelur berdasarkan rule validasi pakar.',
                'configured_at' => now(),
                'createdAt' => now(),
                'updatedAt' => now(),
            ]
        );

        $fuzzyEnvironmentCodes = collect(LayerChickenFuzzyTemplateDefinition::masterEnvironmentRows())
            ->where('required_for_fuzzy', true)
            ->pluck('parameter_code')
            ->values()
            ->all();

        foreach (LayerChickenFuzzyTemplateDefinition::masterEnvironmentRows() as $index => $row) {
            $parameterId = $this->ensureIotParameter($row);
            $payload = [
                'id' => DB::table('livestock_environment_parameters')
                    ->where('config_id', $configId)
                    ->where('parameter_code', $row['parameter_code'])
                    ->value('id') ?: (string) Str::uuid(),
                'parameter_id' => $parameterId,
                'parameter_code' => $row['parameter_code'],
                'parameter_name' => $row['parameter_name'],
                'unit' => $row['unit'],
                'min_value' => $row['min_value'],
                'max_value' => $row['max_value'],
                'fallback_value' => $row['fallback_value'],
                'stale_minutes' => $row['stale_minutes'],
                'required_for_iot' => $row['required_for_iot'],
                'required_for_fuzzy' => $row['required_for_fuzzy'],
                'sort_order' => $index,
                'is_active' => true,
                'createdAt' => now(),
                'updatedAt' => now(),
            ];

            if (Schema::hasColumn('livestock_environment_parameters', 'icon_key')) {
                $payload['icon_key'] = $row['icon_key'];
            }

            DB::table('livestock_environment_parameters')->updateOrInsert(
                ['config_id' => $configId, 'parameter_code' => $row['parameter_code']],
                $payload
            );
        }

        DB::table('livestock_environment_parameters')
            ->where('config_id', $configId)
            ->whereNotIn('parameter_code', $fuzzyEnvironmentCodes)
            ->update(['required_for_fuzzy' => false, 'updatedAt' => now()]);

        $functionRows = DB::table('livestock_productivity_functions')
            ->whereIn('code', LayerChickenFuzzyTemplateDefinition::fuzzyProductivityCodes())
            ->where('is_active', true)
            ->get(['id', 'code'])
            ->keyBy('code');

        $functionIds = [];
        foreach (LayerChickenFuzzyTemplateDefinition::fuzzyProductivityCodes() as $index => $code) {
            $functionId = $functionRows[$code]->id ?? null;
            if (! $functionId) {
                continue;
            }

            $functionIds[] = $functionId;
            DB::table('livestock_productivity_function_configs')->updateOrInsert(
                ['config_id' => $configId, 'function_id' => $functionId],
                [
                    'id' => DB::table('livestock_productivity_function_configs')
                        ->where('config_id', $configId)
                        ->where('function_id', $functionId)
                        ->value('id') ?: (string) Str::uuid(),
                    'required_for_fuzzy' => true,
                    'aggregation_scope' => $code === 'mortalitas' ? 'week' : 'today',
                    'sort_order' => $index,
                    'is_active' => true,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ]
            );
        }

        if (! empty($functionIds)) {
            DB::table('livestock_productivity_function_configs')
                ->where('config_id', $configId)
                ->whereNotIn('function_id', $functionIds)
                ->update(['required_for_fuzzy' => false, 'updatedAt' => now()]);
        }
    }

    private function ensureIotParameter(array $row): ?string
    {
        if (! Schema::hasTable('iot_parameter')) {
            return null;
        }

        $existing = DB::table('iot_parameter')
            ->where('parameterCode', $row['parameter_code'])
            ->value('id');

        if ($existing) {
            DB::table('iot_parameter')
                ->where('id', $existing)
                ->update(array_filter([
                    'parameterName' => $row['parameter_name'],
                    'unit' => $row['unit'],
                    'updatedAt' => Schema::hasColumn('iot_parameter', 'updatedAt') ? now() : null,
                ], fn ($value) => $value !== null));

            return (string) $existing;
        }

        $id = (string) Str::uuid();
        $payload = [
            'id' => $id,
            'parameterCode' => $row['parameter_code'],
            'parameterName' => $row['parameter_name'],
            'unit' => $row['unit'],
            'createdAt' => now(),
        ];

        if (Schema::hasColumn('iot_parameter', 'description')) {
            $payload['description'] = 'Dikelola dari Data Master ternak.';
        }

        if (Schema::hasColumn('iot_parameter', 'updatedAt')) {
            $payload['updatedAt'] = now();
        }

        DB::table('iot_parameter')->insert($payload);

        return $id;
    }

    private function ensureProfile(?string $jenisBudidayaId, ?string $commodityId): SpkFuzzyProfile
    {
        $profile = SpkFuzzyProfile::firstOrNew([
            'name' => LayerChickenFuzzyTemplateDefinition::PROFILE_NAME,
        ]);

        if ($jenisBudidayaId) {
            $activeQuery = SpkFuzzyProfile::where('id', '<>', $profile->id ?: '');
            if (Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
                $activeQuery->where('jenis_budidaya_id', $jenisBudidayaId);
            } elseif ($commodityId) {
                $activeQuery->where('commodity_id', $commodityId);
            }
            $activeQuery->update(['is_active' => false]);
        }

        $profile->fill([
            'commodity_id' => $commodityId,
            'version' => LayerChickenFuzzyTemplateDefinition::VERSION,
            'status' => 'active',
            'is_active' => true,
            'reviewed_at' => $profile->reviewed_at ?: now(),
            'notes' => 'Template default ayam petelur berdasarkan rule validasi pakar dari Excel: rentang nilai, evaluasi lingkungan, evaluasi produktivitas, dan integrasi kausalitas.',
        ]);

        if ($jenisBudidayaId && Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
            $profile->jenis_budidaya_id = $jenisBudidayaId;
        }

        $profile->save();

        return $profile;
    }

    private function clearProfileConfig(string $profileId): void
    {
        $variableIds = DB::table('spk_fuzzy_variables')
            ->where('profile_id', $profileId)
            ->pluck('id');
        $ruleIds = DB::table('spk_fuzzy_rules')
            ->where('profile_id', $profileId)
            ->pluck('id');

        if ($ruleIds->isNotEmpty()) {
            DB::table('spk_fuzzy_rule_conditions')->whereIn('rule_id', $ruleIds)->delete();
        }

        if ($variableIds->isNotEmpty()) {
            $setIds = DB::table('spk_fuzzy_sets')
                ->whereIn('variable_id', $variableIds)
                ->pluck('id');

            DB::table('spk_fuzzy_rule_conditions')->whereIn('variable_id', $variableIds)->delete();
            if ($setIds->isNotEmpty()) {
                DB::table('spk_fuzzy_rule_conditions')->whereIn('set_id', $setIds)->delete();
                DB::table('spk_fuzzy_sets')->whereIn('id', $setIds)->delete();
            }

            DB::table('spk_fuzzy_input_sources')
                ->where('profile_id', $profileId)
                ->orWhereIn('variable_id', $variableIds)
                ->delete();
        } else {
            DB::table('spk_fuzzy_input_sources')->where('profile_id', $profileId)->delete();
        }

        DB::table('spk_fuzzy_rules')->where('profile_id', $profileId)->delete();
        DB::table('spk_fuzzy_variables')->where('profile_id', $profileId)->delete();
    }

    private function seedVariables(string $profileId): array
    {
        $ids = [];

        foreach (LayerChickenFuzzyTemplateDefinition::variables() as $row) {
            $id = (string) Str::uuid();
            DB::table('spk_fuzzy_variables')->insert([
                'id' => $id,
                'profile_id' => $profileId,
                'group' => $row['group'],
                'name' => $row['name'],
                'type' => $row['type'],
                'unit' => $row['unit'],
                'description' => $row['description'],
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);
            $ids[$row['name']] = $id;
        }

        return $ids;
    }

    private function seedSets(array $variableIds): array
    {
        $ids = [];

        foreach (LayerChickenFuzzyTemplateDefinition::sets() as $variableName => $sets) {
            if (! isset($variableIds[$variableName])) {
                continue;
            }

            foreach ($sets as $row) {
                $id = (string) Str::uuid();
                DB::table('spk_fuzzy_sets')->insert([
                    'id' => $id,
                    'variable_id' => $variableIds[$variableName],
                    'name' => $row['name'],
                    'shape' => $row['shape'],
                    'a' => $row['a'],
                    'b' => $row['b'],
                    'c' => $row['c'],
                    'd' => $row['d'] ?? null,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ]);
                $ids[$variableName][$row['name']] = $id;
            }
        }

        return $ids;
    }

    private function seedInputSources(string $profileId, array $variableIds): int
    {
        $count = 0;

        foreach (LayerChickenFuzzyTemplateDefinition::sourceDefinitions() as $variableName => $row) {
            if (! isset($variableIds[$variableName])) {
                continue;
            }

            DB::table('spk_fuzzy_input_sources')->insert([
                'id' => (string) Str::uuid(),
                'profile_id' => $profileId,
                'variable_id' => $variableIds[$variableName],
                'source_type' => $row['source_type'],
                'source_name' => $row['source_name'],
                'field_name' => $row['field_name'],
                'function_name' => $row['function_name'],
                'extra_config' => $row['extra_config'] ? json_encode($row['extra_config']) : null,
                'createdAt' => now(),
                'updatedAt' => now(),
            ]);
            $count++;
        }

        return $count;
    }

    private function seedRules(string $profileId, array $variableIds, array $setIds): int
    {
        $count = 0;
        $groupCounters = [
            'lingkungan' => 0,
            'kesehatan' => 0,
            'kausalitas' => 0,
        ];

        foreach (LayerChickenFuzzyTemplateDefinition::rules() as $rule) {
            $group = $rule['group'] ?? $this->resolveRuleGroup($rule);
            $groupCounters[$group]++;
            $outputVariable = $this->outputVariableForGroup($group);
            $outputSet = $rule['output_set'];
            $outputSetId = $setIds[$outputVariable][$outputSet] ?? null;

            if (! $outputSetId) {
                throw new \RuntimeException("Output set {$outputVariable}.{$outputSet} tidak ditemukan.");
            }

            $ruleId = (string) Str::uuid();
            $payload = [
                'id' => $ruleId,
                'profile_id' => $profileId,
                'name' => 'Rule-'.$group.'-'.str_pad((string) $groupCounters[$group], 2, '0', STR_PAD_LEFT),
                'operator' => 'AND',
                'output_set_id' => $outputSetId,
                'group' => $group,
                'diagnosis' => $group === 'kausalitas' ? $outputSet : $rule['diagnosis'],
                'createdAt' => now(),
                'updatedAt' => now(),
            ];

            if (Schema::hasColumn('spk_fuzzy_rules', 'recommendation')) {
                $payload['recommendation'] = $rule['recommendation'] ?? null;
            }

            if (Schema::hasColumn('spk_fuzzy_rules', 'is_active')) {
                $payload['is_active'] = true;
            }

            DB::table('spk_fuzzy_rules')->insert($payload);

            foreach ($this->conditionsForRule($group, $rule) as [$variableName, $setName]) {
                $variableId = $variableIds[$variableName] ?? null;
                $setId = $setIds[$variableName][$setName] ?? null;

                if (! $variableId || ! $setId) {
                    throw new \RuntimeException("Kondisi {$variableName}.{$setName} tidak ditemukan.");
                }

                DB::table('spk_fuzzy_rule_conditions')->insert([
                    'id' => (string) Str::uuid(),
                    'rule_id' => $ruleId,
                    'variable_id' => $variableId,
                    'set_id' => $setId,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ]);
            }

            $count++;
        }

        return $count;
    }

    private function resolveRuleGroup(array $rule): string
    {
        if (isset($rule['environment'], $rule['productivity'])) {
            return 'kausalitas';
        }

        $firstCondition = $rule['conditions'][0][0] ?? null;

        return in_array($firstCondition, ['suhu', 'kelembapan', 'amonia'], true)
            ? 'lingkungan'
            : 'kesehatan';
    }

    private function outputVariableForGroup(string $group): string
    {
        return match ($group) {
            'lingkungan' => 'status_lingkungan',
            'kesehatan' => 'indeks_kesehatan',
            default => 'diagnosis_kausalitas',
        };
    }

    private function conditionsForRule(string $group, array $rule): array
    {
        if ($group !== 'kausalitas') {
            return $rule['conditions'];
        }

        return [
            ['label_lingkungan', $rule['environment']],
            ['label_kesehatan', $rule['productivity']],
        ];
    }
}
