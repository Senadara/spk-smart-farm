<?php

namespace App\Services;

use App\Services\Fuzzy\InputResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PeternakanService
{
    private ?string $activeKomoditasId = null;

    private ?string $activeJenisBudidayaId = null;

    private ?array $cachedBarnEnvironment = null;

    private ?bool $unitBudidayaHasUmurMinggu = null;

    public function __construct(
        protected LivestockMasterConfigService $livestockMasterConfigService,
        protected InputResolver $fuzzyInputResolver
    ) {}

    public function forKomoditas(?string $komoditasId): self
    {
        $this->activeKomoditasId = $this->resolveKomoditasId($komoditasId);
        $komod = $this->activeKomoditasId
            ? DB::table('komoditas')->where('id', $this->activeKomoditasId)->where('isDeleted', 0)->first()
            : null;

        $this->activeJenisBudidayaId = $komod?->jenisBudidayaId
            ?? $this->livestockMasterConfigService->firstLivestockJenisBudidayaId();

        $this->cachedBarnEnvironment = null;

        return $this;
    }

    public function forJenisTernak(?string $jenisBudidayaId, ?string $komoditasId = null): self
    {
        $this->activeJenisBudidayaId = $this->livestockMasterConfigService
            ->resolveLivestockJenisBudidayaId($jenisBudidayaId);
        $this->activeKomoditasId = $this->livestockMasterConfigService
            ->resolveLivestockCommodityIdForJenis($this->activeJenisBudidayaId, $komoditasId);
        $this->cachedBarnEnvironment = null;

        return $this;
    }

    public function getActiveKomoditasId(): ?string
    {
        return $this->activeKomoditasId;
    }

    public function getActiveJenisBudidayaId(): ?string
    {
        return $this->activeJenisBudidayaId;
    }

    public function resolveKomoditasId(?string $requestedId): ?string
    {
        return $this->livestockMasterConfigService->resolveLivestockCommodityId($requestedId);
    }

    public function getActiveCoopIds(bool $activeOnly = true): array
    {
        if (! $this->activeJenisBudidayaId) {
            return [];
        }

        $query = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
            ->where('isDeleted', 0);

        if ($activeOnly) {
            $query->where('status', 1);
        }

        return $query->pluck('id')->toArray();
    }

    private function hasUnitBudidayaUmurMingguColumn(): bool
    {
        if ($this->unitBudidayaHasUmurMinggu === null) {
            $this->unitBudidayaHasUmurMinggu = Schema::hasColumn('unitBudidaya', 'umurMinggu');
        }

        return $this->unitBudidayaHasUmurMinggu;
    }

    private function unitBudidayaAgeSelect(string $prefix = 'unitBudidaya.'): array
    {
        return $this->hasUnitBudidayaUmurMingguColumn()
            ? [$prefix.'umurMinggu']
            : [];
    }

    private function flockAgeWeeks(object $coop, ?Carbon $date = null): int
    {
        if (property_exists($coop, 'umurMinggu') && $coop->umurMinggu !== null && is_numeric($coop->umurMinggu)) {
            return max(0, (int) $coop->umurMinggu);
        }

        if (! empty($coop->createdAt)) {
            return max(0, (int) floor(Carbon::parse($coop->createdAt)->diffInWeeks($date ?? now())));
        }

        return 0;
    }

    private function barnPhotoUrl(?string $image): string
    {
        $fallback = asset('images/barn-placeholder.jpg');
        $value = trim((string) $image);

        if ($value === '' || $value === '-' || str_starts_with($value, 'system://')) {
            return $fallback;
        }

        if (preg_match('/^https?:\/\//i', $value)) {
            return $value;
        }

        if (str_starts_with($value, '//')) {
            return request()->getScheme().':'.$value;
        }

        $path = ltrim(str_replace('\\', '/', $value), '/');

        if ($path === '') {
            return $fallback;
        }

        if (str_starts_with($path, 'public/')) {
            return asset('storage/'.substr($path, 7));
        }

        if (str_starts_with($path, 'storage/') || str_starts_with($path, 'images/')) {
            return asset($path);
        }

        if (str_starts_with($path, 'uploads/') || str_starts_with($path, 'upload/')) {
            return url('/'.$path);
        }

        return asset('storage/'.$path);
    }

    public function getDailyReportStatus(): array
    {
        $today = now()->toDateString();
        $activeCoops = $this->activeJenisBudidayaId
            ? DB::table('unitBudidaya')
                ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
                ->where('status', 1)
                ->where('isDeleted', 0)
                ->orderBy('nama')
                ->get(['id', 'nama'])
            : collect();

        if ($activeCoops->isEmpty()) {
            return [
                'status' => 'no_coops',
                'isReady' => false,
                'title' => 'Belum ada kandang aktif',
                'message' => 'Tambahkan unit budidaya aktif terlebih dahulu agar laporan harian dan KPI dapat dihitung.',
                'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
                'reportedCount' => 0,
                'totalCoops' => 0,
                'missingBarns' => [],
                'lastReportAt' => null,
            ];
        }

        $coopIds = $activeCoops->pluck('id')->all();
        $reportedCoopIds = DB::table('laporan')
            ->whereIn('unitBudidayaId', $coopIds)
            ->where('isDeleted', 0)
            ->whereDate('createdAt', $today)
            ->distinct()
            ->pluck('unitBudidayaId')
            ->all();

        $missingBarns = $activeCoops
            ->reject(fn ($coop) => in_array($coop->id, $reportedCoopIds, true))
            ->pluck('nama')
            ->values()
            ->all();

        $lastReportAt = DB::table('laporan')
            ->whereIn('unitBudidayaId', $coopIds)
            ->where('isDeleted', 0)
            ->max('createdAt');

        $reportedCount = count($reportedCoopIds);
        $totalCoops = $activeCoops->count();
        $status = match (true) {
            $reportedCount === 0 => 'empty',
            $reportedCount < $totalCoops => 'partial',
            default => 'complete',
        };

        $copy = [
            'empty' => [
                'title' => 'Laporan harian hari ini belum masuk',
                'message' => 'KPI produksi seperti HDP, FCR, feed intake, dan egg mass akan tampil setelah laporan panen atau pakan hari ini dicatat.',
            ],
            'partial' => [
                'title' => 'Laporan harian belum lengkap',
                'message' => 'Sebagian kandang sudah memiliki laporan hari ini, tetapi hasil dashboard belum mewakili seluruh komoditas.',
            ],
            'complete' => [
                'title' => 'Laporan harian sudah lengkap',
                'message' => 'KPI produksi hari ini sudah dihitung dari laporan kandang aktif.',
            ],
        ];

        return [
            'status' => $status,
            'isReady' => $status === 'complete',
            'title' => $copy[$status]['title'],
            'message' => $copy[$status]['message'],
            'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
            'reportedCount' => $reportedCount,
            'totalCoops' => $totalCoops,
            'missingBarns' => $missingBarns,
            'lastReportAt' => $lastReportAt
                ? Carbon::parse($lastReportAt)->locale('id')->translatedFormat('d M Y, H:i')
                : null,
        ];
    }

    public function getCommodityThresholds(): array
    {
        $defaults = [
            'TEMP' => ['min' => 20, 'max' => 28],
            'HUMID' => ['min' => 50, 'max' => 70],
            'AMMON' => ['min' => 0,  'max' => 15],
            'AMMONIA' => ['min' => 0,  'max' => 15],
            'AMMA' => ['min' => 0,  'max' => 15],
            'LUX' => ['min' => 15, 'max' => 50],
            'LIGHT' => ['min' => 15, 'max' => 50],
        ];

        if (! $this->activeKomoditasId) {
            return $defaults;
        }

        $rows = DB::table('commodity_parameter')
            ->join('iot_parameter', 'commodity_parameter.parameterId', '=', 'iot_parameter.id')
            ->where('commodity_parameter.commodityId', $this->activeKomoditasId)
            ->get(['iot_parameter.parameterCode', 'commodity_parameter.minValue', 'commodity_parameter.maxValue']);

        foreach ($rows as $row) {
            $defaults[$row->parameterCode] = [
                'min' => (float) $row->minValue,
                'max' => (float) $row->maxValue,
            ];
        }

        if (Schema::hasTable('livestock_master_configs') && Schema::hasTable('livestock_environment_parameters')) {
            $masterRows = DB::table('livestock_environment_parameters')
                ->join('livestock_master_configs', 'livestock_master_configs.id', '=', 'livestock_environment_parameters.config_id')
                ->join('komoditas', 'komoditas.jenisBudidayaId', '=', 'livestock_master_configs.jenis_budidaya_id')
                ->where('komoditas.id', $this->activeKomoditasId)
                ->where('livestock_environment_parameters.is_active', true)
                ->get([
                    'livestock_environment_parameters.parameter_code',
                    'livestock_environment_parameters.min_value',
                    'livestock_environment_parameters.max_value',
                ]);

            foreach ($masterRows as $row) {
                $defaults[$row->parameter_code] = [
                    'min' => $row->min_value !== null ? (float) $row->min_value : null,
                    'max' => $row->max_value !== null ? (float) $row->max_value : null,
                ];
            }
        }

        return $defaults;
    }

    private function evaluateSensorStatus(float $value, ?float $min, ?float $max): string
    {
        if ($value <= 0) {
            return 'normal';
        }

        if ($min !== null && $value < $min) {
            $gap = ($min - $value) / max(abs($min), 1);

            return $gap > 0.1 ? 'danger' : 'warning';
        }

        if ($max !== null && $value > $max) {
            $gap = ($value - $max) / max(abs($max), 1);

            return $gap > 0.1 ? 'danger' : 'warning';
        }

        return 'normal';
    }

    private function worstStatus(string ...$statuses): string
    {
        if (in_array('danger', $statuses, true)) {
            return 'danger';
        }
        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        return 'normal';
    }

    private function thresholdFor(array $thresholds, string $code): array
    {
        foreach ($this->environmentCodeAliases($code) as $key) {
            if (isset($thresholds[$key])) {
                return $thresholds[$key];
            }
        }

        return ['min' => null, 'max' => null];
    }

    public function activeProductivityFunctionCodes(): array
    {
        if (! $this->livestockMasterConfigService->hasSchema()) {
            return [];
        }

        return $this->livestockMasterConfigService
            ->selectedProductivityFunctionCodesForCommodity($this->activeKomoditasId);
    }

    public function filterProductivityCardsByMaster(array $cards, string $labelKey = 'label'): array
    {
        $selectedCodes = $this->activeProductivityFunctionCodes();
        if (empty($selectedCodes)) {
            return [];
        }

        return array_values(array_filter($cards, function (array $card) use ($selectedCodes, $labelKey) {
            $code = $card['code'] ?? $this->productivityCodeForLabel((string) ($card[$labelKey] ?? ''));

            return $code && in_array($code, $selectedCodes, true);
        }));
    }

    public function filterEnvironmentCardsByMaster(array $cards): array
    {
        if (! $this->livestockMasterConfigService->hasSchema()) {
            return [];
        }

        $allowedTokens = $this->livestockMasterConfigService
            ->configuredSpkInputKeysForCommodity($this->activeKomoditasId, 'lingkungan');

        if (empty($allowedTokens)) {
            return [];
        }

        return array_values(array_filter($cards, function (array $card) use ($allowedTokens) {
            $haystack = strtolower(trim(($card['key'] ?? '').' '.($card['label'] ?? '')));
            if ($haystack === '') {
                return false;
            }

            foreach ($allowedTokens as $token) {
                if ($token !== '' && str_contains($haystack, $token)) {
                    return true;
                }
            }

            return false;
        }));
    }

    private function productivityCodeForLabel(string $label): ?string
    {
        $label = strtolower($label);

        return match (true) {
            str_contains($label, 'hhep') => 'hhep',
            str_contains($label, 'hdp') => 'hdp',
            str_contains($label, 'fcr') => 'fcr',
            str_contains($label, 'umur') || str_contains($label, 'flock') => 'flock_age',
            str_contains($label, 'egg mass') => 'egg_mass',
            str_contains($label, 'berat rata') || str_contains($label, 'average egg') => 'avg_egg_weight',
            str_contains($label, 'feed') || str_contains($label, 'pakan') => 'feed_intake',
            str_contains($label, 'mortal') || str_contains($label, 'kematian') => 'mortalitas',
            default => null,
        };
    }

    private function environmentCodeAliases(string $code): array
    {
        $code = strtoupper(trim($code));

        return match ($code) {
            'TEMP', 'SUHU', 'TEMPERATURE' => ['TEMP', 'SUHU', 'TEMPERATURE', 'suhu', 'temperature'],
            'HUMID', 'HUMIDITY', 'KELEMBAPAN' => ['HUMID', 'HUMIDITY', 'KELEMBAPAN', 'kelembapan', 'humidity'],
            'AMMON', 'AMMONIA', 'AMMA', 'NH3', 'AMONIA' => ['AMMON', 'AMMONIA', 'AMMA', 'NH3', 'AMONIA', 'amonia', 'ammonia'],
            'LIGHT', 'LUX', 'CAHAYA' => ['LIGHT', 'LUX', 'CAHAYA', 'cahaya', 'light', 'lux'],
            default => [$code, strtolower($code)],
        };
    }

    private function sensorValueFromMap(array $mapped, string $code): float
    {
        $value = $this->sensorValueFromMapOrNull($mapped, $code);

        return $value === null ? 0.0 : $value;
    }

    private function sensorValueFromMapOrNull(array $mapped, string $code): ?float
    {
        foreach ($this->environmentCodeAliases($code) as $alias) {
            if (array_key_exists($alias, $mapped)) {
                return (float) $mapped[$alias];
            }

            $upperAlias = strtoupper($alias);
            if (array_key_exists($upperAlias, $mapped)) {
                return (float) $mapped[$upperAlias];
            }
        }

        return null;
    }

    private function sensorSourceFromMap(array $sources, string $code): array
    {
        foreach ($this->environmentCodeAliases($code) as $alias) {
            if (array_key_exists($alias, $sources)) {
                return (array) $sources[$alias];
            }

            $upperAlias = strtoupper($alias);
            if (array_key_exists($upperAlias, $sources)) {
                return (array) $sources[$upperAlias];
            }
        }

        return ['type' => 'missing', 'label' => null];
    }

    private function latestSensorReadingsForDevices(array $devices): array
    {
        if (empty($devices) || ! Schema::hasTable('iot_sensor_data') || ! Schema::hasTable('iot_parameter')) {
            return [];
        }

        $query = DB::table('iot_sensor_data')
            ->join('iot_parameter', 'iot_sensor_data.parameterId', '=', 'iot_parameter.id')
            ->whereIn('iot_sensor_data.deviceId', $devices)
            ->orderBy('iot_sensor_data.sensorTimestamp', 'desc')
            ->limit(100);

        if (Schema::hasColumn('iot_sensor_data', 'isDeleted')) {
            $query->where('iot_sensor_data.isDeleted', 0);
        }

        $readings = [];
        foreach ($query->get(['iot_sensor_data.value', 'iot_sensor_data.sensorTimestamp', 'iot_parameter.parameterCode']) as $row) {
            $code = strtoupper((string) $row->parameterCode);
            if ($code === '' || isset($readings[$code])) {
                continue;
            }

            $readings[$code] = [
                'value' => (float) $row->value,
                'timestamp' => Carbon::parse($row->sensorTimestamp),
            ];
        }

        return $readings;
    }

    private function sensorReadingFromMap(array $readings, string $code): ?array
    {
        foreach ($this->environmentCodeAliases($code) as $alias) {
            $upperAlias = strtoupper($alias);
            if (isset($readings[$upperAlias])) {
                return $readings[$upperAlias];
            }
        }

        return null;
    }

    private function resolveFuzzyEnvironmentSensorValues(?string $coopId): array
    {
        try {
            $inputs = $this->fuzzyInputResolver->resolve($coopId, $this->activeKomoditasId);
            $parameters = $this->livestockMasterConfigService
                ->configuredSpkInputParametersForCommodity($this->activeKomoditasId, 'lingkungan', true);
        } catch (\Throwable) {
            return [];
        }

        $values = [];
        foreach ($parameters as $parameter) {
            if (($parameter['source_type'] ?? null) !== 'iot') {
                continue;
            }

            $code = strtoupper((string) ($parameter['source_code'] ?? $parameter['code'] ?? ''));
            $variableName = (string) ($parameter['variable_name'] ?? '');
            if ($code === '' || $variableName === '' || ! array_key_exists($variableName, $inputs)) {
                continue;
            }

            $values[$code] = (float) $inputs[$variableName];
        }

        return $values;
    }

    private function parameterFallbackValue(object $parameter, string $code): ?float
    {
        if (is_numeric($parameter->fallback_value ?? null)) {
            return (float) $parameter->fallback_value;
        }

        return match (strtoupper($code)) {
            'TEMP', 'SUHU', 'TEMPERATURE' => 26.0,
            'HUMID', 'HUMIDITY', 'KELEMBAPAN' => 65.0,
            'AMMON', 'AMMONIA', 'AMMA', 'NH3', 'AMONIA' => 5.0,
            'LIGHT', 'LUX', 'CAHAYA' => 250.0,
            default => null,
        };
    }

    private function environmentSensorContextForBarn(?string $coopId, array $devices, iterable $environmentParams): array
    {
        $latestReadings = $this->latestSensorReadingsForDevices($devices);
        $fuzzyFallbackValues = $this->resolveFuzzyEnvironmentSensorValues($coopId);
        $mapped = [];
        $sources = [];

        foreach ($environmentParams as $parameter) {
            $code = strtoupper((string) ($parameter->parameter_code ?? $parameter->code ?? ''));
            if ($code === '') {
                continue;
            }

            $reading = $this->sensorReadingFromMap($latestReadings, $code);
            $staleMinutes = max(1, (int) ($parameter->stale_minutes ?? 30));
            $isFresh = $reading
                && ($reading['timestamp'] ?? now())->gte(now()->subMinutes($staleMinutes));

            if ($isFresh) {
                $mapped[$code] = (float) $reading['value'];
                $sources[$code] = ['type' => 'actual', 'label' => null];
                continue;
            }

            $fuzzyValue = $this->sensorValueFromMapOrNull($fuzzyFallbackValues, $code);
            if ($fuzzyValue !== null) {
                $mapped[$code] = $fuzzyValue;
                $sources[$code] = ['type' => 'fallback', 'label' => 'Default SPK'];
                continue;
            }

            $fallback = $this->parameterFallbackValue($parameter, $code);
            if ($fallback !== null) {
                $mapped[$code] = $fallback;
                $sources[$code] = ['type' => 'fallback', 'label' => 'Default konfigurasi'];
                continue;
            }

            if ($reading) {
                $mapped[$code] = (float) $reading['value'];
                $sources[$code] = ['type' => 'stale', 'label' => 'Data lama'];
            }
        }

        return [$mapped, $sources];
    }

    private function sensorPercent(float $value, ?float $min, ?float $max): int
    {
        if ($value <= 0) {
            return 0;
        }

        if ($max !== null && $max > 0) {
            return (int) min(100, max(0, round(($value / $max) * 100)));
        }

        if ($min !== null && $min > 0) {
            return (int) min(100, max(0, round(($value / $min) * 100)));
        }

        return (int) min(100, max(0, round($value)));
    }

    private function formatSensorValue(float $value, ?string $unit): string
    {
        $formatted = rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');

        return trim($formatted.' '.($unit ?? ''));
    }

    private function configuredEnvironmentForBarn(iterable $environmentParams, array $thresholds, array $mapped, array $statusLabels, array $sources = []): array
    {
        $sensorCards = [];
        $statuses = [];
        $summary = [
            'avg_temp' => '-',
            'humidity' => '-',
            'ammonia' => '-',
            'ammonia_ok' => true,
            'lux' => '-',
            'temp_status' => 'normal',
            'humidity_status' => 'normal',
            'ammonia_status' => 'normal',
            'lux_status' => 'normal',
            'parameters' => [],
        ];
        $temp = 0.0;
        $displaySensor = null;

        foreach ($environmentParams as $parameter) {
            $code = strtoupper((string) ($parameter->parameter_code ?? $parameter->code ?? ''));
            $name = (string) ($parameter->parameter_name ?? $parameter->name ?? $code);
            $unit = (string) ($parameter->unit ?? '');
            if ($code === '' || $name === '') {
                continue;
            }

            $threshold = $this->thresholdFor($thresholds, $code);
            $value = $this->sensorValueFromMap($mapped, $code);
            $source = $this->sensorSourceFromMap($sources, $code);
            $sensorStatus = $this->evaluateSensorStatus($value, $threshold['min'] ?? null, $threshold['max'] ?? null);
            $displayValue = $this->formatSensorValue($value, $unit);
            $card = [
                'code' => $code,
                'label' => $name.' ('.$displayValue.')',
                'name' => $name,
                'value' => round($value, 2),
                'valueLabel' => $displayValue,
                'unit' => $unit,
                'iconKey' => (string) ($parameter->icon_key ?? 'sensor'),
                'percent' => $this->sensorPercent($value, $threshold['min'] ?? null, $threshold['max'] ?? null),
                'status' => $sensorStatus,
                'statusLabel' => $statusLabels[$sensorStatus] ?? $sensorStatus,
                'min' => $threshold['min'] ?? null,
                'max' => $threshold['max'] ?? null,
                'dataSource' => $source['type'] ?? 'missing',
                'dataSourceLabel' => $source['label'] ?? null,
                'isFallback' => ($source['type'] ?? null) === 'fallback',
            ];

            $sensorCards[] = $card;
            $summary['parameters'][] = $card;
            $statuses[] = $sensorStatus;
            $displaySensor ??= $card;

            $aliases = $this->environmentCodeAliases($code);
            if (in_array('TEMP', $aliases, true)) {
                $temp = round($value, 1);
                $summary['avg_temp'] = $displayValue;
                $summary['temp_status'] = $sensorStatus;
                $displaySensor = $card;
            }
            if (in_array('HUMID', $aliases, true)) {
                $summary['humidity'] = $displayValue;
                $summary['humidity_status'] = $sensorStatus;
            }
            if (in_array('AMMON', $aliases, true)) {
                $summary['ammonia'] = $displayValue;
                $summary['ammonia_ok'] = $sensorStatus === 'normal';
                $summary['ammonia_status'] = $sensorStatus;
            }
            if (in_array('LIGHT', $aliases, true)) {
                $summary['lux'] = $displayValue;
                $summary['lux_status'] = $sensorStatus;
            }
        }

        return [
            'applies' => true,
            'temp' => $temp,
            'display_sensor_label' => $displaySensor['name'] ?? 'Sensor',
            'display_sensor_value' => $displaySensor['valueLabel'] ?? '-',
            'status' => empty($statuses) ? 'normal' : $this->worstStatus(...$statuses),
            'sensors' => $sensorCards,
            'summary' => $summary,
        ];
    }

    public function getBarnDetail(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (! $coopId || $coopId === 'no-data') {
            return array_merge($barn, [
                'flockAge' => '-',
                'totalBirds' => '-',
                'capacity' => '-',
                'breed' => '-',
                'type' => '-',
                'startDate' => '-',
                'location' => '-',
                'photo' => asset('images/barn-placeholder.jpg'),
                'photoFallback' => asset('images/barn-placeholder.jpg'),
            ]);
        }

        $coop = DB::table('unitBudidaya')
            ->leftJoin('jenisBudidaya', 'unitBudidaya.jenisBudidayaId', '=', 'jenisBudidaya.id')
            ->where('unitBudidaya.id', $coopId)
            ->select('unitBudidaya.*', 'jenisBudidaya.nama as breedName')
            ->first();

        if (! $coop) {
            return array_merge($barn, [
                'flockAge' => '-',
                'totalBirds' => '-',
                'capacity' => '-',
                'breed' => '-',
                'type' => '-',
                'startDate' => '-',
                'location' => '-',
                'photo' => asset('images/barn-placeholder.jpg'),
                'photoFallback' => asset('images/barn-placeholder.jpg'),
            ]);
        }

        $createdAt = \Carbon\Carbon::parse($coop->createdAt);
        $weeks = $this->flockAgeWeeks($coop);

        return array_merge($barn, [
            'flockAge' => $weeks.' Minggu',
            'totalBirds' => number_format((float) ($coop->jumlah ?? 0), 0, ',', '.'),
            'capacity' => number_format((float) ($coop->kapasitas ?? 0), 0, ',', '.'),
            'breed' => $coop->breedName ?? '-',
            'type' => $coop->tipe ?? ($barn['type'] ?? '-'),
            'startDate' => $createdAt->format('Y-m-d'),
            'location' => $coop->lokasi ?? '-',
            'photo' => $this->barnPhotoUrl($coop->gambar ?? null),
            'photoFallback' => asset('images/barn-placeholder.jpg'),
        ]);
    }

    public function getBarnSensors(array $barn): array
    {
        if (empty($barn['id']) || $barn['id'] === 'no-data') {
            return [];
        }

        if (! empty($barn['sensors']) && is_array($barn['sensors'])) {
            return array_values(array_map(function (array $sensor) {
                return [
                    'code' => $sensor['code'] ?? null,
                    'label' => $sensor['name'] ?? $sensor['label'] ?? 'Sensor',
                    'value' => is_numeric($sensor['value'] ?? null) ? (float) $sensor['value'] : 0.0,
                    'valueLabel' => $sensor['valueLabel'] ?? null,
                    'unit' => $sensor['unit'] ?? '',
                    'min' => $sensor['min'] ?? 0,
                    'max' => $sensor['max'] ?? 100,
                    'idealMin' => $sensor['min'] ?? 0,
                    'idealMax' => $sensor['max'] ?? 100,
                    'status' => $sensor['status'] ?? 'normal',
                    'iconKey' => $sensor['iconKey'] ?? 'sensor',
                    'dataSource' => $sensor['dataSource'] ?? 'missing',
                    'dataSourceLabel' => $sensor['dataSourceLabel'] ?? null,
                    'isFallback' => (bool) ($sensor['isFallback'] ?? false),
                ];
            }, $barn['sensors']));
        }

        return [];

        if (empty($barn['id']) || $barn['id'] === 'no-data') {
            return [
                ['label' => 'Suhu', 'value' => 0, 'unit' => '°C', 'min' => 18, 'max' => 30, 'idealMin' => 20, 'idealMax' => 28, 'status' => 'normal', 'icon' => '🌡️'],
                ['label' => 'Kelembapan', 'value' => 0, 'unit' => '%', 'min' => 30, 'max' => 100, 'idealMin' => 50, 'idealMax' => 70, 'status' => 'normal', 'icon' => '💧'],
                ['label' => 'Amonia', 'value' => 0, 'unit' => 'ppm', 'min' => 0, 'max' => 50, 'idealMin' => 0, 'idealMax' => 15, 'status' => 'normal', 'icon' => '🌬️'],
                ['label' => 'Cahaya', 'value' => 0, 'unit' => 'lux', 'min' => 0, 'max' => 50, 'idealMin' => 15, 'idealMax' => 30, 'status' => 'normal', 'icon' => '☀️'],
            ];
        }

        return [
            ['label' => 'Suhu', 'value' => floatval(str_replace('°C', '', $barn['summary']['avg_temp'])), 'unit' => '°C', 'min' => 18, 'max' => 45, 'idealMin' => 20, 'idealMax' => 28, 'status' => $barn['sensors'][0]['status'] ?? 'normal', 'icon' => '🌡️'],
            ['label' => 'Kelembapan', 'value' => floatval(str_replace('%', '', $barn['summary']['humidity'])), 'unit' => '%', 'min' => 30, 'max' => 100, 'idealMin' => 50, 'idealMax' => 70, 'status' => $barn['sensors'][1]['status'] ?? 'normal', 'icon' => '💧'],
            ['label' => 'Amonia', 'value' => floatval(str_replace('ppm', '', $barn['summary']['ammonia'])), 'unit' => 'ppm', 'min' => 0, 'max' => 50, 'idealMin' => 0, 'idealMax' => 20, 'status' => $barn['sensors'][2]['status'] ?? 'normal', 'icon' => '🌬️'],
            ['label' => 'Cahaya', 'value' => floatval(str_replace(' lx', '', $barn['summary']['lux'])), 'unit' => 'lux', 'min' => 0, 'max' => 500, 'idealMin' => 15, 'idealMax' => 50, 'status' => $barn['sensors'][3]['status'] ?? 'normal', 'icon' => '☀️'],
        ];
    }

    public function getBarnSensorTrend($barnId): array
    {
        $labels = [];
        $temp = [];
        $hum = [];
        $ammonia = [];
        $light = [];

        // Pre-fill labels 24 hours back to ensure continuity
        for ($i = 23; $i >= 0; $i--) {
            $labels[] = now()->subHours($i)->format('H:00');
            $temp[] = null;
            $hum[] = null;
            $ammonia[] = null;
            $light[] = null;
        }

        if (! $barnId || $barnId === 'no-data') {
            return ['labels' => $labels, 'temperature' => array_map(fn () => 0, $temp), 'humidity' => array_map(fn () => 0, $hum), 'ammonia' => array_map(fn () => 0, $ammonia), 'light' => array_map(fn () => 0, $light)];
        }

        $devices = DB::table('iot_device')->where('unitBudidayaId', $barnId)->pluck('id')->toArray();
        if (! empty($devices)) {
            $yesterday = now()->subHours(24);
            $logs = DB::table('iot_sensor_data')
                ->join('iot_parameter', 'iot_parameter.id', '=', 'iot_sensor_data.parameterId')
                ->whereIn('iot_sensor_data.deviceId', $devices)
                ->where('iot_sensor_data.sensorTimestamp', '>=', $yesterday)
                ->selectRaw('iot_parameter.parameterCode as code, DATE_FORMAT(iot_sensor_data.sensorTimestamp, "%H:00") as hour_label, AVG(iot_sensor_data.value) as avg_value')
                ->groupBy('code', 'hour_label')
                ->get();

            // Map data to the correct hours
            foreach ($logs as $log) {
                // Find index
                $idx = array_search($log->hour_label, $labels);
                if ($idx !== false) {
                    if ($log->code === 'TEMP') {
                        $temp[$idx] = round($log->avg_value, 1);
                    }
                    if ($log->code === 'HUMID') {
                        $hum[$idx] = round($log->avg_value, 1);
                    }
                    if ($log->code === 'AMMON' || $log->code === 'AMMO' || $log->code === 'AMMA' || $log->code === 'AMMONIA') {
                        $ammonia[$idx] = round($log->avg_value, 1);
                    }
                    if ($log->code === 'LIGHT' || $log->code === 'LUX') {
                        $light[$idx] = round($log->avg_value, 0);
                    }
                }
            }

            // Interpolate nulls or set to 0
            $temp = $this->interpolateArray($temp);
            $hum = $this->interpolateArray($hum);
            $ammonia = $this->interpolateArray($ammonia);
            $light = $this->interpolateArray($light);
        } else {
            $temp = array_map(fn () => 0, $temp);
            $hum = array_map(fn () => 0, $hum);
            $ammonia = array_map(fn () => 0, $ammonia);
            $light = array_map(fn () => 0, $light);
        }

        return ['labels' => $labels, 'temperature' => $temp, 'humidity' => $hum, 'ammonia' => $ammonia, 'light' => $light];
    }

    private function interpolateArray(array $arr): array
    {
        $lastVal = 0;
        foreach ($arr as $k => $v) {
            if ($v !== null) {
                $lastVal = $v;
            } else {
                $arr[$k] = $lastVal;
            }
        }

        return $arr;
    }

    public function getBarnKpi(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (! $coopId || $coopId === 'no-data') {
            return ['hdp' => 0, 'hhep' => 0, 'feedIntake' => 0, 'fcr' => 0, 'gradeTelur' => ['A' => 0, 'B' => 0, 'C' => 0], 'mortalitas' => 0, 'afkir' => 0, 'usiaAwalBertelur' => '-', 'puncakProduksi' => 'Belum Produksi'];
        }

        $today = now()->toDateString();
        $coop = DB::table('unitBudidaya')->where('id', $coopId)->first(['jumlah', 'createdAt']);
        if (! $coop) {
            return ['hdp' => 0, 'hhep' => 0, 'feedIntake' => 0, 'fcr' => 0, 'gradeTelur' => ['A' => 0, 'B' => 0, 'C' => 0], 'mortalitas' => 0, 'afkir' => 0, 'usiaAwalBertelur' => '-', 'puncakProduksi' => 'Belum Produksi'];
        }
        $populasiAwal = $coop->jumlah ?? 0;

        $mati = DB::table('kematian')
            ->join('laporan', 'laporan.id', '=', 'kematian.laporanId')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();

        $totalMati = $mati;
        $populasiAwal += $totalMati; // reconstruct populasi mula-mula
        $populasiSaatIni = $populasiAwal - $totalMati;

        $mortalitas = $populasiAwal > 0 ? ($totalMati / $populasiAwal) * 100 : 0;

        $panenToday = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as totalTelur, COALESCE(SUM(COALESCE(panen.berat, 0)), 0) as totalEggMass')
            ->first();

        $totalTelur = (float) ($panenToday->totalTelur ?? 0);
        $totalEggMass = (float) ($panenToday->totalEggMass ?? 0);

        $pakanToday = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->sum('harianTernak.pakan');

        $hdp = $populasiSaatIni > 0 ? ($totalTelur / $populasiSaatIni) * 100 : 0;
        $hhep = $populasiAwal > 0 ? ($totalTelur / $populasiAwal) * 100 : 0;
        $feedIntake = $populasiSaatIni > 0 ? ($pakanToday / $populasiSaatIni) * 1000 : 0;
        $fcr = $totalEggMass > 0 ? $pakanToday / $totalEggMass : 0;

        // Fetch valid grades for chart
        $hasGradeWeight = DB::getSchemaBuilder()->hasColumn('panenRincianGrade', 'berat');
        $gradeSelect = 'grade.nama as grade_name, SUM(COALESCE(panenRincianGrade.jumlah, 0)) as total_jumlah';
        if ($hasGradeWeight) {
            $gradeSelect .= ', SUM(COALESCE(panenRincianGrade.berat, 0)) as total_berat';
        }

        $gradeRows = DB::table('panenRincianGrade')
            ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->where('panenRincianGrade.isDeleted', 0)
            ->where('grade.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw($gradeSelect)
            ->groupBy('grade.nama')
            ->get()
            ->keyBy('grade_name');

        $totalGradeJumlah = (float) $gradeRows->sum(fn ($row) => (float) ($row->total_jumlah ?? 0));
        $totalGradeBerat = $hasGradeWeight
            ? (float) $gradeRows->sum(fn ($row) => (float) ($row->total_berat ?? 0))
            : 0.0;
        $useGradeWeight = $totalGradeJumlah <= 0 && $totalGradeBerat > 0;

        $gradeValue = fn (string $name): float => (float) (
            $useGradeWeight
                ? ($gradeRows->get($name)->total_berat ?? 0)
                : ($gradeRows->get($name)->total_jumlah ?? 0)
        );

        $totalGradeA = $gradeValue('Grade A');
        $totalGradeB = $gradeValue('Grade B');
        $totalGradeC = $gradeValue('Grade C');
        $totalGrades = $totalGradeA + $totalGradeB + $totalGradeC;
        $totalRejectEggs = (float) $gradeRows
            ->filter(fn ($row, $name) => $this->isRejectEggGradeName((string) $name))
            ->sum(fn ($row) => (float) ($row->total_jumlah ?? 0));
        $rejectRate = $totalTelur > 0 ? ($totalRejectEggs / $totalTelur) * 100 : 0;

        $gradeTelur = ['A' => 0, 'B' => 0, 'C' => 0];
        if ($totalGrades > 0) {
            $gradeTelur = [
                'A' => round(($totalGradeA / $totalGrades) * 100),
                'B' => round(($totalGradeB / $totalGrades) * 100),
                'C' => round(($totalGradeC / $totalGrades) * 100),
            ];
        }

        return [
            'hdp' => round($hdp, 1),
            'hhep' => round($hhep, 1),
            'feedIntake' => round($feedIntake, 0),
            'fcr' => round($fcr, 2),
            'gradeTelur' => $gradeTelur,
            'mortalitas' => round($mortalitas, 2),
            'afkir' => round($rejectRate, 2),
            'usiaAwalBertelur' => '18 Minggu',
            'puncakProduksi' => 'Fase Produksi',
        ];
    }

    public function getBarnProductionLog(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (! $coopId || $coopId === 'no-data') {
            return [];
        }

        $coop = DB::table('unitBudidaya')->where('id', $coopId)->first(['jumlah']);
        $populasi = $coop ? (float) $coop->jumlah : 0;

        $log = [];
        // Populate exactly 7 days
        for ($i = 0; $i < 7; $i++) {
            $date = now()->subDays($i)->toDateString();

            $telur = DB::table('panen')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('laporan.createdAt', $date)
                ->where('laporan.isDeleted', 0)
                ->where('panen.isDeleted', 0)
                ->sum('panen.jumlah');

            $pakan = DB::table('harianTernak')
                ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $coopId)->whereDate('laporan.createdAt', $date)
                ->where('laporan.isDeleted', 0)
                ->where('harianTernak.isDeleted', 0)
                ->sum('harianTernak.pakan');

            $mati = $this->countMortalityForBarnDate($coopId, $date);
            $rejects = $this->sumRejectEggsForBarnDate($coopId, $date);
            $populationAtDate = $this->populationAtReportTime($coopId, Carbon::parse($date)->endOfDay()->toDateTimeString(), $populasi);

            $hdp = $populationAtDate > 0 && $telur > 0 ? round(($telur / $populationAtDate) * 100, 1).'%' : '-';

            $log[] = [
                'date' => Carbon::parse($date)->format('d M Y'),
                'eggs' => $telur > 0 ? number_format((float) $telur, 0, ',', '.') : '-',
                'rejects' => $rejects > 0 ? number_format((float) $rejects, 0, ',', '.') : '-',
                'rejectsCount' => $rejects,
                'feedKg' => $pakan > 0 ? round($pakan, 1) : '-',
                'mortality' => $mati > 0 ? $mati : '-',
                'mortalityCount' => $mati,
                'hdp' => $hdp,
            ];
        }

        return $log;
    }

    public function getBarnIotDevices(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (! $coopId || $coopId === 'no-data') {
            return [];
        }

        $devices = DB::table('iot_device')
            ->leftJoin('iot_connection_config', 'iot_device.connectionConfigId', '=', 'iot_connection_config.id')
            ->leftJoin('iot_protocol', 'iot_connection_config.protocolId', '=', 'iot_protocol.id')
            ->where('iot_device.unitBudidayaId', $coopId)
            ->select('iot_device.*', 'iot_protocol.protocolName')
            ->get();

        $result = [];
        foreach ($devices as $d) {
            $lastData = DB::table('iot_sensor_data')
                ->where('deviceId', $d->id)
                ->orderBy('sensorTimestamp', 'desc')
                ->first(['sensorTimestamp']);

            $result[] = [
                'code' => $d->deviceCode,
                'name' => $d->deviceName,
                'status' => strtolower($d->status) === 'online' || strtolower($d->status) === 'active' ? 'active' : 'inactive',
                'lastData' => $lastData ? Carbon::parse($lastData->sensorTimestamp)->diffForHumans() : 'No Data',
                'protocol' => $d->protocolName ?? '-',
            ];
        }

        return $result;
    }

    /* SPK logic kept as dummy per request */
    public function getBarnSpkResult(array $barn): array
    {
        $results = [
            0 => ['status' => 'Excellent', 'color' => 'emerald', 'title' => 'Performa Optimal', 'description' => 'Lingkungan kandang dalam kondisi ideal. HDP tinggi di 94.5%, FCR efisien. Pertahankan manajemen pakan dan ventilasi saat ini.', 'score' => 92],
            1 => ['status' => 'Maintain', 'color' => 'blue', 'title' => 'Performa Baik — Tingkatkan', 'description' => 'Produksi masih dalam fase ramp-up. Kelembapan sedikit tinggi, pertimbangkan peningkatan sirkulasi udara untuk optimasi.', 'score' => 85],
            2 => ['status' => 'Growing', 'color' => 'purple', 'title' => 'Fase Pertumbuhan', 'description' => 'Flock masih dalam fase grower (12 minggu). Fokus pada kualitas pakan starter dan kontrol suhu untuk pertumbuhan optimal.', 'score' => 78],
            3 => ['status' => 'Monitor', 'color' => 'amber', 'title' => 'Perlu Perhatian Ventilasi', 'description' => 'Suhu 26°C mendekati batas atas. Ammonia 18ppm sudah moderate. Segera periksa sistem ventilasi dan kurangi kepadatan jika perlu.', 'score' => 72],
            4 => ['status' => 'Aging', 'color' => 'amber', 'title' => 'Pertimbangkan Afkir Bertahap', 'description' => 'Flock sudah 52 minggu. HDP turun ke 82.5% dengan FCR meningkat. Evaluasi titik impas untuk keputusan culling.', 'score' => 65],
            5 => ['status' => 'Alert', 'color' => 'red', 'title' => 'Suhu Kritis — Tindakan Segera', 'description' => 'Suhu kandang 27°C melebihi batas ideal. Ammonia 22ppm tinggi. Aktifkan ventilasi darurat dan monitor mortalitas.', 'score' => 52],
        ];
        $id = is_numeric($barn['id']) ? (int) $barn['id'] : 0;

        return $results[$id] ?? $results[0];
    }

    public function getBarnSpkMessages(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        $status = $barn['status'] ?? 'normal';
        $kpi = $this->getBarnKpi($barn);
        $hasTodayReport = $coopId && $coopId !== 'no-data'
            ? DB::table('laporan')
                ->where('unitBudidayaId', $coopId)
                ->where('isDeleted', 0)
                ->whereDate('createdAt', now()->toDateString())
                ->exists()
            : false;

        if (! $hasTodayReport) {
            return [
                ['mode' => 'Data Harian', 'status' => 'warning', 'message' => 'Belum ada laporan panen atau pakan hari ini. Hasil SPK produktivitas belum lengkap.'],
                ['mode' => 'Lingkungan', 'status' => $status === 'danger' ? 'danger' : ($status === 'warning' ? 'warning' : 'normal'), 'message' => $status === 'danger' ? 'Parameter lingkungan berada di zona kritis.' : ($status === 'warning' ? 'Parameter lingkungan perlu dipantau.' : 'Parameter lingkungan masih dalam batas aman.')],
            ];
        }

        $productivityStatus = 'normal';
        if (($kpi['hdp'] ?? 0) < 70 || ($kpi['fcr'] ?? 0) > 2.5) {
            $productivityStatus = 'warning';
        }

        return [
            ['mode' => 'Lingkungan', 'status' => $status === 'danger' ? 'danger' : ($status === 'warning' ? 'warning' : 'normal'), 'message' => $status === 'danger' ? 'Suhu dan amonia melebihi ambang batas! Aktifkan ventilasi darurat.' : ($status === 'warning' ? 'Parameter lingkungan mendekati batas atas. Periksa sirkulasi udara.' : 'Seluruh parameter lingkungan dalam kondisi ideal.')],
            ['mode' => 'Produktivitas', 'status' => $productivityStatus, 'message' => $productivityStatus === 'normal' ? 'HDP dan FCR hari ini berada dalam rentang aman.' : 'HDP atau FCR hari ini perlu ditinjau pada halaman Analisa SPK.'],
            ['mode' => 'Pakan', 'status' => ($kpi['feedIntake'] ?? 0) > 0 ? 'normal' : 'warning', 'message' => ($kpi['feedIntake'] ?? 0) > 0 ? 'Konsumsi pakan hari ini sudah tercatat.' : 'Data pakan hari ini belum tercatat.'],
            ['mode' => 'Kesehatan', 'status' => ($kpi['mortalitas'] ?? 0) > 3 ? 'warning' : 'normal', 'message' => ($kpi['mortalitas'] ?? 0) > 3 ? 'Mortalitas kumulatif meningkat, perlu pemeriksaan.' : 'Mortalitas masih dalam batas pemantauan normal.'],
        ];
    }

    public function getBarnDailyDataAudit(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        $today = now()->toDateString();

        if (! $coopId || $coopId === 'no-data') {
            return [
                'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
                'available' => [],
                'missing' => [],
                'actions' => [],
            ];
        }

        $hasPanen = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->exists();

        $hasFeed = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->exists();

        $hasGrade = DB::table('panenRincianGrade')
            ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->where('panenRincianGrade.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->exists();

        $hasMortality = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->exists();

        $hasEggMassColumn = DB::getSchemaBuilder()->hasColumn('panen', 'berat');
        $hasEggMass = $hasEggMassColumn && DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->whereNotNull('panen.berat')
            ->where('panen.berat', '>', 0)
            ->exists();
        $eggMassStatus = ! $hasPanen ? 'empty' : ($hasEggMass ? 'ready' : 'warning');

        return [
            'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
            'available' => [
                ['label' => 'Jumlah telur', 'status' => $hasPanen ? 'ready' : 'empty', 'source' => 'laporan + panen.jumlah'],
                ['label' => 'Berat telur / egg mass', 'status' => $eggMassStatus, 'source' => 'panen.berat'],
                ['label' => 'Konsumsi pakan', 'status' => $hasFeed ? 'ready' : 'empty', 'source' => 'harianTernak.pakan'],
                ['label' => 'Mortalitas', 'status' => $hasMortality ? 'ready' : 'empty', 'source' => 'laporan + kematian'],
                ['label' => 'Rincian grade', 'status' => $hasGrade ? 'ready' : 'empty', 'source' => 'panenRincianGrade + grade'],
            ],
            'missing' => [
                ['label' => 'Afkir ayam harian', 'source' => 'Belum tersedia pada laporan hari ini'],
            ],
            'actions' => [
                'Pastikan laporan panen mobile mengirim jumlah butir dan berat telur agar FCR serta SPK produktivitas bisa dihitung.',
                'Gunakan catatan panen dan grade telur sebagai acuan kualitas produksi harian.',
            ],
        ];
    }

    public function getBarnActivityLog(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        if (! $coopId || $coopId === 'no-data') {
            return [];
        }

        $activities = DB::table('laporan')
            ->where('unitBudidayaId', $coopId)
            ->where('isDeleted', 0)
            ->selectRaw("LOWER(COALESCE(tipe, 'harian')) as report_type, DATE(createdAt) as report_date, MAX(createdAt) as latest_at, COUNT(*) as report_count")
            ->groupBy('report_type', 'report_date')
            ->orderByDesc('latest_at')
            ->limit(5)
            ->get();

        $logs = [];
        foreach ($activities as $act) {
            $type = 'info';
            $reportType = strtolower((string) $act->report_type);
            if (in_array($reportType, ['panen', 'harian'], true)) {
                $type = 'success';
            }
            if (in_array($reportType, ['kematian', 'sakit', 'hama'], true)) {
                $type = 'warning';
            }

            $reportDate = Carbon::parse($act->report_date)->toDateString();
            $dateLabel = Carbon::parse($reportDate)->locale('id')->translatedFormat('d M Y');
            $desc = ((int) $act->report_count).' laporan tercatat pada '.$dateLabel.'.';

            if ($reportType === 'kematian') {
                $deathCount = $this->countMortalityForBarnDate($coopId, $reportDate);
                $desc = ($deathCount > 0 ? number_format($deathCount, 0, ',', '.') : (int) $act->report_count)
                    .' ayam mati tercatat pada '.$dateLabel.'.';
            } elseif ($reportType === 'panen') {
                $eggCount = DB::table('panen')
                    ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                    ->where('laporan.unitBudidayaId', $coopId)
                    ->where('laporan.isDeleted', 0)
                    ->where('panen.isDeleted', 0)
                    ->whereDate('laporan.createdAt', $reportDate)
                    ->sum('panen.jumlah');
                $desc = ($eggCount > 0 ? number_format((float) $eggCount, 0, ',', '.').' butir telur' : 'Panen')
                    .' tercatat pada '.$dateLabel.'.';
            } elseif ($reportType === 'harian') {
                $feedTotal = DB::table('harianTernak')
                    ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
                    ->where('laporan.unitBudidayaId', $coopId)
                    ->where('laporan.isDeleted', 0)
                    ->where('harianTernak.isDeleted', 0)
                    ->whereDate('laporan.createdAt', $reportDate)
                    ->sum('harianTernak.pakan');
                $desc = ($feedTotal > 0 ? number_format((float) $feedTotal, 1, ',', '.').' kg pakan' : 'Laporan harian')
                    .' tercatat pada '.$dateLabel.'.';
            }

            $logs[] = [
                'time' => Carbon::parse($act->latest_at)->diffForHumans(),
                'title' => 'Laporan '.ucfirst($reportType ?: 'harian'),
                'desc' => $desc,
                'type' => $type,
            ];
        }

        if (empty($logs)) {
            $logs[] = ['time' => '-', 'title' => 'Belum ada aktivitas', 'desc' => 'Tidak ada history laporan', 'type' => 'info'];
        }

        return $logs;
    }

    public function getProductivityTrend(?string $coopId = null): array
    {
        $activeCoopIds = $coopId && $coopId !== 'no-data'
            ? [$coopId]
            : $this->getActiveCoopIds(false);

        $labels = [];
        $hdp = [];
        $hhep = [];
        $fcr = [];
        $feedIntake = [];
        $mortality = [];

        if (empty($activeCoopIds)) {
            for ($i = 29; $i >= 0; $i--) {
                $labels[] = now()->subDays($i)->format('d/m');
                $hdp[] = 0;
                $hhep[] = 0;
                $fcr[] = 0;
                $feedIntake[] = 0;
                $mortality[] = 0;
            }

            return $this->productivityTrendPayload($labels, [
                'hdp' => $hdp,
                'hhep' => $hhep,
                'fcr' => $fcr,
                'feed_intake' => $feedIntake,
                'mortalitas' => $mortality,
            ]);
        }

        $startDate = now()->subDays(29)->toDateString();
        $endDate = now()->toDateString();

        $panens = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(panen.jumlah) as totalTelur, SUM(COALESCE(panen.berat, 0)) as totalMass')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $pakans = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(harianTernak.pakan) as totalPakan')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $matis = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, count(kematian.id) as totalMati')
            ->groupBy('dt')->get()->keyBy('dt')->toArray();

        $basePop = DB::table('unitBudidaya')->whereIn('id', $activeCoopIds)->sum('jumlah');

        for ($i = 29; $i >= 0; $i--) {
            $dt = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('d/m');

            $p = $panens[$dt] ?? null;
            $pk = $pakans[$dt] ?? null;
            $m = $matis[$dt] ?? null;

            $telur = $p ? $p->totalTelur : 0;
            $mass = $p ? $p->totalMass : 0;
            $pakan = $pk ? $pk->totalPakan : 0;
            $mati = $m ? $m->totalMati : 0;

            $_hdp = $basePop > 0 ? ($telur / $basePop) * 100 : 0;
            $_fcr = $mass > 0 ? $pakan / $mass : 0;
            $_fi = $basePop > 0 ? ($pakan / $basePop) * 1000 : 0;
            $_mortality = $basePop > 0 ? ($mati / $basePop) * 100 : 0;

            $hdp[] = round($_hdp, 1);
            $hhep[] = round($_hdp * 0.95, 1);
            $fcr[] = round($_fcr, 2);
            $feedIntake[] = round($_fi, 1);
            $mortality[] = round($_mortality, 2);
        }

        return $this->productivityTrendPayload($labels, [
            'hdp' => $hdp,
            'hhep' => $hhep,
            'fcr' => $fcr,
            'feed_intake' => $feedIntake,
            'mortalitas' => $mortality,
        ]);
    }

    private function productivityTrendPayload(array $labels, array $dataByCode): array
    {
        $seriesConfig = [
            'hdp' => ['label' => 'HDP (%)', 'short_label' => 'HDP', 'color' => '#10B981', 'axis' => 'y'],
            'hhep' => ['label' => 'HHEP (%)', 'short_label' => 'HHEP', 'color' => '#0EA5E9', 'axis' => 'y'],
            'fcr' => ['label' => 'FCR', 'short_label' => 'FCR', 'color' => '#64748B', 'axis' => 'y1'],
            'feed_intake' => ['label' => 'Feed Intake (g)', 'short_label' => 'Feed Intake', 'color' => '#F59E0B', 'axis' => 'y'],
            'mortalitas' => ['label' => 'Mortalitas (%)', 'short_label' => 'Mortalitas', 'color' => '#EF4444', 'axis' => 'y1'],
        ];

        $activeCodes = $this->activeProductivityFunctionCodes();

        $series = collect($seriesConfig)
            ->filter(fn ($config, string $code) => in_array($code, $activeCodes, true))
            ->map(fn (array $config, string $code) => [
                'code' => $code,
                'label' => $config['label'],
                'short_label' => $config['short_label'],
                'color' => $config['color'],
                'axis' => $config['axis'],
                'data' => $dataByCode[$code] ?? [],
            ])
            ->values()
            ->all();

        return [
            'labels' => $labels,
            'hdp' => $dataByCode['hdp'] ?? [],
            'hhep' => $dataByCode['hhep'] ?? [],
            'fcr' => $dataByCode['fcr'] ?? [],
            'feedIntake' => $dataByCode['feed_intake'] ?? [],
            'mortality' => $dataByCode['mortalitas'] ?? [],
            'series' => $series,
        ];
    }

    public function getBarnProductivityHistoryReport(array $barn, ?string $startDate = null, ?string $endDate = null): array
    {
        $coopId = $barn['id'] ?? null;
        $generatedAt = now();
        $empty = [
            'generated_at' => $generatedAt,
            'barn' => [
                'id' => $coopId,
                'name' => $barn['name'] ?? '-',
                'location' => $barn['location'] ?? '-',
                'breed' => $barn['breed'] ?? '-',
                'current_population' => 0,
                'initial_population' => 0,
                'capacity' => 0,
                'start_date' => '-',
                'flock_age' => '-',
            ],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'period_start' => '-',
                'period_end' => '-',
                'report_days' => 0,
                'total_reports' => 0,
                'total_eggs' => 0,
                'total_egg_mass_kg' => 0,
                'total_feed_kg' => 0,
                'total_mortality' => 0,
                'avg_hdp' => 0,
                'avg_hhep' => 0,
                'avg_feed_intake' => 0,
                'avg_fcr' => 0,
            ],
            'rows' => [],
            'warnings' => ['Belum ada data histori produktivitas untuk kandang ini.'],
        ];

        if (! $coopId || $coopId === 'no-data') {
            return $empty;
        }

        $coop = DB::table('unitBudidaya')
            ->leftJoin('jenisBudidaya', 'unitBudidaya.jenisBudidayaId', '=', 'jenisBudidaya.id')
            ->where('unitBudidaya.id', $coopId)
            ->where('unitBudidaya.isDeleted', 0)
            ->select(array_merge([
                'unitBudidaya.id',
                'unitBudidaya.nama',
                'unitBudidaya.lokasi',
                'unitBudidaya.jumlah',
                'unitBudidaya.kapasitas',
                'unitBudidaya.createdAt',
                'jenisBudidaya.nama as breedName',
            ], $this->unitBudidayaAgeSelect()))
            ->first();

        if (! $coop) {
            return $empty;
        }

        $reportQuery = DB::table('laporan')
            ->where('unitBudidayaId', $coopId)
            ->where('isDeleted', 0);

        if ($startDate) {
            $reportQuery->whereDate('createdAt', '>=', $startDate);
        }
        if ($endDate) {
            $reportQuery->whereDate('createdAt', '<=', $endDate);
        }

        $reportRows = $reportQuery
            ->orderBy('createdAt')
            ->get(['id', 'tipe', 'judul', 'catatan', 'createdAt']);

        $notesByDate = $reportRows
            ->groupBy(fn ($report) => Carbon::parse($report->createdAt)->toDateString())
            ->map(function ($items) {
                return $items
                    ->map(fn ($report) => trim((string) ($report->catatan ?: $report->judul ?: $report->tipe ?: '')))
                    ->filter()
                    ->unique()
                    ->take(2)
                    ->implode('; ');
            });

        $panenQuery = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0);

        if ($startDate) {
            $panenQuery->whereDate('laporan.createdAt', '>=', $startDate);
        }
        if ($endDate) {
            $panenQuery->whereDate('laporan.createdAt', '<=', $endDate);
        }

        $panens = $panenQuery
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(panen.jumlah) as totalTelur, SUM(COALESCE(panen.berat, 0)) as totalMass')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $feedQuery = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0);

        if ($startDate) {
            $feedQuery->whereDate('laporan.createdAt', '>=', $startDate);
        }
        if ($endDate) {
            $feedQuery->whereDate('laporan.createdAt', '<=', $endDate);
        }

        $feeds = $feedQuery
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(harianTernak.pakan) as totalPakan')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $deathQuery = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0);

        if ($startDate) {
            $deathQuery->whereDate('laporan.createdAt', '>=', $startDate);
        }
        if ($endDate) {
            $deathQuery->whereDate('laporan.createdAt', '<=', $endDate);
        }

        $deaths = $deathQuery
            ->selectRaw('DATE(laporan.createdAt) as dt, COUNT(kematian.id) as totalMati')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $dates = collect()
            ->merge($panens->keys())
            ->merge($feeds->keys())
            ->merge($deaths->keys())
            ->unique()
            ->sort()
            ->values();

        $currentPopulation = (float) ($coop->jumlah ?? 0);
        $totalDeaths = (float) $deaths->sum(fn ($item) => (float) ($item->totalMati ?? 0));
        $initialPopulation = $currentPopulation + $totalDeaths;
        $cumulativeDeaths = 0.0;
        $rows = [];

        foreach ($dates as $dt) {
            $panen = $panens->get($dt);
            $feed = $feeds->get($dt);
            $death = $deaths->get($dt);

            $totalEggs = (float) ($panen->totalTelur ?? 0);
            $eggMassKg = (float) ($panen->totalMass ?? 0);
            $feedKg = (float) ($feed->totalPakan ?? 0);
            $deathCount = (float) ($death->totalMati ?? 0);
            $populationAtDate = max($initialPopulation - $cumulativeDeaths, $currentPopulation, 0);

            $hdp = $populationAtDate > 0 ? ($totalEggs / $populationAtDate) * 100 : 0;
            $hhep = $initialPopulation > 0 ? ($totalEggs / $initialPopulation) * 100 : 0;
            $feedIntake = $populationAtDate > 0 ? ($feedKg / $populationAtDate) * 1000 : 0;
            $fcr = $eggMassKg > 0 ? $feedKg / $eggMassKg : 0;
            $mortalityRate = $populationAtDate > 0 ? ($deathCount / $populationAtDate) * 100 : 0;

            $rows[] = [
                'date_iso' => $dt,
                'date_label' => Carbon::parse($dt)->locale('id')->translatedFormat('d M Y'),
                'eggs' => round($totalEggs),
                'egg_mass_kg' => round($eggMassKg, 2),
                'feed_kg' => round($feedKg, 2),
                'feed_intake' => round($feedIntake, 1),
                'mortality' => round($deathCount),
                'mortality_rate' => round($mortalityRate, 2),
                'hdp' => round($hdp, 1),
                'hhep' => round($hhep, 1),
                'fcr' => round($fcr, 2),
                'note' => $notesByDate->get($dt, ''),
            ];

            $cumulativeDeaths += $deathCount;
        }

        $rowCollection = collect($rows);
        $activeRows = $rowCollection->filter(fn ($row) => ($row['eggs'] ?? 0) > 0 || ($row['feed_kg'] ?? 0) > 0);
        $avgRows = $activeRows->isNotEmpty() ? $activeRows : $rowCollection;
        $warnings = [];

        if ($rowCollection->isEmpty()) {
            $warnings[] = 'Belum ada data panen, pakan, atau mortalitas historis untuk kandang ini.';
        }
        if ($currentPopulation <= 0) {
            $warnings[] = 'Populasi kandang belum tersedia, sehingga HDP, HHEP, dan feed intake tidak bisa dihitung akurat.';
        }
        if ($rowCollection->contains(fn ($row) => ($row['hdp'] ?? 0) > 120)) {
            $warnings[] = 'Ada nilai HDP di atas 120%. Periksa kesesuaian jumlah panen dan populasi kandang.';
        }
        if ($rowCollection->sum('feed_kg') <= 0 && $rowCollection->sum('eggs') > 0) {
            $warnings[] = 'Data panen tersedia, tetapi konsumsi pakan belum tercatat pada histori yang sama.';
        }

        $createdAt = $coop->createdAt ? Carbon::parse($coop->createdAt) : null;
        $firstRow = $rowCollection->first();
        $lastRow = $rowCollection->last();

        return [
            'generated_at' => $generatedAt,
            'barn' => [
                'id' => $coop->id,
                'name' => $coop->nama ?? ($barn['name'] ?? '-'),
                'location' => $coop->lokasi ?? '-',
                'breed' => $coop->breedName ?? '-',
                'current_population' => $currentPopulation,
                'initial_population' => $initialPopulation,
                'capacity' => (float) ($coop->kapasitas ?? 0),
                'start_date' => $createdAt ? $createdAt->locale('id')->translatedFormat('d M Y') : '-',
                'flock_age' => $this->flockAgeWeeks($coop).' Minggu',
            ],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => [
                'period_start' => $firstRow['date_label'] ?? '-',
                'period_end' => $lastRow['date_label'] ?? '-',
                'report_days' => $rowCollection->count(),
                'total_reports' => $reportRows->count(),
                'total_eggs' => round($rowCollection->sum('eggs')),
                'total_egg_mass_kg' => round($rowCollection->sum('egg_mass_kg'), 2),
                'total_feed_kg' => round($rowCollection->sum('feed_kg'), 2),
                'total_mortality' => round($rowCollection->sum('mortality')),
                'avg_hdp' => round((float) $avgRows->avg('hdp'), 1),
                'avg_hhep' => round((float) $avgRows->avg('hhep'), 1),
                'avg_feed_intake' => round((float) $avgRows->avg('feed_intake'), 1),
                'avg_fcr' => round((float) $avgRows->filter(fn ($row) => ($row['fcr'] ?? 0) > 0)->avg('fcr'), 2),
            ],
            'rows' => $rows,
            'warnings' => $warnings,
        ];
    }

    public function getEggQuality(array $barn): array
    {
        $coopId = $barn['id'] ?? null;
        $today = now()->toDateString();
        $empty = [
            'hasReport' => false,
            'hasGradeDetail' => false,
            'sourceDate' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
            'lastPanenAt' => null,
            'totalEggs' => 0,
            'totalWeightKg' => 0,
            'avgWeightGram' => null,
            'gradeDistribution' => [],
            'rejectRate' => null,
            'rejectStatus' => 'missing',
            'missingFields' => [],
        ];

        if (! $coopId || $coopId === 'no-data') {
            return $empty;
        }

        $lastPanenAt = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->max('laporan.createdAt');

        $panens = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->get([
                'panen.id',
                'panen.jumlah',
                'panen.berat',
            ]);

        if ($panens->isEmpty()) {
            return array_merge($empty, [
                'lastPanenAt' => $lastPanenAt
                    ? Carbon::parse($lastPanenAt)->locale('id')->translatedFormat('d M Y, H:i')
                    : null,
            ]);
        }

        $panenIds = $panens->pluck('id')->all();
        $totalEggs = (float) $panens->sum('jumlah');
        $totalWeightKg = (float) $panens->sum(fn ($p) => (float) ($p->berat ?? 0));

        $hasGradeWeight = DB::getSchemaBuilder()->hasColumn('panenRincianGrade', 'berat');
        $gradeSelect = 'grade.nama as grade_name, SUM(COALESCE(panenRincianGrade.jumlah, 0)) as total_jumlah';
        if ($hasGradeWeight) {
            $gradeSelect .= ', SUM(COALESCE(panenRincianGrade.berat, 0)) as total_berat';
        }

        $gradeRows = DB::table('panenRincianGrade')
            ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
            ->whereIn('panenRincianGrade.panenId', $panenIds)
            ->where('panenRincianGrade.isDeleted', 0)
            ->where('grade.isDeleted', 0)
            ->selectRaw($gradeSelect)
            ->groupBy('grade.nama')
            ->get()
            ->keyBy('grade_name');

        $gradeColors = [
            'Grade AA' => 'bg-emerald-900',
            'Grade A' => 'bg-emerald-600',
            'Grade B' => 'bg-sky-500',
            'Grade C' => 'bg-amber-500',
            'Afkir' => 'bg-red-500',
            'Rusak' => 'bg-red-500',
            'Retak' => 'bg-orange-500',
            'Kotor' => 'bg-rose-500',
            'Pecah' => 'bg-red-600',
            'Reject' => 'bg-red-500',
        ];
        $baseOrder = ['Grade AA', 'Grade A', 'Grade B', 'Grade C'];
        $rejectGrades = $gradeRows->keys()
            ->filter(fn ($name) => $this->isRejectEggGradeName((string) $name))
            ->values()
            ->all();
        $otherGrades = $gradeRows->keys()
            ->reject(fn ($name) => in_array((string) $name, $baseOrder, true) || $this->isRejectEggGradeName((string) $name))
            ->values()
            ->all();
        $orderedGrades = collect($baseOrder)
            ->merge($rejectGrades)
            ->merge($otherGrades)
            ->unique()
            ->values()
            ->all();
        $gradeJumlahTotal = (float) $gradeRows->sum(fn ($row) => (float) ($row->total_jumlah ?? 0));
        $gradeBeratTotal = $hasGradeWeight
            ? (float) $gradeRows->sum(fn ($row) => (float) ($row->total_berat ?? 0))
            : 0.0;
        $useWeightDistribution = $gradeJumlahTotal <= 0 && $gradeBeratTotal > 0;
        $denominator = $useWeightDistribution
            ? max($gradeBeratTotal, $totalWeightKg, 1)
            : max($gradeJumlahTotal, $totalEggs, 1);
        $distribution = [];

        foreach ($orderedGrades as $gradeName) {
            $gradeRow = $gradeRows->get($gradeName);
            $amount = (float) (
                $useWeightDistribution
                    ? ($gradeRow->total_berat ?? 0)
                    : ($gradeRow->total_jumlah ?? 0)
            );

            if ($amount <= 0) {
                continue;
            }

            $distribution[] = [
                'label' => $gradeName,
                'count' => $amount,
                'unit' => $useWeightDistribution ? 'kg' : 'butir',
                'pct' => round(($amount / $denominator) * 100, 1),
                'color' => $gradeColors[$gradeName] ?? ($this->isRejectEggGradeName($gradeName) ? 'bg-red-500' : 'bg-gray-500'),
                'isReject' => $this->isRejectEggGradeName($gradeName),
            ];
        }

        $rejectAmount = (float) $gradeRows
            ->filter(fn ($row, $name) => $this->isRejectEggGradeName((string) $name))
            ->sum(fn ($row) => (float) (
                $useWeightDistribution
                    ? ($row->total_berat ?? 0)
                    : ($row->total_jumlah ?? 0)
            ));
        $rejectDenominator = $useWeightDistribution ? $totalWeightKg : $totalEggs;
        $rejectRate = $rejectDenominator > 0 ? round(($rejectAmount / $rejectDenominator) * 100, 2) : null;

        return array_merge($empty, [
            'hasReport' => true,
            'hasGradeDetail' => ! empty($distribution),
            'lastPanenAt' => $lastPanenAt
                ? Carbon::parse($lastPanenAt)->locale('id')->translatedFormat('d M Y, H:i')
                : null,
            'totalEggs' => $totalEggs,
            'totalWeightKg' => round($totalWeightKg, 2),
            'avgWeightGram' => $totalEggs > 0 ? round(($totalWeightKg * 1000) / $totalEggs, 1) : null,
            'gradeDistribution' => $distribution,
            'rejectCount' => $rejectAmount,
            'rejectUnit' => $useWeightDistribution ? 'kg' : 'butir',
            'rejectRate' => $rejectRate,
            'rejectStatus' => $rejectRate !== null && $rejectRate <= 5 ? 'normal' : 'warning',
        ]);
    }

    public function getKpiMetrics(): array
    {
        if (empty($this->activeProductivityFunctionCodes())) {
            return [];
        }

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        $currentMonth = now()->startOfMonth()->toDateString();
        $lastMonthStart = now()->subMonth()->startOfMonth()->toDateString();
        $lastMonthEnd = now()->subMonth()->endOfMonth()->toDateString();

        $activeCoopIds = $this->getActiveCoopIds();
        $totalAyamHidup = empty($activeCoopIds)
            ? 0
            : (float) DB::table('unitBudidaya')->whereIn('id', $activeCoopIds)->sum('jumlah');

        if (empty($activeCoopIds) || $totalAyamHidup <= 0) {
            return $this->filterProductivityCardsByMaster([
                ['code' => 'hdp', 'label' => 'HDP %', 'value' => '0%', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['code' => 'hhep', 'label' => 'HHEP %', 'value' => '0%', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['code' => 'fcr', 'label' => 'FCR', 'value' => '0', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['code' => 'flock_age', 'label' => 'Umur Biologis', 'value' => '0 Mgg', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['code' => 'feed_intake', 'label' => 'Feed Intake', 'value' => '0g', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['code' => 'egg_mass', 'label' => 'Egg Mass', 'value' => '0kg', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['code' => 'avg_egg_weight', 'label' => 'Berat Rata-rata', 'value' => '0g', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
                ['code' => 'mortalitas', 'label' => 'Mortality', 'value' => '0%', 'trend' => ['direction' => 'stable', 'value' => 'No data', 'status' => 'neutral']],
            ]);
        }

        $panenToday = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as totalTelur, COALESCE(SUM(COALESCE(panen.berat, 0)), 0) as totalEggMass')
            ->first();

        $totalTelurToday = (float) ($panenToday->totalTelur ?? 0);
        $totalEggMassToday = (float) ($panenToday->totalEggMass ?? 0);

        $panenYesterday = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', $yesterday)
            ->selectRaw('COALESCE(SUM(panen.jumlah), 0) as totalTelur, COALESCE(SUM(COALESCE(panen.berat, 0)), 0) as totalEggMass')
            ->first();

        $totalTelurYesterday = (float) ($panenYesterday->totalTelur ?? 0);
        $totalEggMassYesterday = (float) ($panenYesterday->totalEggMass ?? 0);

        $pakanToday = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $today)
            ->sum('harianTernak.pakan');

        $pakanYesterday = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', $yesterday)
            ->sum('harianTernak.pakan');

        $hdpToday = $totalAyamHidup > 0 ? round(($totalTelurToday / $totalAyamHidup) * 100, 1) : 0;
        $hdpYesterday = $totalAyamHidup > 0 ? round(($totalTelurYesterday / $totalAyamHidup) * 100, 1) : 0;
        $avgEggWeightToday = $totalTelurToday > 0 ? round(($totalEggMassToday * 1000) / $totalTelurToday, 1) : 0;
        $avgEggWeightYesterday = $totalTelurYesterday > 0 ? round(($totalEggMassYesterday * 1000) / $totalTelurYesterday, 1) : 0;

        $feedIntakeToday = $totalAyamHidup > 0 ? round(($pakanToday / $totalAyamHidup) * 1000, 0) : 0;
        $feedIntakeYesterday = $totalAyamHidup > 0 ? round(($pakanYesterday / $totalAyamHidup) * 1000, 0) : 0;

        $fcrToday = $totalEggMassToday > 0 ? round($pakanToday / $totalEggMassToday, 2) : 0;
        $fcrYesterday = $totalEggMassYesterday > 0 ? round($pakanYesterday / $totalEggMassYesterday, 2) : 0;

        $mortalityThisMonth = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $currentMonth)
            ->count();

        $mortalityLastMonth = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $lastMonthStart)
            ->whereDate('laporan.createdAt', '<=', $lastMonthEnd)
            ->count();

        $totalDeathsAllTime = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoopIds)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();

        $populasiAwal = $totalAyamHidup + $totalDeathsAllTime;
        $hhepToday = $populasiAwal > 0 ? round(($totalTelurToday / $populasiAwal) * 100, 1) : 0;
        $hhepYesterday = $populasiAwal > 0 ? round(($totalTelurYesterday / $populasiAwal) * 100, 1) : 0;
        $populasiAwalBulan = $totalAyamHidup + $mortalityThisMonth;
        $mortalityPct = $populasiAwalBulan > 0 ? round(($mortalityThisMonth / $populasiAwalBulan) * 100, 2) : 0;

        $oldestCoop = DB::table('unitBudidaya')
            ->whereIn('id', $activeCoopIds)
            ->orderBy('createdAt', 'asc')
            ->first(array_merge(['createdAt'], $this->unitBudidayaAgeSelect('')));
        $umurBiologis = $oldestCoop ? $this->flockAgeWeeks($oldestCoop).' Mgg' : '0 Mgg';

        return $this->filterProductivityCardsByMaster([
            [
                'code' => 'hdp',
                'label' => 'HDP %',
                'value' => $hdpToday.'%',
                'trend' => $this->calcTrend($hdpToday, $hdpYesterday, 'higher_is_better'),
            ],
            [
                'code' => 'hhep',
                'label' => 'HHEP %',
                'value' => $hhepToday.'%',
                'trend' => $this->calcTrend($hhepToday, $hhepYesterday, 'higher_is_better'),
            ],
            [
                'code' => 'fcr',
                'label' => 'FCR',
                'value' => $fcrToday > 0 ? (string) $fcrToday : '0',
                'trend' => $this->calcTrend($fcrToday, $fcrYesterday, 'lower_is_better'),
            ],
            [
                'code' => 'flock_age',
                'label' => 'Umur Biologis',
                'value' => $umurBiologis,
                'trend' => ['direction' => 'stable', 'value' => 'Fase Produksi', 'status' => 'neutral'],
            ],
            [
                'code' => 'feed_intake',
                'label' => 'Feed Intake',
                'value' => $feedIntakeToday.'g',
                'trend' => $this->calcTrend($feedIntakeToday, $feedIntakeYesterday, 'neutral'),
            ],
            [
                'code' => 'egg_mass',
                'label' => 'Egg Mass',
                'value' => round($totalEggMassToday, 1).'kg',
                'trend' => $this->calcTrend($totalEggMassToday, $totalEggMassYesterday, 'higher_is_better'),
            ],
            [
                'code' => 'avg_egg_weight',
                'label' => 'Berat Rata-rata',
                'value' => $avgEggWeightToday > 0 ? $avgEggWeightToday.'g' : '0g',
                'trend' => $this->calcTrend($avgEggWeightToday, $avgEggWeightYesterday, 'neutral'),
            ],
            [
                'code' => 'mortalitas',
                'label' => 'Mortality',
                'value' => $mortalityPct.'%',
                'trend' => $this->calcMortalityTrend($mortalityThisMonth, $mortalityLastMonth),
            ],
        ]);
    }

    private function calcTrend(float $current, float $previous, string $mode): array
    {
        if ($previous == 0 && $current == 0) {
            return ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral'];
        }

        $diff = round($current - $previous, 2);

        if (abs($diff) < 0.01) {
            return ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral'];
        }

        $direction = $diff > 0 ? 'up' : 'down';
        $absVal = abs($diff);

        if ($mode === 'higher_is_better') {
            $status = $diff > 0 ? 'positive' : 'warning';
        } elseif ($mode === 'lower_is_better') {
            $status = $diff < 0 ? 'positive' : 'warning';
        } else {
            $status = abs($diff) > 10 ? 'warning' : 'neutral';
        }

        return ['direction' => $direction, 'value' => (string) $absVal, 'status' => $status];
    }

    private function calcMortalityTrend(int $thisMonth, int $lastMonth): array
    {
        $diff = $thisMonth - $lastMonth;

        if ($diff === 0) {
            return ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral'];
        }

        return [
            'direction' => $diff > 0 ? 'up' : 'down',
            'value' => abs($diff).' ekor',
            'status' => $diff > 0 ? 'warning' : 'positive',
        ];
    }

    public function getChartData(string $range = '30d'): array
    {
        $activeCoops = $this->getActiveCoopIds(false);
        $pop = empty($activeCoops)
            ? 0
            : (float) DB::table('unitBudidaya')->whereIn('id', $activeCoops)->sum('jumlah');

        [$startDate, $labelFormat, $stepDays] = match ($range) {
            '90d' => [now()->subDays(89)->toDateString(), 'd/m', 1],
            'ytd' => [now()->startOfYear()->toDateString(), 'd/m', 1],
            default => [now()->subDays(29)->toDateString(), 'd/m', 1],
        };

        $endDate = now()->toDateString();
        $labels = [];
        $hdpArr = [];
        $fcrArr = [];

        if (empty($activeCoops) || $pop <= 0) {
            $days = max(1, Carbon::parse($startDate)->diffInDays($endDate) + 1);
            for ($i = $days - 1; $i >= 0; $i -= $stepDays) {
                $dt = Carbon::parse($endDate)->subDays($i);
                $labels[] = $dt->format($labelFormat);
                $hdpArr[] = 0;
                $fcrArr[] = 0;
            }

            return ['labels' => $labels, 'hdp' => $hdpArr, 'fcr' => $fcrArr, 'range' => $range];
        }

        $panens = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(panen.jumlah) as tTelur, SUM(COALESCE(panen.berat, 0)) as tMass')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $pakans = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate)
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(harianTernak.pakan) as totalPakan')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');

        $cursor = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        while ($cursor->lte($end)) {
            $dt = $cursor->toDateString();
            $labels[] = $cursor->format($labelFormat);

            $p = $panens[$dt] ?? null;
            $pk = $pakans[$dt] ?? null;
            $telur = $p ? (float) $p->tTelur : 0;
            $mass = $p ? (float) $p->tMass : 0;
            $pakan = $pk ? (float) $pk->totalPakan : 0;

            $hdpArr[] = $pop > 0 ? round(($telur / $pop) * 100, 1) : 0;
            $fcrArr[] = $mass > 0 ? round($pakan / $mass, 2) : 0;

            $cursor->addDays($stepDays);
        }

        return [
            'labels' => $labels,
            'hdp' => $hdpArr,
            'fcr' => $fcrArr,
            'range' => $range,
        ];
    }

    public function getChartDataByRange(): array
    {
        return [
            '30d' => $this->getChartData('30d'),
            '90d' => $this->getChartData('90d'),
            'ytd' => $this->getChartData('ytd'),
        ];
    }

    public function getBarnEnvironment(): array
    {
        if ($this->cachedBarnEnvironment !== null) {
            return $this->cachedBarnEnvironment;
        }

        $thresholds = $this->getCommodityThresholds();
        $environmentParams = $this->livestockMasterConfigService
            ->configuredEnvironmentParametersForCommodity(
                $this->activeKomoditasId,
                false
            );

        $activeCoops = $this->activeJenisBudidayaId
            ? DB::table('unitBudidaya')
                ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
                ->where('status', 1)
                ->where('isDeleted', 0)
                ->get()
            : collect();

        $barns = [];
        foreach ($activeCoops as $coop) {
            $devices = DB::table('iot_device')
                ->where('unitBudidayaId', $coop->id)
                ->pluck('id')->toArray();

            [$mapped, $sensorSources] = $this->environmentSensorContextForBarn(
                (string) $coop->id,
                $devices,
                $environmentParams
            );

            $temp = $this->sensorValueFromMap($mapped, 'TEMP');
            $hum = $this->sensorValueFromMap($mapped, 'HUMID');
            $ammo = $this->sensorValueFromMap($mapped, 'AMMON');
            $lux = $this->sensorValueFromMap($mapped, 'LIGHT');

            $tempThr = $this->thresholdFor($thresholds, 'TEMP');
            $humThr = $this->thresholdFor($thresholds, 'HUMID');
            $ammoThr = $this->thresholdFor($thresholds, 'AMMON');
            $luxThr = $this->thresholdFor($thresholds, 'LUX');

            $tempStatus = $this->evaluateSensorStatus($temp, $tempThr['min'], $tempThr['max']);
            $humStatus = $this->evaluateSensorStatus($hum, $humThr['min'], $humThr['max']);
            $ammoStatus = $this->evaluateSensorStatus($ammo, $ammoThr['min'], $ammoThr['max']);
            $luxStatus = $this->evaluateSensorStatus($lux, $luxThr['min'], $luxThr['max']);
            $status = $this->worstStatus($tempStatus, $humStatus, $ammoStatus, $luxStatus);

            $statusLabels = [
                'normal' => 'Normal',
                'warning' => 'Perhatian',
                'danger' => 'Kritis',
            ];

            $ammoMax = $ammoThr['max'] ?? 15;
            $configuredEnvironment = $this->configuredEnvironmentForBarn(
                $environmentParams,
                $thresholds,
                $mapped,
                $statusLabels,
                $sensorSources
            );

            $barns[] = [
                'id' => $coop->id,
                'name' => $coop->nama,
                'temp' => round($temp, 1),
                'status' => $status,
                'sensors' => [
                    ['label' => 'Temperature ('.round($temp, 1).'°C)', 'percent' => $temp > 0 ? min(($temp / max($tempThr['max'] ?? 40, 1)) * 100, 100) : 0, 'status' => $tempStatus, 'statusLabel' => $statusLabels[$tempStatus]],
                    ['label' => 'Humidity ('.round($hum, 1).'%)', 'percent' => min($hum, 100), 'status' => $humStatus, 'statusLabel' => $statusLabels[$humStatus]],
                    ['label' => 'Ammonia ('.round($ammo, 1).'ppm)', 'percent' => min($ammo * 2, 100), 'status' => $ammoStatus, 'statusLabel' => $statusLabels[$ammoStatus]],
                    ['label' => 'Light ('.round($lux, 1).' lx)', 'percent' => min($lux, 100), 'status' => $luxStatus, 'statusLabel' => $statusLabels[$luxStatus]],
                ],
                'summary' => [
                    'avg_temp' => round($temp, 1).'°C',
                    'humidity' => round($hum, 1).'%',
                    'ammonia' => round($ammo, 1).'ppm',
                    'ammonia_ok' => $ammoStatus === 'normal',
                    'lux' => round($lux, 1).' lx',
                    'temp_status' => $tempStatus,
                    'humidity_status' => $humStatus,
                    'ammonia_status' => $ammoStatus,
                    'lux_status' => $luxStatus,
                ],
            ];

            $lastBarnIndex = array_key_last($barns);
            if ($lastBarnIndex !== null && ($configuredEnvironment['applies'] ?? false)) {
                $barns[$lastBarnIndex]['temp'] = $configuredEnvironment['temp'];
                $barns[$lastBarnIndex]['display_sensor_label'] = $configuredEnvironment['display_sensor_label'];
                $barns[$lastBarnIndex]['display_sensor_value'] = $configuredEnvironment['display_sensor_value'];
                $barns[$lastBarnIndex]['status'] = $configuredEnvironment['status'];
                $barns[$lastBarnIndex]['sensors'] = $configuredEnvironment['sensors'];
                $barns[$lastBarnIndex]['summary'] = $configuredEnvironment['summary'];
            }
        }

        if (empty($barns)) {
            $barns[] = [
                'id' => 'no-data',
                'name' => 'Belum ada Kandang',
                'temp' => '-',
                'display_sensor_label' => 'Sensor',
                'display_sensor_value' => '-',
                'status' => 'normal',
                'sensors' => [],
                'summary' => ['avg_temp' => '-', 'humidity' => '-', 'ammonia' => '-', 'ammonia_ok' => true, 'lux' => '-', 'parameters' => []],
            ];
        }

        $this->cachedBarnEnvironment = ['barns' => $barns];

        return $this->cachedBarnEnvironment;
    }

    public function getProduktivitasData(?string $coopId = null): array
    {
        if (empty($this->activeProductivityFunctionCodes())) {
            return $this->configuredProductivityPayload([], []);
        }

        $activeCoops = $coopId ? [$coopId] : $this->getActiveCoopIds(false);
        $coopSum = empty($activeCoops)
            ? 0
            : (float) DB::table('unitBudidaya')->whereIn('id', $activeCoops)->sum('jumlah');

        if ($coopSum <= 0) {
            return $this->configuredProductivityPayload([
                ['code' => 'hdp', 'label' => 'HDP', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ['code' => 'hhep', 'label' => 'HHEP', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ['code' => 'fcr', 'label' => 'FCR', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ['code' => 'flock_age', 'label' => 'Umur Biologis', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ['code' => 'feed_intake', 'label' => 'Feed Consumption', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ['code' => 'egg_mass', 'label' => 'Egg Mass', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ['code' => 'avg_egg_weight', 'label' => 'Berat Rata-rata', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
                ['code' => 'mortalitas', 'label' => 'Mortalitas', 'value' => '-', 'color' => 'neutral', 'detail' => '-', 'score' => 0],
            ], []);
        }

        $panenQuery = DB::table('panen')->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->whereDate('laporan.createdAt', now()->toDateString());

        $panen = $panenQuery->selectRaw('SUM(panen.jumlah) as tTelur, SUM(COALESCE(panen.berat, 0)) as tMass')->first();

        $totalTelur = (float) ($panen->tTelur ?? 0);
        $hdp = $coopSum > 0 ? $totalTelur / $coopSum * 100 : 0;

        $pakan = DB::table('harianTernak')->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->whereDate('laporan.createdAt', now()->toDateString())
            ->sum('harianTernak.pakan');

        $fi = $coopSum > 0 ? ($pakan / $coopSum) * 1000 : 0;
        $eggMass = (float) ($panen->tMass ?? 0);
        $fcr = $eggMass > 0 ? $pakan / $eggMass : 0;
        $avgEggWeight = $totalTelur > 0 ? ($eggMass * 1000) / $totalTelur : 0;

        $mati = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->whereDate('laporan.createdAt', '>=', now()->startOfMonth()->toDateString())
            ->count();
        $mortality = $coopSum > 0 ? ($mati / $coopSum) * 100 : 0;

        $totalMati = DB::table('kematian')->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $activeCoops)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();
        $initialPopulation = $coopSum + $totalMati;
        $hhep = $initialPopulation > 0 ? ($totalTelur / $initialPopulation) * 100 : 0;

        $avgWeeks = 0;
        if (! empty($activeCoops)) {
            $coops = DB::table('unitBudidaya')
                ->whereIn('id', $activeCoops)
                ->get(array_merge(['createdAt'], $this->unitBudidayaAgeSelect('')));
            $weeks = $coops->map(fn ($c) => $this->flockAgeWeeks($c));
            $avgWeeks = $weeks->isEmpty() ? 0 : (int) round($weeks->avg());
        }

        $hdpScore = min(100, max(0, round($hdp)));
        $hhepScore = min(100, max(0, round($hhep)));
        $ageScore = min(100, max(0, $avgWeeks * 2));
        $feedScore = min(100, max(0, round($fi > 120 ? 100 : ($fi / 1.2))));
        $fcrScore = $fcr > 0 ? min(100, max(0, round(100 - max(0, $fcr - 2) * 30))) : 0;
        $eggMassScore = min(100, max(0, round($eggMass)));
        $avgWeightScore = $avgEggWeight > 0 ? min(100, max(0, round(100 - (abs($avgEggWeight - 60) * 5)))) : 0;
        $mortScore = min(100, max(0, round(100 - ($mortality * 10))));

        return $this->configuredProductivityPayload([
            ['code' => 'hdp', 'label' => 'HDP', 'value' => round($hdp, 1).'%', 'color' => $hdp > 85 ? 'emerald' : ($hdp > 70 ? 'amber' : 'red'), 'detail' => $hdp > 85 ? 'Optimal' : ($hdp > 70 ? 'Cukup' : 'Rendah'), 'score' => $hdpScore],
            ['code' => 'hhep', 'label' => 'HHEP', 'value' => round($hhep, 1).'%', 'color' => $hhep > 85 ? 'emerald' : ($hhep > 70 ? 'amber' : 'red'), 'detail' => 'Telur / populasi awal', 'score' => $hhepScore],
            ['code' => 'fcr', 'label' => 'FCR', 'value' => $fcr > 0 ? round($fcr, 2) : '-', 'color' => $fcr > 0 && $fcr <= 2.2 ? 'emerald' : ($fcr <= 2.6 ? 'amber' : 'red'), 'detail' => 'Pakan / egg mass', 'score' => $fcrScore],
            ['code' => 'flock_age', 'label' => 'Umur Biologis', 'value' => $avgWeeks > 0 ? $avgWeeks.' minggu' : '-', 'color' => $avgWeeks >= 18 && $avgWeeks <= 90 ? 'emerald' : 'amber', 'detail' => 'Rata-rata kandang', 'score' => $ageScore],
            ['code' => 'feed_intake', 'label' => 'Feed Consumption', 'value' => round($fi, 0).' g', 'color' => $fi >= 100 && $fi <= 130 ? 'emerald' : 'amber', 'detail' => 'Per ekor/hari', 'score' => $feedScore],
            ['code' => 'egg_mass', 'label' => 'Egg Mass', 'value' => $eggMass > 0 ? round($eggMass, 1).' kg' : '-', 'color' => $eggMass > 0 ? 'emerald' : 'neutral', 'detail' => 'Berat panen hari ini', 'score' => $eggMassScore],
            ['code' => 'avg_egg_weight', 'label' => 'Berat Rata-rata', 'value' => $avgEggWeight > 0 ? round($avgEggWeight, 1).' g' : '-', 'color' => $avgWeightScore >= 80 ? 'emerald' : ($avgWeightScore >= 55 ? 'amber' : 'red'), 'detail' => 'Berat / butir', 'score' => $avgWeightScore],
            ['code' => 'mortalitas', 'label' => 'Mortalitas', 'value' => round($mortality, 2).'%', 'color' => $mortality < 1 ? 'emerald' : ($mortality < 3 ? 'amber' : 'red'), 'detail' => 'Bulan ini', 'score' => $mortScore],
        ], [
            ['code' => 'hdp', 'label' => 'HDP (Hen-Day)', 'percent' => $hdpScore, 'status' => $hdp >= 85 ? 'normal' : ($hdp >= 70 ? 'warning' : 'danger'), 'statusLabel' => round($hdp, 1).'%'],
            ['code' => 'hhep', 'label' => 'HHEP (Hen-Housed)', 'percent' => $hhepScore, 'status' => $hhep >= 85 ? 'normal' : ($hhep >= 70 ? 'warning' : 'danger'), 'statusLabel' => round($hhep, 1).'%'],
            ['code' => 'fcr', 'label' => 'FCR', 'percent' => $fcrScore, 'status' => $fcr > 0 && $fcr <= 2.2 ? 'normal' : ($fcr <= 2.6 ? 'warning' : 'danger'), 'statusLabel' => $fcr > 0 ? (string) round($fcr, 2) : '-'],
            ['code' => 'flock_age', 'label' => 'Umur Biologis', 'percent' => $ageScore, 'status' => $avgWeeks >= 18 && $avgWeeks <= 90 ? 'normal' : 'warning', 'statusLabel' => $avgWeeks > 0 ? $avgWeeks.' minggu' : '-'],
            ['code' => 'feed_intake', 'label' => 'Feed Consumption', 'percent' => $feedScore, 'status' => $fi >= 100 && $fi <= 130 ? 'normal' : 'warning', 'statusLabel' => round($fi, 0).' g/ekor'],
            ['code' => 'egg_mass', 'label' => 'Egg Mass', 'percent' => $eggMassScore, 'status' => $eggMass > 0 ? 'normal' : 'warning', 'statusLabel' => $eggMass > 0 ? round($eggMass, 1).' kg' : '-'],
            ['code' => 'avg_egg_weight', 'label' => 'Berat Rata-rata', 'percent' => $avgWeightScore, 'status' => $avgWeightScore >= 80 ? 'normal' : ($avgWeightScore >= 55 ? 'warning' : 'danger'), 'statusLabel' => $avgEggWeight > 0 ? round($avgEggWeight, 1).' g' : '-'],
            ['code' => 'mortalitas', 'label' => 'Mortalitas', 'percent' => $mortScore, 'status' => $mortality < 1 ? 'normal' : ($mortality < 3 ? 'warning' : 'danger'), 'statusLabel' => round($mortality, 2).'%'],
        ]);
    }

    private function configuredProductivityPayload(array $indicators, array $sensors): array
    {
        $indicators = $this->filterProductivityCardsByMaster($indicators);
        $sensors = $this->filterProductivityCardsByMaster($sensors);

        return [
            'spider' => [
                'labels' => array_values(array_map(fn (array $item) => $item['label'] ?? '-', $indicators)),
                'values' => array_values(array_map(fn (array $item) => (int) ($item['score'] ?? $item['percent'] ?? 0), $indicators)),
            ],
            'indicators' => $indicators,
            'productivitySensors' => $sensors,
        ];
    }

    public function getListKandang(): array
    {
        if (! $this->activeJenisBudidayaId) {
            return [];
        }

        $coops = DB::table('unitBudidaya')
            ->where('jenisBudidayaId', $this->activeJenisBudidayaId)
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->get(['id', 'nama', 'kapasitas', 'jumlah', 'lokasi', 'gambar', 'createdAt']);

        $envById = collect($this->getBarnEnvironment()['barns'])->keyBy('id');
        $today = now()->toDateString();

        return $coops->map(function ($coop) use ($envById, $today) {
            $env = $envById->get($coop->id);
            $status = $env['status'] ?? 'normal';

            $hdpToday = 0.0;
            $pop = (float) ($coop->jumlah ?? 0);
            if ($pop > 0) {
                $telur = DB::table('panen')
                    ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                    ->where('laporan.unitBudidayaId', $coop->id)
                    ->where('laporan.isDeleted', 0)
                    ->where('panen.isDeleted', 0)
                    ->whereDate('laporan.createdAt', $today)
                    ->sum('panen.jumlah');
                $hdpToday = round(($telur / $pop) * 100, 1);
            }

            return [
                'id' => $coop->id,
                'nama' => $coop->nama,
                'kapasitas' => $coop->kapasitas,
                'jumlah' => $coop->jumlah,
                'lokasi' => $coop->lokasi,
                'photo' => $this->barnPhotoUrl($coop->gambar ?? null),
                'photoFallback' => asset('images/barn-placeholder.jpg'),
                'status' => $status,
                'temp' => $env['temp'] ?? '-',
                'hdp' => $hdpToday,
            ];
        })->values()->all();
    }

    public function getLastFuzzyEvaluationAt(): ?Carbon
    {
        $coopIds = $this->getActiveCoopIds();

        $query = DB::table('spk_fuzzy_logs')->orderByDesc('createdAt');

        if (! empty($coopIds)) {
            $query->where(function ($q) use ($coopIds) {
                $q->whereIn('unit_budidaya_id', $coopIds)->orWhereNull('unit_budidaya_id');
            });
        }

        $ts = $query->value('createdAt');

        return $ts ? Carbon::parse($ts) : null;
    }

    public function getSpkResults(): array
    {
        return [
            'lingkungan' => [
                'status' => 'Monitor',
                'statusColor' => 'amber',
                'score' => 76.4,
                'scoreColor' => 'blue',
                'title' => 'Decision: Check Ventilation.',
                'description' => 'Environment score is 76.4/100. Humidity is ideal, but elevated temperature and ammonia levels suggest reduced airflow efficiency.',
                'link' => '#',
            ],
            'produktivitas' => [
                'status' => 'Maintain',
                'statusColor' => 'blue',
                'score' => 92.5,
                'scoreColor' => 'emerald',
                'title' => 'Decision: Keep Current Rations.',
                'description' => 'Health score is 92.5/100. Birds are performing optimally. Feed quality dip is negligible given high HDP output.',
                'link' => '#',
            ],
            'gabungan' => [
                'status' => 'Excellent',
                'statusColor' => 'emerald',
                'score' => 92.5,
                'scoreColor' => 'emerald',
                'title' => 'Decision: Expand Phase 2.',
                'description' => 'Combined weighted score indicates peak performance. Current environmental stress is minor compared to productivity gains.',
                'link' => '#',
                'isMain' => true,
            ],
        ];
    }

    public function getProductionLog(): array
    {
        $coops = $this->activeJenisBudidayaId
            ? DB::table('unitBudidaya')->where('jenisBudidayaId', $this->activeJenisBudidayaId)->where('isDeleted', 0)->get()->keyBy('id')
            : collect();

        if ($coops->isEmpty()) {
            return [['date' => '-', 'barn' => 'No Data', 'flock_age' => '-', 'birds' => '-', 'eggs' => '-', 'rejects' => '-', 'mortality' => '-', 'status' => '-']];
        }

        $dailyReports = DB::table('laporan')
            ->whereIn('unitBudidayaId', $coops->pluck('id'))
            ->where('isDeleted', 0)
            ->whereIn('tipe', ['panen', 'kematian', 'Panen', 'Mati'])
            ->selectRaw('unitBudidayaId, DATE(createdAt) as reportDate, MAX(createdAt) as latestAt')
            ->groupBy('unitBudidayaId', 'reportDate')
            ->orderBy('latestAt', 'desc')
            ->limit(10)
            ->get();

        $logs = [];
        foreach ($dailyReports as $row) {
            $b = $coops[$row->unitBudidayaId];
            $date = Carbon::parse($row->reportDate);
            $panen = DB::table('panen')
                ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
                ->where('laporan.unitBudidayaId', $b->id)
                ->whereDate('laporan.createdAt', $row->reportDate)
                ->where('laporan.isDeleted', 0)
                ->where('panen.isDeleted', 0)
                ->sum('panen.jumlah');
            $reject = $this->sumRejectEggsForBarnDate($b->id, $row->reportDate);
            $mortality = $this->countMortalityForBarnDate($b->id, $row->reportDate);

            $age = $this->flockAgeWeeks($b, $date).' Wks';
            $birdsAtReport = $this->populationAtReportTime($b->id, $date->copy()->endOfDay()->toDateTimeString(), (float) ($b->jumlah ?? 0));
            $status = $mortality > 0 ? 'Attention' : ($reject > 0 ? 'Check' : 'Optimal');

            $logs[] = [
                'date' => $date->format('M d, Y'),
                'barn' => $b->nama,
                'flock_age' => $age,
                'birds' => number_format($birdsAtReport, 0, ',', '.'),
                'eggs' => $panen > 0 ? number_format((float) $panen, 0, ',', '.') : '-',
                'rejects' => $reject > 0 ? number_format((float) $reject, 0, ',', '.') : '-',
                'mortality' => $mortality > 0 ? number_format((float) $mortality, 0, ',', '.') : '-',
                'status' => $status,
            ];
        }

        return empty($logs) ? [['date' => '-', 'barn' => '-', 'flock_age' => '-', 'birds' => '-', 'eggs' => '-', 'rejects' => '-', 'mortality' => '-', 'status' => '-']] : $logs;
    }

    private function rejectEggGradeKeywords(): array
    {
        return ['afkir', 'reject', 'rusak', 'retak', 'kotor', 'pecah', 'cacat', 'broken', 'dirty', 'crack'];
    }

    private function isRejectEggGradeName(string $gradeName): bool
    {
        $name = strtolower($gradeName);
        foreach ($this->rejectEggGradeKeywords() as $keyword) {
            if (str_contains($name, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function applyRejectEggGradeFilter($query)
    {
        return $query->where(function ($inner) {
            foreach ($this->rejectEggGradeKeywords() as $keyword) {
                $inner->orWhereRaw('LOWER(grade.nama) LIKE ?', ['%'.$keyword.'%']);
            }
        });
    }

    private function sumRejectEggsForReport(?string $laporanId): float
    {
        if (! $laporanId) {
            return 0.0;
        }

        $query = DB::table('panenRincianGrade')
            ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
            ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
            ->where('panen.laporanId', $laporanId)
            ->where('panen.isDeleted', 0)
            ->where('panenRincianGrade.isDeleted', 0)
            ->where('grade.isDeleted', 0);

        return (float) $this->applyRejectEggGradeFilter($query)->sum('panenRincianGrade.jumlah');
    }

    private function sumRejectEggsForBarnDate(string $coopId, string $date): float
    {
        $query = DB::table('panenRincianGrade')
            ->join('panen', 'panen.id', '=', 'panenRincianGrade.panenId')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->join('grade', 'panenRincianGrade.gradeId', '=', 'grade.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->whereDate('laporan.createdAt', $date)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->where('panenRincianGrade.isDeleted', 0)
            ->where('grade.isDeleted', 0);

        return (float) $this->applyRejectEggGradeFilter($query)->sum('panenRincianGrade.jumlah');
    }

    private function countMortalityForBarnDate(string $coopId, string $date): int
    {
        return (int) DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->whereDate('laporan.createdAt', $date)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();
    }

    private function populationAtReportTime(string $coopId, ?string $reportCreatedAt, float $currentPopulation): float
    {
        if (! $reportCreatedAt) {
            return max($currentPopulation, 0);
        }

        $futureDeaths = (int) DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.createdAt', '>', $reportCreatedAt)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();

        return max($currentPopulation + $futureDeaths, 0);
    }
}
