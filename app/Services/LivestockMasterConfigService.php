<?php

namespace App\Services;

use App\Models\IotParameter;
use App\Services\Fuzzy\LayerChickenFuzzyTemplateDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LivestockMasterConfigService
{
    private ?bool $environmentIconColumnExists = null;

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
            'hhep' => [
                'name' => 'HHEP - Hen Housed Egg Production',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateHhep',
                'output_unit' => '%',
                'description' => 'Persentase produksi harian terhadap estimasi populasi awal kandang.',
                'required_inputs' => ['panen.jumlah', 'unitBudidaya.jumlah', 'kematian.id'],
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
            'egg_mass' => [
                'name' => 'Egg Mass / Berat panen harian',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateEggMass',
                'output_unit' => 'kg',
                'description' => 'Total berat telur yang dipanen harian dari laporan mobile.',
                'required_inputs' => ['panen.berat'],
            ],
            'avg_egg_weight' => [
                'name' => 'Berat rata-rata telur',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateAverageEggWeight',
                'output_unit' => 'g/butir',
                'description' => 'Rata-rata berat telur harian dari total berat panen dibagi jumlah butir.',
                'required_inputs' => ['panen.berat', 'panen.jumlah'],
            ],
            'flock_age' => [
                'name' => 'Umur biologis flock',
                'service_class' => 'App\\Services\\Fuzzy\\CalculateFlockAge',
                'output_unit' => 'minggu',
                'description' => 'Rata-rata umur biologis kandang aktif dari input mobile atau tanggal kandang dibuat.',
                'required_inputs' => ['unitBudidaya.umurMinggu', 'unitBudidaya.createdAt'],
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

    public function livestockTypeOptions(): Collection
    {
        $types = $this->livestockTypes();
        if ($types->isEmpty()) {
            return collect();
        }

        $commoditiesByJenis = $this->livestockCommodities()->groupBy('jenisBudidayaId');

        return $types->map(function ($type) use ($commoditiesByJenis) {
            $commodities = $commoditiesByJenis->get($type->id, collect())->values();
            $primaryCommodity = $commodities->first(fn ($commodity) => str_contains(strtolower((string) $commodity->nama), 'layer'))
                ?? $commodities->first();

            return (object) [
                'id' => $type->id,
                'nama' => $type->nama,
                'tipe' => $type->tipe,
                'primary_commodity_id' => $primaryCommodity?->id,
                'commodity_count' => $commodities->count(),
                'commodities' => $commodities,
            ];
        })->values();
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

    public function livestockJenisBudidayaIds(): array
    {
        return $this->livestockTypes()
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

    public function resolveLivestockJenisBudidayaId(?string $requestedId): ?string
    {
        $types = $this->livestockTypes();

        if ($requestedId && $types->contains('id', $requestedId)) {
            return $requestedId;
        }

        $layer = $types->first(fn ($type) => str_contains(strtolower((string) $type->nama), 'layer')
            || str_contains(strtolower((string) $type->nama), 'petelur'));

        return $layer?->id ?? $types->first()?->id;
    }

    public function resolveLivestockCommodityIdForJenis(?string $jenisBudidayaId, ?string $requestedId = null): ?string
    {
        if (! $jenisBudidayaId) {
            return $this->resolveLivestockCommodityId($requestedId);
        }

        $commodities = $this->livestockCommodities()
            ->where('jenisBudidayaId', $jenisBudidayaId)
            ->values();

        if ($requestedId && $commodities->contains('id', $requestedId)) {
            return $requestedId;
        }

        $layer = $commodities->first(fn ($commodity) => str_contains(strtolower((string) $commodity->nama), 'layer')
            || str_contains(strtolower((string) $commodity->nama), 'petelur'));

        return $layer?->id ?? $commodities->first()?->id;
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
            'selectedOperationalFunctionIds' => $selectedConfig
                ? $this->selectedOperationalFunctionIdsForConfig((string) $selectedConfig->id)
                : [],
            'afkirConfig' => $this->afkirConfigForJenis($selectedJenisBudidayaId),
            'productivityFunctions' => $this->productivityCatalog($selectedConfig?->id ? (string) $selectedConfig->id : null),
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

    public function afkirConfigForCommodity(?string $commodityId): array
    {
        $jenisBudidayaId = null;
        if ($commodityId) {
            $jenisBudidayaId = $this->livestockCommodities()
                ->firstWhere('id', $commodityId)
                ?->jenisBudidayaId;
        }

        return $this->afkirConfigForJenis($jenisBudidayaId);
    }

    public function afkirConfigForJenis(?string $jenisBudidayaId): array
    {
        $type = $jenisBudidayaId
            ? $this->livestockTypes()->firstWhere('id', $jenisBudidayaId)
            : null;
        $defaults = $this->defaultAfkirCycleSettings($type?->nama);

        if (! $jenisBudidayaId || ! $this->hasSchema()) {
            return $defaults;
        }

        $config = $this->configForJenis($jenisBudidayaId);
        if (! $config || ! Schema::hasColumn('livestock_master_configs', 'afkir_target_weeks')) {
            return $defaults;
        }

        $targetWeeks = $this->nullableInteger($config->afkir_target_weeks ?? null);
        $warningWeeks = Schema::hasColumn('livestock_master_configs', 'afkir_warning_weeks')
            ? $this->nullableInteger($config->afkir_warning_weeks ?? null)
            : null;
        $label = Schema::hasColumn('livestock_master_configs', 'afkir_label')
            ? trim((string) ($config->afkir_label ?? ''))
            : '';

        $productionStart = Schema::hasColumn('livestock_master_configs', 'production_start_weeks')
            ? $this->nullableInteger($config->production_start_weeks ?? null)
            : null;
        $peakStart = Schema::hasColumn('livestock_master_configs', 'peak_start_weeks')
            ? $this->nullableInteger($config->peak_start_weeks ?? null)
            : null;
        $peakEnd = Schema::hasColumn('livestock_master_configs', 'peak_end_weeks')
            ? $this->nullableInteger($config->peak_end_weeks ?? null)
            : null;
        $declineStart = Schema::hasColumn('livestock_master_configs', 'production_decline_weeks')
            ? $this->nullableInteger($config->production_decline_weeks ?? null)
            : null;

        return [
            'label' => $label !== '' ? $label : $defaults['label'],
            'target_weeks' => $targetWeeks ?? $defaults['target_weeks'],
            'warning_weeks' => $warningWeeks ?? $defaults['warning_weeks'],
            'is_configured' => $targetWeeks !== null,
            'production_start_weeks' => $productionStart ?? $defaults['production_start_weeks'],
            'peak_start_weeks' => $peakStart ?? $defaults['peak_start_weeks'],
            'peak_end_weeks' => $peakEnd ?? $defaults['peak_end_weeks'],
            'production_decline_weeks' => $declineStart ?? $defaults['production_decline_weeks'],
        ];
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
            return $this->emptyReadiness('missing_config', 'Belum terhubung Data Master', 'Konfigurasikan parameter lingkungan untuk jenis ternak ini.');
        }

        $environmentCount = DB::table('livestock_environment_parameters')
            ->where('config_id', $config->id)
            ->where('is_active', true)
            ->where('required_for_fuzzy', true)
            ->count();

        $functionCount = DB::table('livestock_productivity_function_configs')
            ->where('config_id', $config->id)
            ->where('is_active', true)
            ->where('required_for_fuzzy', true)
            ->count();

        $hints = [];
        if ($environmentCount === 0) {
            $hints[] = 'Belum ada parameter lingkungan yang dicentang untuk Fuzzy.';
        }
        if ($functionCount === 0) {
            $hints[] = 'Belum ada parameter produktivitas yang dicentang untuk Fuzzy.';
        }

        $isReady = $config->status === 'configured' && $environmentCount > 0 && $functionCount > 0;

        return [
            'status' => $isReady ? 'ready' : 'incomplete',
            'configured' => $isReady,
            'title' => $isReady ? 'Terhubung Data Master' : 'Data Master belum lengkap',
            'message' => $isReady
                ? 'Jenis ternak ini sudah punya parameter lingkungan dan produktivitas dari Data Master.'
                : 'Lengkapi parameter lingkungan dan produktivitas Data Master sebelum menghubungkan device IoT dan konfigurasi fuzzy.',
            'environment_count' => $environmentCount,
            'function_count' => $functionCount,
            'config_id' => $config->id,
            'configured_at' => $config->configured_at,
            'hints' => $hints,
        ];
    }

    public function configuredIotParametersForCommodity(?string $commodityId = null, bool $fallbackToAll = true): Collection
    {
        $jenisBudidayaId = null;
        if ($commodityId) {
            $jenisBudidayaId = $this->livestockCommodities()
                ->firstWhere('id', $commodityId)
                ?->jenisBudidayaId;
        }

        return $this->configuredIotParametersForJenis($jenisBudidayaId, $fallbackToAll);
    }

    public function configuredIotParametersForJenis(?string $jenisBudidayaId = null, bool $fallbackToAll = true): Collection
    {
        if (! Schema::hasTable('iot_parameter')) {
            return collect();
        }

        $columns = $this->iotParameterColumns();
        $parameterIds = collect();

        if (! $this->hasSchema()) {
            return $fallbackToAll
                ? IotParameter::query()->orderBy('parameterCode')->get($columns)
                : collect();
        }

        $query = DB::table('livestock_environment_parameters')
            ->join('livestock_master_configs', 'livestock_master_configs.id', '=', 'livestock_environment_parameters.config_id')
            ->where('livestock_environment_parameters.is_active', true)
            ->where('livestock_environment_parameters.required_for_iot', true)
            ->whereNotNull('livestock_environment_parameters.parameter_id');

        if ($jenisBudidayaId) {
            $query->where('livestock_master_configs.jenis_budidaya_id', $jenisBudidayaId);
        }

        $parameterIds = $query->pluck('livestock_environment_parameters.parameter_id')->filter()->unique()->values();

        if ($parameterIds->isNotEmpty()) {
            return IotParameter::query()
                ->whereIn('id', $parameterIds->all())
                ->orderBy('parameterCode')
                ->get($columns);
        }

        return $fallbackToAll
            ? IotParameter::query()->orderBy('parameterCode')->get($columns)
            : collect();
    }

    private function iotParameterColumns(): array
    {
        $columns = ['id', 'parameterCode', 'parameterName', 'unit'];

        if (Schema::hasColumn('iot_parameter', 'description')) {
            $columns[] = 'description';
        }

        return $columns;
    }

    public function configuredEnvironmentParametersForCommodity(?string $commodityId = null, bool $fallbackToDefault = true): Collection
    {
        $jenisBudidayaId = null;
        if ($commodityId) {
            $jenisBudidayaId = $this->livestockCommodities()
                ->firstWhere('id', $commodityId)
                ?->jenisBudidayaId;
        }

        return $this->configuredEnvironmentParametersForJenis($jenisBudidayaId, $fallbackToDefault);
    }

    public function configuredEnvironmentParametersForJenis(?string $jenisBudidayaId = null, bool $fallbackToDefault = true): Collection
    {
        return $this->configuredEnvironmentParametersForJenisWithFuzzy($jenisBudidayaId, $fallbackToDefault, false);
    }

    public function fuzzyEnvironmentParametersForJenis(?string $jenisBudidayaId = null, bool $fallbackToDefault = false): Collection
    {
        return $this->configuredEnvironmentParametersForJenisWithFuzzy($jenisBudidayaId, $fallbackToDefault, true);
    }

    private function configuredEnvironmentParametersForJenisWithFuzzy(?string $jenisBudidayaId, bool $fallbackToDefault, bool $onlyFuzzy): Collection
    {
        if (! $this->hasSchema()) {
            $rows = $fallbackToDefault
                ? collect($this->defaultEnvironmentRows())->map(fn ($row) => (object) $row)
                : collect();

            return $onlyFuzzy ? $rows->where('required_for_fuzzy', true)->values() : $rows;
        }

        $config = $jenisBudidayaId ? $this->configForJenis($jenisBudidayaId) : null;

        if (! $config) {
            $rows = $fallbackToDefault
                ? collect($this->defaultEnvironmentRows())->map(fn ($row) => (object) $row)
                : collect();

            return $onlyFuzzy ? $rows->where('required_for_fuzzy', true)->values() : $rows;
        }

        $rows = $this->environmentRowsForConfig((string) $config->id);
        if ($onlyFuzzy) {
            $rows = $rows->where('required_for_fuzzy', true)->values();
        } else {
            $rows = $rows->where('required_for_iot', true)->values();
        }

        return $rows->isNotEmpty() || ! $fallbackToDefault
            ? $rows
            : collect($this->defaultEnvironmentRows())
                ->map(fn ($row) => (object) $row)
                ->when($onlyFuzzy, fn ($defaults) => $defaults->where('required_for_fuzzy', true)->values());
    }

    public function selectedProductivityFunctionCodesForCommodity(?string $commodityId): array
    {
        $jenisBudidayaId = null;
        if ($commodityId) {
            $jenisBudidayaId = $this->livestockCommodities()
                ->firstWhere('id', $commodityId)
                ?->jenisBudidayaId;
        }

        return $this->selectedProductivityFunctionCodesForJenis($jenisBudidayaId);
    }

    public function selectedProductivityFunctionCodesForJenis(?string $jenisBudidayaId): array
    {
        $this->ensureDefaultFunctionCatalog();

        if (! $jenisBudidayaId || ! $this->hasSchema()) {
            return [];
        }

        $config = $this->configForJenis($jenisBudidayaId);
        if (! $config) {
            return [];
        }

        return DB::table('livestock_productivity_function_configs')
            ->join('livestock_productivity_functions', 'livestock_productivity_functions.id', '=', 'livestock_productivity_function_configs.function_id')
            ->where('livestock_productivity_function_configs.config_id', $config->id)
            ->where('livestock_productivity_function_configs.is_active', true)
            ->where('livestock_productivity_functions.is_active', true)
            ->orderBy('livestock_productivity_function_configs.sort_order')
            ->orderBy('livestock_productivity_functions.name')
            ->pluck('livestock_productivity_functions.code')
            ->filter()
            ->values()
            ->all();
    }

    public function configuredProductivityFunctionsForJenis(?string $jenisBudidayaId): Collection
    {
        return $this->configuredProductivityFunctionsForJenisWithFuzzy($jenisBudidayaId, false);
    }

    public function fuzzyProductivityFunctionsForJenis(?string $jenisBudidayaId): Collection
    {
        return $this->configuredProductivityFunctionsForJenisWithFuzzy($jenisBudidayaId, true);
    }

    private function configuredProductivityFunctionsForJenisWithFuzzy(?string $jenisBudidayaId, bool $onlyFuzzy): Collection
    {
        $this->ensureDefaultFunctionCatalog();

        if (! $jenisBudidayaId || ! $this->hasSchema()) {
            return collect();
        }

        $config = $this->configForJenis($jenisBudidayaId);
        if (! $config) {
            return collect();
        }

        $columns = [
            'livestock_productivity_functions.id',
            'livestock_productivity_functions.code',
            'livestock_productivity_functions.name',
            'livestock_productivity_functions.service_class',
            'livestock_productivity_functions.output_unit',
            'livestock_productivity_functions.description',
            'livestock_productivity_functions.required_inputs',
            'livestock_productivity_function_configs.aggregation_scope',
            'livestock_productivity_function_configs.required_for_fuzzy',
            'livestock_productivity_function_configs.sort_order',
        ];

        if (Schema::hasColumn('livestock_productivity_function_configs', 'target_min_value')) {
            $columns[] = 'livestock_productivity_function_configs.target_min_value';
        }

        if (Schema::hasColumn('livestock_productivity_function_configs', 'target_max_value')) {
            $columns[] = 'livestock_productivity_function_configs.target_max_value';
        }

        return DB::table('livestock_productivity_function_configs')
            ->join('livestock_productivity_functions', 'livestock_productivity_functions.id', '=', 'livestock_productivity_function_configs.function_id')
            ->where('livestock_productivity_function_configs.config_id', $config->id)
            ->where('livestock_productivity_function_configs.is_active', true)
            ->when($onlyFuzzy, fn ($query) => $query->where('livestock_productivity_function_configs.required_for_fuzzy', true))
            ->where('livestock_productivity_functions.is_active', true)
            ->orderBy('livestock_productivity_function_configs.sort_order')
            ->orderBy('livestock_productivity_functions.name')
            ->get($columns);
    }

    public function configuredProductivityFunctionCodesForCommodity(?string $commodityId): array
    {
        return $this->configuredSpkInputParametersForCommodity($commodityId, 'kesehatan', true)
            ->pluck('code')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function configuredSpkInputKeysForCommodity(?string $commodityId, ?string $group = null): array
    {
        return $this->configuredSpkInputParametersForCommodity($commodityId, $group, true)
            ->flatMap(fn (array $row) => array_filter([
                $row['variable_name'] ?? null,
                $row['code'] ?? null,
                $row['source_code'] ?? null,
                $row['name'] ?? null,
            ]))
            ->map(fn ($value) => strtolower((string) $value))
            ->unique()
            ->values()
            ->all();
    }

    public function configuredSpkInputParametersForCommodity(?string $commodityId, ?string $group = null, bool $requireSource = true): Collection
    {
        if (! Schema::hasTable('spk_fuzzy_profiles') || ! Schema::hasTable('spk_fuzzy_variables')) {
            return collect();
        }

        if ($requireSource && ! Schema::hasTable('spk_fuzzy_input_sources')) {
            return collect();
        }

        $profile = $this->activeFuzzyProfileForCommodity($commodityId);
        if (! $profile) {
            return collect();
        }

        $query = DB::table('spk_fuzzy_variables')
            ->where('spk_fuzzy_variables.profile_id', $profile->id)
            ->where('spk_fuzzy_variables.type', 'input')
            ->where('spk_fuzzy_variables.group', '!=', 'kausalitas')
            ->when($group, fn ($q) => $q->where('spk_fuzzy_variables.group', $group));

        if (Schema::hasTable('spk_fuzzy_input_sources')) {
            $query->leftJoin('spk_fuzzy_input_sources', 'spk_fuzzy_input_sources.variable_id', '=', 'spk_fuzzy_variables.id');
            if ($requireSource) {
                $query->whereNotNull('spk_fuzzy_input_sources.id');
            }
        }

        $selects = [
            'spk_fuzzy_variables.id',
            'spk_fuzzy_variables.name as variable_name',
            'spk_fuzzy_variables.group',
            'spk_fuzzy_variables.unit',
            'spk_fuzzy_variables.description',
        ];

        if (Schema::hasTable('spk_fuzzy_input_sources')) {
            $selects = array_merge($selects, [
                'spk_fuzzy_input_sources.id as source_id',
                'spk_fuzzy_input_sources.source_type',
                'spk_fuzzy_input_sources.source_name',
                'spk_fuzzy_input_sources.field_name',
                'spk_fuzzy_input_sources.function_name',
                'spk_fuzzy_input_sources.extra_config',
            ]);
        } else {
            $selects = array_merge($selects, [
                DB::raw('NULL as source_id'),
                DB::raw('NULL as source_type'),
                DB::raw('NULL as source_name'),
                DB::raw('NULL as field_name'),
                DB::raw('NULL as function_name'),
                DB::raw('NULL as extra_config'),
            ]);
        }

        return $query
            ->orderByRaw("CASE spk_fuzzy_variables.`group` WHEN 'lingkungan' THEN 1 WHEN 'kesehatan' THEN 2 ELSE 3 END")
            ->orderBy('spk_fuzzy_variables.name')
            ->get($selects)
            ->map(fn ($row) => $this->formatSpkInputParameter($row))
            ->values();
    }

    public function spkConfigurationStatusForCommodity(?string $commodityId): array
    {
        $profile = $this->activeFuzzyProfileForCommodity($commodityId);

        if (! $profile) {
            return [
                'configured' => false,
                'title' => 'Konfigurasi SPK belum dibuat',
                'message' => 'Buat profil Fuzzy Mamdani dan atur variabel input sebelum SPK dijalankan.',
                'profile' => null,
                'environment_parameters' => [],
                'productivity_parameters' => [],
                'environment_count' => 0,
                'productivity_count' => 0,
                'missing_source_count' => 0,
                'rule_counts' => ['lingkungan' => 0, 'kesehatan' => 0, 'kausalitas' => 0],
                'hints' => ['Belum ada profil fuzzy aktif untuk komoditas ini.'],
            ];
        }

        $allInputs = $this->configuredSpkInputParametersForCommodity($commodityId, null, false);
        $configuredInputs = $allInputs->whereNotNull('source_id')->values();
        $environmentInputs = $configuredInputs->where('group', 'lingkungan')->values();
        $productivityInputs = $configuredInputs->where('group', 'kesehatan')->values();
        $missingSourceCount = $allInputs->whereNull('source_id')->count();
        $ruleCounts = $this->fuzzyRuleCountsForProfile((string) $profile->id);

        $environmentReady = $environmentInputs->isNotEmpty()
            && $allInputs->where('group', 'lingkungan')->whereNull('source_id')->isEmpty()
            && ($ruleCounts['lingkungan'] ?? 0) > 0;
        $productivityReady = $productivityInputs->isNotEmpty()
            && $allInputs->where('group', 'kesehatan')->whereNull('source_id')->isEmpty()
            && ($ruleCounts['kesehatan'] ?? 0) > 0;
        $causalityReady = ($ruleCounts['kausalitas'] ?? 0) > 0;
        $isReady = $environmentReady && $productivityReady && $causalityReady;

        $hints = [];
        if ($environmentInputs->isEmpty()) {
            $hints[] = 'Belum ada input lingkungan yang tersambung di Pengaturan Fuzzy.';
        }
        if ($productivityInputs->isEmpty()) {
            $hints[] = 'Belum ada input produktivitas yang tersambung di Pengaturan Fuzzy.';
        }
        if ($missingSourceCount > 0) {
            $hints[] = "{$missingSourceCount} variabel input belum memiliki sumber data.";
        }
        foreach (['lingkungan' => 'lingkungan', 'kesehatan' => 'produktivitas', 'kausalitas' => 'kausalitas'] as $key => $label) {
            if (($ruleCounts[$key] ?? 0) === 0) {
                $hints[] = "Rule {$label} belum dikonfigurasi.";
            }
        }

        return [
            'configured' => $isReady,
            'title' => $isReady ? 'SPK siap dijalankan' : 'Konfigurasi SPK belum lengkap',
            'message' => $isReady
                ? 'Parameter aktif SPK sudah mengikuti variabel dan sumber data pada profil fuzzy.'
                : 'Lengkapi variabel input, sumber data, dan rule pada Pengaturan Fuzzy sebelum menjalankan SPK.',
            'profile' => [
                'id' => $profile->id,
                'name' => $profile->name,
                'version' => $profile->version,
                'status' => $profile->status,
                'is_active' => (bool) $profile->is_active,
            ],
            'environment_parameters' => $environmentInputs->values()->all(),
            'productivity_parameters' => $productivityInputs->values()->all(),
            'environment_count' => $environmentInputs->count(),
            'productivity_count' => $productivityInputs->count(),
            'missing_source_count' => $missingSourceCount,
            'rule_counts' => $ruleCounts,
            'hints' => $hints,
        ];
    }

    public function summaryForCommodity(?string $commodityId): array
    {
        $readiness = $this->readinessForCommodity($commodityId);
        $configId = $readiness['config_id'] ?? null;

        $environmentParameters = collect();
        $productivityFunctions = collect();

        if ($configId && $this->hasSchema()) {
            $environmentColumns = [
                'parameter_code',
                'parameter_name',
                'unit',
                'min_value',
                'max_value',
                'fallback_value',
                'stale_minutes',
                'required_for_iot',
                'required_for_fuzzy',
            ];

            if ($this->environmentIconColumnExists()) {
                $environmentColumns[] = 'icon_key';
            }

            $environmentParameters = DB::table('livestock_environment_parameters')
                ->where('config_id', $configId)
                ->where('is_active', true)
                ->where('required_for_iot', true)
                ->orderBy('sort_order')
                ->orderBy('parameter_name')
                ->get($environmentColumns)
                ->map(fn ($row) => [
                    'code' => (string) $row->parameter_code,
                    'name' => (string) $row->parameter_name,
                    'unit' => (string) ($row->unit ?? ''),
                    'icon_key' => (string) ($row->icon_key ?? 'sensor'),
                    'min' => $row->min_value,
                    'max' => $row->max_value,
                    'fallback' => $row->fallback_value,
                    'stale_minutes' => $row->stale_minutes,
                    'required_for_iot' => (bool) $row->required_for_iot,
                    'required_for_fuzzy' => (bool) $row->required_for_fuzzy,
                ]);

            $functionColumns = [
                'livestock_productivity_functions.code',
                'livestock_productivity_functions.name',
                'livestock_productivity_functions.output_unit',
                'livestock_productivity_functions.description',
                'livestock_productivity_functions.required_inputs',
            ];

            if (Schema::hasColumn('livestock_productivity_function_configs', 'target_min_value')) {
                $functionColumns[] = 'livestock_productivity_function_configs.target_min_value';
            }

            if (Schema::hasColumn('livestock_productivity_function_configs', 'target_max_value')) {
                $functionColumns[] = 'livestock_productivity_function_configs.target_max_value';
            }

            $productivityFunctions = DB::table('livestock_productivity_function_configs')
                ->join('livestock_productivity_functions', 'livestock_productivity_functions.id', '=', 'livestock_productivity_function_configs.function_id')
                ->where('livestock_productivity_function_configs.config_id', $configId)
                ->where('livestock_productivity_function_configs.is_active', true)
                ->where('livestock_productivity_functions.is_active', true)
                ->orderBy('livestock_productivity_function_configs.sort_order')
                ->orderBy('livestock_productivity_functions.name')
                ->get($functionColumns)
                ->map(fn ($row) => [
                    'code' => (string) $row->code,
                    'name' => (string) $row->name,
                    'unit' => (string) ($row->output_unit ?? ''),
                    'description' => (string) ($row->description ?? ''),
                    'required_inputs' => json_decode((string) $row->required_inputs, true) ?: [],
                    'target_min_value' => $row->target_min_value ?? null,
                    'target_max_value' => $row->target_max_value ?? null,
                ]);
        }

        $spkStatus = $this->spkConfigurationStatusForCommodity($commodityId);

        return array_merge($readiness, [
            'data_master_configured' => (bool) ($readiness['configured'] ?? false),
            'data_master_title' => $readiness['title'] ?? null,
            'data_master_message' => $readiness['message'] ?? null,
            'data_master_environment_count' => $environmentParameters->count(),
            'data_master_function_count' => $productivityFunctions->count(),
            'master_productivity_functions' => $productivityFunctions->values()->all(),
            'environment_parameters' => $environmentParameters->values()->all(),
            'afkir_config' => $this->afkirConfigForCommodity($commodityId),
            'productivity_functions' => $spkStatus['productivity_parameters'],
            'environment_count' => $environmentParameters->count(),
            'function_count' => $spkStatus['productivity_count'],
            'spk_configured' => $spkStatus['configured'],
            'spk_title' => $spkStatus['title'],
            'spk_message' => $spkStatus['message'],
            'spk_profile' => $spkStatus['profile'],
            'spk_environment_parameters' => $spkStatus['environment_parameters'],
            'spk_productivity_parameters' => $spkStatus['productivity_parameters'],
            'spk_environment_count' => $spkStatus['environment_count'],
            'spk_productivity_count' => $spkStatus['productivity_count'],
            'spk_missing_source_count' => $spkStatus['missing_source_count'],
            'spk_rule_counts' => $spkStatus['rule_counts'],
            'spk_hints' => $spkStatus['hints'],
        ]);
    }

    public function availableProductivityFunctionMap(?string $commodityId = null): array
    {
        $this->ensureDefaultFunctionCatalog();

        if (! $this->hasSchema()) {
            return [];
        }

        $rows = DB::table('livestock_productivity_functions')
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'service_class')
            ->toArray();

        return $rows ?: $this->defaultFunctionMap();
    }

    public function productivityCatalog(?string $configId = null): Collection
    {
        $this->ensureDefaultFunctionCatalog();

        if (! Schema::hasTable('livestock_productivity_functions')) {
            return collect($this->defaultProductivityFunctions())
                ->map(function (array $meta, string $code) {
                    return (object) array_merge(['id' => $code, 'code' => $code], $meta);
                })
                ->values();
        }

        $rows = DB::table('livestock_productivity_functions')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if (! $configId || ! Schema::hasTable('livestock_productivity_function_configs')) {
            return $rows;
        }

        $configColumns = ['function_id'];

        if (Schema::hasColumn('livestock_productivity_function_configs', 'target_min_value')) {
            $configColumns[] = 'target_min_value';
        }

        if (Schema::hasColumn('livestock_productivity_function_configs', 'target_max_value')) {
            $configColumns[] = 'target_max_value';
        }

        $configs = DB::table('livestock_productivity_function_configs')
            ->where('config_id', $configId)
            ->get($configColumns)
            ->keyBy('function_id');

        return $rows->map(function ($row) use ($configs) {
            $config = $configs->get($row->id);
            $row->target_min_value = $config->target_min_value ?? null;
            $row->target_max_value = $config->target_max_value ?? null;

            return $row;
        });
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
            $productivityRows = collect($data['productivity_functions'] ?? [])
                ->map(fn ($row) => $this->normalizeProductivityFunctionRow((array) $row))
                ->filter(fn ($row) => $row['function_id'] !== '')
                ->unique('function_id')
                ->values();

            if ($productivityRows->isEmpty() && ! empty($data['productivity_function_ids'] ?? [])) {
                $productivityRows = collect($data['productivity_function_ids'])
                    ->filter()
                    ->unique()
                    ->map(fn ($functionId) => [
                        'function_id' => (string) $functionId,
                        'is_active' => true,
                        'required_for_fuzzy' => true,
                        'aggregation_scope' => 'today',
                    ])
                    ->values();
            }

            $functionIds = $productivityRows->pluck('function_id')->filter()->unique()->values();

            $config = $this->configForJenis($jenisBudidayaId);
            $configId = $config?->id ?: (string) Str::uuid();
            $now = now();

            $configPayload = [
                'id' => $configId,
                'commodity_id' => $commodityId,
                'status' => $environmentRows->isNotEmpty() ? 'configured' : 'draft',
                'notes' => $data['notes'] ?? null,
                'configured_by' => $data['configured_by'] ?? null,
                'configured_at' => $environmentRows->isNotEmpty() || $productivityRows->where('is_active', true)->isNotEmpty() ? $now : null,
                'createdAt' => $config?->createdAt ?? $now,
                'updatedAt' => $now,
            ];

            if (Schema::hasColumn('livestock_master_configs', 'afkir_label')) {
                $label = trim((string) ($data['afkir_label'] ?? ''));
                $configPayload['afkir_label'] = $label !== '' ? $label : $this->defaultAfkirCycleSettings($this->livestockTypes()->firstWhere('id', $jenisBudidayaId)?->nama)['label'];
            }

            if (Schema::hasColumn('livestock_master_configs', 'afkir_target_weeks')) {
                $configPayload['afkir_target_weeks'] = $this->nullableInteger($data['afkir_target_weeks'] ?? null);
            }

            if (Schema::hasColumn('livestock_master_configs', 'afkir_warning_weeks')) {
                $configPayload['afkir_warning_weeks'] = $this->nullableInteger($data['afkir_warning_weeks'] ?? null) ?? 4;
            }

            foreach ([
                'production_start_weeks',
                'peak_start_weeks',
                'peak_end_weeks',
                'production_decline_weeks',
            ] as $cycleColumn) {
                if (Schema::hasColumn('livestock_master_configs', $cycleColumn)) {
                    $configPayload[$cycleColumn] = $this->nullableInteger($data[$cycleColumn] ?? null);
                }
            }

            DB::table('livestock_master_configs')->updateOrInsert(
                ['jenis_budidaya_id' => $jenisBudidayaId],
                $configPayload
            );

            $activeCodes = [];
            foreach ($environmentRows as $index => $row) {
                $parameterId = $this->ensureIotParameter($row);
                $activeCodes[] = $row['parameter_code'];
                $environmentId = DB::table('livestock_environment_parameters')
                    ->where('config_id', $configId)
                    ->where('parameter_code', $row['parameter_code'])
                    ->value('id') ?: (string) Str::uuid();

                $environmentPayload = [
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
                ];

                if ($this->environmentIconColumnExists()) {
                    $environmentPayload['icon_key'] = $row['icon_key'];
                }

                DB::table('livestock_environment_parameters')->updateOrInsert(
                    ['config_id' => $configId, 'parameter_code' => $row['parameter_code']],
                    $environmentPayload
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
                ->get(['id'])
                ->keyBy('id');

            foreach ($productivityRows as $index => $row) {
                if (! isset($validFunctions[$row['function_id']])) {
                    continue;
                }

                $functionConfigId = DB::table('livestock_productivity_function_configs')
                    ->where('config_id', $configId)
                    ->where('function_id', $row['function_id'])
                    ->value('id') ?: (string) Str::uuid();

                $functionConfigPayload = [
                    'id' => $functionConfigId,
                    'required_for_fuzzy' => $row['required_for_fuzzy'],
                    'aggregation_scope' => $row['aggregation_scope'],
                    'sort_order' => $index,
                    'is_active' => $row['is_active'],
                    'createdAt' => $now,
                    'updatedAt' => $now,
                ];

                if (Schema::hasColumn('livestock_productivity_function_configs', 'target_min_value')) {
                    $functionConfigPayload['target_min_value'] = $row['target_min_value'];
                }

                if (Schema::hasColumn('livestock_productivity_function_configs', 'target_max_value')) {
                    $functionConfigPayload['target_max_value'] = $row['target_max_value'];
                }

                DB::table('livestock_productivity_function_configs')->updateOrInsert(
                    ['config_id' => $configId, 'function_id' => $row['function_id']],
                    $functionConfigPayload
                );
            }

            return $configId;
        });
    }

    public function defaultEnvironmentRows(): array
    {
        return LayerChickenFuzzyTemplateDefinition::masterEnvironmentRows();
    }

    private function activeFuzzyProfileForCommodity(?string $commodityId): ?object
    {
        if (! Schema::hasTable('spk_fuzzy_profiles')) {
            return null;
        }

        $jenisBudidayaId = null;
        if ($commodityId) {
            $jenisBudidayaId = $this->livestockCommodities()
                ->firstWhere('id', $commodityId)
                ?->jenisBudidayaId;
        }

        if ($jenisBudidayaId && Schema::hasColumn('spk_fuzzy_profiles', 'jenis_budidaya_id')) {
            $profile = DB::table('spk_fuzzy_profiles')
                ->where('jenis_budidaya_id', $jenisBudidayaId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->orderByDesc('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }

            $profile = DB::table('spk_fuzzy_profiles')
                ->where('jenis_budidaya_id', $jenisBudidayaId)
                ->whereIn('status', ['review', 'draft', 'active'])
                ->orderByDesc('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        if ($commodityId) {
            $profile = DB::table('spk_fuzzy_profiles')
                ->where('commodity_id', $commodityId)
                ->where('is_active', true)
                ->where('status', 'active')
                ->orderByDesc('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }

            $profile = DB::table('spk_fuzzy_profiles')
                ->where('commodity_id', $commodityId)
                ->whereIn('status', ['review', 'draft', 'active'])
                ->orderByDesc('updatedAt')
                ->first();

            if ($profile) {
                return $profile;
            }
        }

        return DB::table('spk_fuzzy_profiles')
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderByDesc('updatedAt')
            ->first()
            ?: DB::table('spk_fuzzy_profiles')->orderByDesc('updatedAt')->first();
    }

    private function fuzzyRuleCountsForProfile(string $profileId): array
    {
        $empty = ['lingkungan' => 0, 'kesehatan' => 0, 'kausalitas' => 0];
        if (! Schema::hasTable('spk_fuzzy_rules')) {
            return $empty;
        }

        $counts = DB::table('spk_fuzzy_rules')
            ->where('profile_id', $profileId)
            ->select('group', DB::raw('COUNT(*) as total'))
            ->groupBy('group')
            ->pluck('total', 'group')
            ->toArray();

        foreach ($empty as $group => $default) {
            $empty[$group] = (int) ($counts[$group] ?? $default);
        }

        return $empty;
    }

    private function formatSpkInputParameter(object $row): array
    {
        $extraConfig = $row->extra_config ?? null;
        if (is_string($extraConfig)) {
            $extraConfig = json_decode($extraConfig, true) ?: [];
        }
        if (! is_array($extraConfig)) {
            $extraConfig = [];
        }

        $sourceCode = match ($row->source_type ?? null) {
            'iot' => $extraConfig['parameterCode'] ?? null,
            'report_metric' => $extraConfig['metricCode'] ?? null,
            'function' => $row->function_name ?? null,
            'database' => trim((string) (($row->source_name ?? '').'.'.($row->field_name ?? '')), '.'),
            default => null,
        };

        $code = $row->group === 'kesehatan'
            ? $this->productivityCodeForSpkInput($row, $extraConfig)
            : strtolower((string) ($sourceCode ?: $row->variable_name));

        return [
            'id' => (string) $row->id,
            'variable_name' => (string) $row->variable_name,
            'group' => (string) $row->group,
            'code' => $code,
            'name' => $this->humanizeSpkInputName($row),
            'unit' => (string) ($row->unit ?? ''),
            'description' => (string) ($row->description ?? ''),
            'source_id' => $row->source_id ?? null,
            'source_type' => $row->source_type ?? null,
            'source_code' => $sourceCode,
        ];
    }

    private function productivityCodeForSpkInput(object $row, array $extraConfig): string
    {
        if (($row->source_type ?? null) === 'function' && ! empty($row->function_name)) {
            $code = $this->productivityFunctionCodeByClass((string) $row->function_name);
            if ($code) {
                return $code;
            }
        }

        $text = strtolower(trim((string) ($row->variable_name.' '.$row->description.' '.($row->source_name ?? '').' '.($row->field_name ?? '').' '.($extraConfig['metricCode'] ?? ''))));

        return match (true) {
            str_contains($text, 'hhep') => 'hhep',
            str_contains($text, 'hdp') => 'hdp',
            str_contains($text, 'fcr') => 'fcr',
            str_contains($text, 'flock') || str_contains($text, 'umur') || str_contains($text, 'age') => 'flock_age',
            str_contains($text, 'egg_mass') || str_contains($text, 'egg mass') || str_contains($text, 'massa_telur') => 'egg_mass',
            str_contains($text, 'avg_egg') || str_contains($text, 'berat_rata') || str_contains($text, 'rata') => 'avg_egg_weight',
            str_contains($text, 'feed') || str_contains($text, 'pakan') => 'feed_intake',
            str_contains($text, 'mortal') || str_contains($text, 'kematian') => 'mortalitas',
            default => Str::snake((string) $row->variable_name),
        };
    }

    private function productivityFunctionCodeByClass(string $className): ?string
    {
        if (! Schema::hasTable('livestock_productivity_functions')) {
            foreach ($this->defaultProductivityFunctions() as $code => $meta) {
                if (($meta['service_class'] ?? null) === $className) {
                    return $code;
                }
            }

            return null;
        }

        return DB::table('livestock_productivity_functions')
            ->where('service_class', $className)
            ->where('is_active', true)
            ->value('code');
    }

    private function humanizeSpkInputName(object $row): string
    {
        if (($row->source_type ?? null) === 'function' && ! empty($row->function_name)) {
            $label = $this->productivityFunctionLabelByClass((string) $row->function_name);
            if ($label) {
                return $label;
            }
        }

        return Str::of((string) $row->variable_name)
            ->replace('_', ' ')
            ->title()
            ->toString();
    }

    private function productivityFunctionLabelByClass(string $className): ?string
    {
        if (! Schema::hasTable('livestock_productivity_functions')) {
            foreach ($this->defaultProductivityFunctions() as $meta) {
                if (($meta['service_class'] ?? null) === $className) {
                    return $meta['name'] ?? null;
                }
            }

            return null;
        }

        return DB::table('livestock_productivity_functions')
            ->where('service_class', $className)
            ->where('is_active', true)
            ->value('name');
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
            ->where('required_for_fuzzy', true)
            ->pluck('function_id')
            ->values()
            ->all();
    }

    private function selectedOperationalFunctionIdsForConfig(string $configId): array
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

    private function normalizeProductivityFunctionRow(array $row): array
    {
        $requiredForFuzzy = (bool) ($row['required_for_fuzzy'] ?? false);
        $isActive = (bool) ($row['is_active'] ?? false) || $requiredForFuzzy;

        return [
            'function_id' => trim((string) ($row['function_id'] ?? '')),
            'is_active' => $isActive,
            'required_for_fuzzy' => $isActive && $requiredForFuzzy,
            'aggregation_scope' => in_array(($row['aggregation_scope'] ?? 'today'), ['today', 'week', 'month'], true)
                ? $row['aggregation_scope']
                : 'today',
            'target_min_value' => $this->nullableFloat($row['target_min_value'] ?? null),
            'target_max_value' => $this->nullableFloat($row['target_max_value'] ?? null),
        ];
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
        $requiredForFuzzy = (bool) ($row['required_for_fuzzy'] ?? false);
        $requiredForOperational = (bool) ($row['required_for_iot'] ?? true) || $requiredForFuzzy;

        return [
            'parameter_code' => $this->normalizeParameterCode($row['parameter_code'] ?? ''),
            'parameter_name' => trim((string) ($row['parameter_name'] ?? '')),
            'unit' => $this->nullableString($row['unit'] ?? null),
            'icon_key' => $this->normalizeIconKey($row['icon_key'] ?? 'sensor'),
            'min_value' => $this->nullableFloat($row['min_value'] ?? null),
            'max_value' => $this->nullableFloat($row['max_value'] ?? null),
            'fallback_value' => $this->nullableFloat($row['fallback_value'] ?? null),
            'stale_minutes' => max(1, min(10080, (int) ($row['stale_minutes'] ?? 30))),
            'required_for_iot' => $requiredForOperational,
            'required_for_fuzzy' => $requiredForOperational && $requiredForFuzzy,
        ];
    }

    private function normalizeParameterCode(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z0-9_]+/', '_', $value) ?: '';
        $value = preg_replace('/_+/', '_', $value) ?: '';

        return trim($value, '_');
    }

    private function normalizeIconKey(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9_-]+/', '', $value) ?: '';

        return in_array($value, ['sensor', 'gauge', 'air', 'water', 'light', 'alert'], true)
            ? $value
            : 'sensor';
    }

    private function environmentIconColumnExists(): bool
    {
        if ($this->environmentIconColumnExists === null) {
            $this->environmentIconColumnExists = Schema::hasTable('livestock_environment_parameters')
                && Schema::hasColumn('livestock_environment_parameters', 'icon_key');
        }

        return $this->environmentIconColumnExists;
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

    private function nullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function defaultAfkirCycleSettings(?string $typeName = null): array
    {
        $name = strtolower((string) $typeName);

        if (str_contains($name, 'petelur') || str_contains($name, 'layer')) {
            return [
                'label' => 'Afkir layer',
                'target_weeks' => 80,
                'warning_weeks' => 8,
                'is_configured' => false,
                'production_start_weeks' => 18,
                'peak_start_weeks' => 25,
                'peak_end_weeks' => 45,
                'production_decline_weeks' => 46,
            ];
        }

        if (str_contains($name, 'potong') || str_contains($name, 'broiler') || str_contains($name, 'pedaging')) {
            return [
                'label' => 'Akhir siklus panen',
                'target_weeks' => 6,
                'warning_weeks' => 1,
                'is_configured' => false,
                'production_start_weeks' => null,
                'peak_start_weeks' => null,
                'peak_end_weeks' => null,
                'production_decline_weeks' => null,
            ];
        }

        if (str_contains($name, 'lele') || str_contains($name, 'ikan')) {
            return [
                'label' => 'Akhir siklus panen',
                'target_weeks' => 12,
                'warning_weeks' => 2,
                'is_configured' => false,
                'production_start_weeks' => null,
                'peak_start_weeks' => null,
                'peak_end_weeks' => null,
                'production_decline_weeks' => null,
            ];
        }

        return [
            'label' => 'Afkir / akhir siklus',
            'target_weeks' => null,
            'warning_weeks' => 4,
            'is_configured' => false,
            'production_start_weeks' => null,
            'peak_start_weeks' => null,
            'peak_end_weeks' => null,
            'production_decline_weeks' => null,
        ];
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
