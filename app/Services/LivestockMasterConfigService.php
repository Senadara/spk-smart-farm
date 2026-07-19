<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LivestockMasterConfigService
{
    public function hasSchema(): bool
    {
        return Schema::hasTable('livestock_master_configs')
            && Schema::hasTable('livestock_environment_parameters')
            && Schema::hasTable('livestock_productivity_functions')
            && Schema::hasTable('livestock_productivity_function_configs');
    }

    public function defaultProductivityFunctions(): array
    {
        return [
            'hdp' => [
                'name' => 'HDP - Hen Day Production',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateHdp',
                'output_unit' => '%',
                'description' => 'Persentase produksi harian terhadap populasi aktif.',
                'required_inputs' => ['panen.jumlah', 'unitBudidaya.jumlah'],
            ],
            'feed_intake' => [
                'name' => 'Pakan per ekor per hari',
                'service_class' => 'App\\Services\\Fuzzy\\CalculatePakan',
                'output_unit' => 'g/ekor',
                'description' => 'Estimasi konsumsi pakan harian per ekor dari laporan ternak.',
                'required_inputs' => ['harianTernak.pakan', 'unitBudidaya.jumlah'],
            ],
            'mortalitas' => [
                'name' => 'Mortalitas bulan berjalan',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateMortalitas',
                'output_unit' => '%',
                'description' => 'Persentase kematian terhadap populasi pada periode berjalan.',
                'required_inputs' => ['kematian.id', 'unitBudidaya.jumlah'],
            ],
            'fcr' => [
                'name' => 'FCR - Feed Conversion Ratio',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateFcr',
                'output_unit' => 'rasio',
                'description' => 'Rasio pakan terhadap egg mass dari panen.',
                'required_inputs' => ['harianTernak.pakan', 'panen.berat'],
            ],
        ];
    }

    public function ensureDefaultFunctionCatalog(): void
    {
        if (! Schema::hasTable('livestock_productivity_functions')) {
            return;
        }

        foreach ($this->defaultProductivityFunctions() as $code => $meta) {
            $id = DB::table('livestock_productivity_functions')->where('code', $code)->value('id') ?: (string) Str::uuid();

            DB::table('livestock_productivity_functions')->updateOrInsert(
                ['code' => $code],
                [
                    'id' => $id,
                    'name' => $meta['name'],
                    'service_class' => $meta['service_class'],
                    'output_unit' => $meta['output_unit'],
                    'description' => $meta['description'],
                    'required_inputs' => json_encode($meta['required_inputs']),
                    'is_active' => true,
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ]
            );
        }
    }

    public function livestockTypes(): Collection
    {
        if (! Schema::hasTable('jenisBudidaya')) {
            return collect();
        }

        return DB::table('jenisBudidaya')
            ->where('isDeleted', 0)
            ->where('tipe', 'hewan')
            ->orderBy('nama')
            ->get(['id', 'nama', 'tipe']);
    }

    public function livestockCommodities(): Collection
    {
        if (! Schema::hasTable('komoditas') || ! Schema::hasTable('jenisBudidaya')) {
            return collect();
        }

        return DB::table('komoditas')
            ->join('jenisBudidaya', 'komoditas.jenisBudidayaId', '=', 'jenisBudidaya.id')
            ->where('komoditas.isDeleted', 0)
            ->where('jenisBudidaya.isDeleted', 0)
            ->where('jenisBudidaya.tipe', 'hewan')
            ->orderBy('komoditas.nama')
            ->get([
                'komoditas.id',
                'komoditas.nama',
                'komoditas.jenisBudidayaId',
                'jenisBudidaya.nama as jenis_budidaya_nama',
            ]);
    }

    public function livestockCommodityIds(): array
    {
        return $this->livestockCommodities()
            ->pluck('id')
            ->filter()
            ->values()
            ->all();
    }

    public function isLivestockCommodity(?string $commodityId): bool
    {
        if (! $commodityId) {
            return false;
        }

        return $this->livestockCommodities()->contains('id', $commodityId);
    }

    public function resolveLivestockCommodityId(?string $requestedId): ?string
    {
        $commodities = $this->livestockCommodities();

        if ($requestedId && $commodities->contains('id', $requestedId)) {
            return $requestedId;
        }

        $layer = $commodities->first(fn ($commodity) => str_contains(strtolower((string) $commodity->nama), 'layer'));
        if ($layer) {
            return $layer->id;
        }

        return $commodities->first()?->id;
    }

    public function firstLivestockJenisBudidayaId(): ?string
    {
        return $this->livestockTypes()->first()?->id;
    }

    public function overview(?string $selectedJenisBudidayaId = null): array
    {
        $this->ensureDefaultFunctionCatalog();

        $types = $this->livestockTypes();
        $selectedJenisBudidayaId = $selectedJenisBudidayaId && $types->contains('id', $selectedJenisBudidayaId)
            ? $selectedJenisBudidayaId
            : $types->first()?->id;

        $jenisIds = $types->pluck('id')->filter()->values()->all();
        $commoditiesByJenis = $this->livestockCommodities()->groupBy('jenisBudidayaId');

        $coopCounts = Schema::hasTable('unitBudidaya') && ! empty($jenisIds)
            ? DB::table('unitBudidaya')
                ->whereIn('jenisBudidayaId', $jenisIds)
                ->where('isDeleted', 0)
                ->select('jenisBudidayaId', DB::raw('COUNT(*) as total'))
                ->groupBy('jenisBudidayaId')
                ->pluck('total', 'jenisBudidayaId')
            : collect();

        $typeRows = $types->map(function ($type) use ($commoditiesByJenis, $coopCounts) {
            $readiness = $this->readinessForJenis((string) $type->id);
            $commodities = $commoditiesByJenis->get($type->id, collect());

            return [
                'id' => $type->id,
                'nama' => $type->nama,
                'commodity_count' => $commodities->count(),
                'commodities' => $commodities->pluck('nama')->values()->all(),
                'primary_commodity_id' => $commodities->first()?->id,
                'coop_count' => (int) ($coopCounts[$type->id] ?? 0),
                'readiness' => $readiness,
            ];
        })->values();

        $selectedType = $typeRows->firstWhere('id', $selectedJenisBudidayaId);
        $selectedConfig = $selectedJenisBudidayaId ? $this->configForJenis($selectedJenisBudidayaId) : null;

        return [
            'schemaReady' => $this->hasSchema(),
            'types' => $typeRows,
            'selectedJenisBudidayaId' => $selectedJenisBudidayaId,
            'selectedType' => $selectedType,
            'selectedConfig' => $selectedConfig,
            'environmentParameters' => $selectedConfig
                ? $this->environmentRowsForConfig((string) $selectedConfig->id)
                : collect($this->defaultEnvironmentRows())->map(fn ($row) => (object) $row),
            'selectedFunctionIds' => $selectedConfig
                ? $this->selectedFunctionIdsForConfig((string) $selectedConfig->id)
                : [],
            'productivityFunctions' => $this->productivityCatalog(),
        ];
    }

    public function configForJenis(string $jenisBudidayaId): ?object
    {
        if (! $this->hasSchema()) {
            return null;
        }

        return DB::table('livestock_master_configs')
            ->where('jenis_budidaya_id', $jenisBudidayaId)
            ->first();
    }

    public function readinessForCommodity(?string $commodityId): array
    {
        if (! $commodityId) {
            return $this->emptyReadiness('no_commodity', 'Belum ada komoditas ternak', 'Dashboard peternakan membutuhkan komoditas bertipe hewan dari data mobile.');
        }

        $commodity = $this->livestockCommodities()->firstWhere('id', $commodityId);
        if (! $commodity) {
            return $this->emptyReadiness('not_livestock', 'Komoditas bukan peternakan', 'Komoditas ini tidak ditampilkan pada dashboard peternakan.');
        }

        $readiness = $this->readinessForJenis((string) $commodity->jenisBudidayaId);
        $readiness['commodity_id'] = $commodity->id;
        $readiness['commodity_name'] = $commodity->nama;
        $readiness['jenis_budidaya_id'] = $commodity->jenisBudidayaId;
        $readiness['jenis_budidaya_name'] = $commodity->jenis_budidaya_nama;

        return $readiness;
    }

    public function readinessForJenis(string $jenisBudidayaId): array
    {
        if (! $this->hasSchema()) {
            return $this->emptyReadiness('schema_missing', 'Data Master ternak belum dimigrasikan', 'Jalankan migration terbaru agar konfigurasi ternak bisa disimpan.');
        }

        $config = $this->configForJenis($jenisBudidayaId);
        if (! $config) {
            return $this->emptyReadiness('missing_config', 'Belum terhubung Data Master', 'Konfigurasikan parameter lingkungan dan fungsi produktivitas untuk jenis ternak ini.');
        }

        $environmentCount = DB::table('livestock_environment_parameters')
            ->where('config_id', $config->id)
            ->where('is_active', true)
            ->count();

        $functionCount = DB::table('livestock_productivity_function_configs')
            ->where('config_id', $config->id)
            ->where('is_active', true)
            ->count();

        $hints = [];
        if ($environmentCount === 0) {
            $hints[] = 'Parameter lingkungan belum dipilih.';
        }
        if ($functionCount === 0) {
            $hints[] = 'Fungsi produktivitas belum dipilih.';
        }

        $isReady = $config->status === 'configured' && $environmentCount > 0 && $functionCount > 0;

        return [
            'status' => $isReady ? 'ready' : 'incomplete',
            'configured' => $isReady,
            'title' => $isReady ? 'Terhubung Data Master' : 'Data Master belum lengkap',
            'message' => $isReady
                ? 'Jenis ternak ini sudah punya parameter lingkungan dan fungsi produktivitas.'
                : 'Lengkapi Data Master sebelum menghubungkan device IoT dan konfigurasi fuzzy.',
            'environment_count' => $environmentCount,
            'function_count' => $functionCount,
            'config_id' => $config->id,
            'configured_at' => $config->configured_at,
            'hints' => $hints,
        ];
    }

    public function configuredIotParametersForCommodity(?string $commodityId = null, bool $fallbackToAll = true): Collection
    {
        if (! Schema::hasTable('iot_parameter')) {
            return collect();
        }

        $parameterIds = collect();

        if (! $this->hasSchema()) {
            return DB::table('iot_parameter')->orderBy('parameterCode')->get(['id', 'parameterCode', 'parameterName', 'unit']);
        }

        if ($this->hasSchema()) {
            $query = DB::table('livestock_environment_parameters')
                ->join('livestock_master_configs', 'livestock_master_configs.id', '=', 'livestock_environment_parameters.config_id')
                ->where('livestock_environment_parameters.is_active', true)
                ->whereNotNull('livestock_environment_parameters.parameter_id');

            if ($commodityId) {
                $commodity = $this->livestockCommodities()->firstWhere('id', $commodityId);
                if (! $commodity) {
                    return collect();
                }

                $query->where('livestock_master_configs.jenis_budidaya_id', $commodity->jenisBudidayaId);
            }

            $parameterIds = $query->pluck('livestock_environment_parameters.parameter_id')->filter()->unique()->values();
        }

        if ($parameterIds->isNotEmpty()) {
            return DB::table('iot_parameter')
                ->whereIn('id', $parameterIds->all())
                ->orderBy('parameterCode')
                ->get(['id', 'parameterCode', 'parameterName', 'unit']);
        }

        return $fallbackToAll
            ? DB::table('iot_parameter')->orderBy('parameterCode')->get(['id', 'parameterCode', 'parameterName', 'unit'])
            : collect();
    }

    public function availableProductivityFunctionMap(?string $commodityId = null): array
    {
        $this->ensureDefaultFunctionCatalog();

        if (! $this->hasSchema()) {
            return $this->defaultFunctionMap();
        }

        if ($commodityId) {
            $commodity = $this->livestockCommodities()->firstWhere('id', $commodityId);
            $config = $commodity ? $this->configForJenis((string) $commodity->jenisBudidayaId) : null;

            if ($config) {
                $rows = DB::table('livestock_productivity_function_configs')
                    ->join('livestock_productivity_functions', 'livestock_productivity_functions.id', '=', 'livestock_productivity_function_configs.function_id')
                    ->where('livestock_productivity_function_configs.config_id', $config->id)
                    ->where('livestock_productivity_function_configs.is_active', true)
                    ->where('livestock_productivity_functions.is_active', true)
                    ->orderBy('livestock_productivity_function_configs.sort_order')
                    ->orderBy('livestock_productivity_functions.name')
                    ->pluck('livestock_productivity_functions.name', 'livestock_productivity_functions.service_class')
                    ->toArray();

                return $rows;
            }

            return [];
        }

        $rows = DB::table('livestock_productivity_functions')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'service_class')
            ->toArray();

        return $rows ?: $this->defaultFunctionMap();
    }

    public function productivityCatalog(): Collection
    {
        $this->ensureDefaultFunctionCatalog();

        if (! Schema::hasTable('livestock_productivity_functions')) {
            return collect($this->defaultProductivityFunctions())
                ->map(function (array $meta, string $code) {
                    return (object) array_merge(['id' => $code, 'code' => $code], $meta);
                })
                ->values();
        }

        return DB::table('livestock_productivity_functions')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
    }

    public function saveConfiguration(array $data): string
    {
        if (! $this->hasSchema()) {
            throw new \RuntimeException('Tabel Data Master ternak belum tersedia. Jalankan migration terlebih dahulu.');
        }

        $this->ensureDefaultFunctionCatalog();

        return DB::transaction(function () use ($data) {
            $jenisBudidayaId = (string) $data['jenis_budidaya_id'];
            $commodityId = $data['commodity_id'] ?? $this->defaultCommodityIdForJenis($jenisBudidayaId);
            $environmentRows = collect($data['environment_parameters'] ?? [])
                ->map(fn ($row) => $this->normalizeEnvironmentRow((array) $row))
                ->filter(fn ($row) => $row['parameter_code'] !== '' && $row['parameter_name'] !== '')
                ->values();
            $functionIds = collect($data['productivity_function_ids'] ?? [])
                ->filter()
                ->unique()
                ->values();

            $config = $this->configForJenis($jenisBudidayaId);
            $configId = $config?->id ?: (string) Str::uuid();
            $now = now();

            DB::table('livestock_master_configs')->updateOrInsert(
                ['jenis_budidaya_id' => $jenisBudidayaId],
                [
                    'id' => $configId,
                    'commodity_id' => $commodityId,
                    'status' => $environmentRows->isNotEmpty() && $functionIds->isNotEmpty() ? 'configured' : 'draft',
                    'notes' => $data['notes'] ?? null,
                    'configured_by' => $data['configured_by'] ?? null,
                    'configured_at' => $environmentRows->isNotEmpty() || $functionIds->isNotEmpty() ? $now : null,
                    'createdAt' => $config?->createdAt ?? $now,
                    'updatedAt' => $now,
                ]
            );

            $activeCodes = [];
            foreach ($environmentRows as $index => $row) {
                $parameterId = $this->ensureIotParameter($row);
                $activeCodes[] = $row['parameter_code'];
                $environmentId = DB::table('livestock_environment_parameters')
                    ->where('config_id', $configId)
                    ->where('parameter_code', $row['parameter_code'])
                    ->value('id') ?: (string) Str::uuid();

                DB::table('livestock_environment_parameters')->updateOrInsert(
                    ['config_id' => $configId, 'parameter_code' => $row['parameter_code']],
                    [
                        'id' => $environmentId,
                        'parameter_id' => $parameterId,
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
                        'createdAt' => $now,
                        'updatedAt' => $now,
                    ]
                );

                $this->syncCommodityParameter($commodityId, $parameterId, $row);
            }

            $staleEnvironmentQuery = DB::table('livestock_environment_parameters')->where('config_id', $configId);
            if (! empty($activeCodes)) {
                $staleEnvironmentQuery->whereNotIn('parameter_code', $activeCodes);
            }
            $staleEnvironmentQuery->update(['is_active' => false, 'updatedAt' => $now]);

            DB::table('livestock_productivity_function_configs')
                ->where('config_id', $configId)
                ->update(['is_active' => false, 'updatedAt' => $now]);

            $validFunctions = DB::table('livestock_productivity_functions')
                ->whereIn('id', $functionIds->all())
                ->where('is_active', true)
                ->get(['id']);

            foreach ($validFunctions as $index => $function) {
                $functionConfigId = DB::table('livestock_productivity_function_configs')
                    ->where('config_id', $configId)
                    ->where('function_id', $function->id)
                    ->value('id') ?: (string) Str::uuid();

                DB::table('livestock_productivity_function_configs')->updateOrInsert(
                    ['config_id' => $configId, 'function_id' => $function->id],
                    [
                        'id' => $functionConfigId,
                        'required_for_fuzzy' => true,
                        'aggregation_scope' => 'today',
                        'sort_order' => $index,
                        'is_active' => true,
                        'createdAt' => $now,
                        'updatedAt' => $now,
                    ]
                );
            }

            return $configId;
        });
    }

    public function defaultEnvironmentRows(): array
    {
        return [
            [
                'parameter_code' => 'TEMP',
                'parameter_name' => 'Suhu',
                'unit' => 'C',
                'min_value' => 20,
                'max_value' => 28,
                'fallback_value' => 26,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => true,
            ],
            [
                'parameter_code' => 'HUMID',
                'parameter_name' => 'Kelembaban',
                'unit' => '%',
                'min_value' => 50,
                'max_value' => 70,
                'fallback_value' => 65,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => true,
            ],
            [
                'parameter_code' => 'AMMON',
                'parameter_name' => 'Amonia',
                'unit' => 'ppm',
                'min_value' => 0,
                'max_value' => 15,
                'fallback_value' => 5,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => true,
            ],
            [
                'parameter_code' => 'LIGHT',
                'parameter_name' => 'Cahaya',
                'unit' => 'lx',
                'min_value' => 15,
                'max_value' => 50,
                'fallback_value' => 250,
                'stale_minutes' => 30,
                'required_for_iot' => true,
                'required_for_fuzzy' => false,
            ],
        ];
    }

    private function environmentRowsForConfig(string $configId): Collection
    {
        if (! $this->hasSchema()) {
            return collect();
        }

        $rows = DB::table('livestock_environment_parameters')
            ->where('config_id', $configId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('parameter_name')
            ->get();

        return $rows->isNotEmpty()
            ? $rows
            : collect($this->defaultEnvironmentRows())->map(fn ($row) => (object) $row);
    }

    private function selectedFunctionIdsForConfig(string $configId): array
    {
        if (! $this->hasSchema()) {
            return [];
        }

        return DB::table('livestock_productivity_function_configs')
            ->where('config_id', $configId)
            ->where('is_active', true)
            ->pluck('function_id')
            ->values()
            ->all();
    }

    private function defaultCommodityIdForJenis(string $jenisBudidayaId): ?string
    {
        if (! Schema::hasTable('komoditas')) {
            return null;
        }

        return DB::table('komoditas')
            ->where('jenisBudidayaId', $jenisBudidayaId)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->value('id');
    }

    private function ensureIotParameter(array $row): string
    {
        $existing = DB::table('iot_parameter')
            ->where('parameterCode', $row['parameter_code'])
            ->first();

        if ($existing) {
            DB::table('iot_parameter')->where('id', $existing->id)->update([
                'parameterName' => $row['parameter_name'],
                'unit' => $row['unit'],
                'description' => 'Dikelola dari Data Master ternak.',
            ]);

            return (string) $existing->id;
        }

        $id = (string) Str::uuid();
        DB::table('iot_parameter')->insert([
            'id' => $id,
            'parameterCode' => $row['parameter_code'],
            'parameterName' => $row['parameter_name'],
            'unit' => $row['unit'],
            'description' => 'Dikelola dari Data Master ternak.',
            'createdAt' => now(),
        ]);

        return $id;
    }

    private function syncCommodityParameter(?string $commodityId, string $parameterId, array $row): void
    {
        if (! $commodityId || ! Schema::hasTable('commodity_parameter')) {
            return;
        }

        $min = $row['min_value'];
        $max = $row['max_value'];
        if ($min === null && $max === null) {
            return;
        }

        $id = DB::table('commodity_parameter')
            ->where('commodityId', $commodityId)
            ->where('parameterId', $parameterId)
            ->value('id') ?: (string) Str::uuid();

        DB::table('commodity_parameter')->updateOrInsert(
            ['commodityId' => $commodityId, 'parameterId' => $parameterId],
            [
                'id' => $id,
                'minValue' => $min,
                'maxValue' => $max,
                'createdAt' => now(),
            ]
        );
    }

    private function normalizeEnvironmentRow(array $row): array
    {
        return [
            'parameter_code' => $this->normalizeParameterCode($row['parameter_code'] ?? ''),
            'parameter_name' => trim((string) ($row['parameter_name'] ?? '')),
            'unit' => $this->nullableString($row['unit'] ?? null),
            'min_value' => $this->nullableFloat($row['min_value'] ?? null),
            'max_value' => $this->nullableFloat($row['max_value'] ?? null),
            'fallback_value' => $this->nullableFloat($row['fallback_value'] ?? null),
            'stale_minutes' => max(1, min(10080, (int) ($row['stale_minutes'] ?? 30))),
            'required_for_iot' => (bool) ($row['required_for_iot'] ?? true),
            'required_for_fuzzy' => (bool) ($row['required_for_fuzzy'] ?? false),
        ];
    }

    private function normalizeParameterCode(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9_]+/', '_', $value) ?: '';
        $value = preg_replace('/_+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function defaultFunctionMap(): array
    {
        return collect($this->defaultProductivityFunctions())
            ->pluck('name', 'service_class')
            ->toArray();
    }

    private function emptyReadiness(string $status, string $title, string $message): array
    {
        return [
            'status' => $status,
            'configured' => false,
            'title' => $title,
            'message' => $message,
            'environment_count' => 0,
            'function_count' => 0,
            'config_id' => null,
            'configured_at' => null,
            'hints' => [],
        ];
    }
}
