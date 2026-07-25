<?php

namespace App\Services\Fuzzy;

use App\Models\SpkFuzzyProfile;
use App\Models\SpkFuzzyVariable;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class InputResolver
{
    public function resolve(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): array
    {
        return $this->resolveWithMeta($coopId, $commodityId, $profileId)['inputs'];
    }

    public function resolveWithMeta(?string $coopId = null, ?string $commodityId = null, ?string $profileId = null): array
    {
        $profile = SpkFuzzyProfile::resolveForContext($commodityId, $coopId, $profileId);
        $profileId = $profile?->id ?: $profileId;
        $commodityId = $commodityId ?: $profile?->commodity_id;

        $variables = SpkFuzzyVariable::with('inputSource')
            ->where('type', 'input')
            ->when($profileId, fn ($query) => $query->where('profile_id', $profileId))
            ->get();

        $inputs = [];
        $meta = [];

        foreach ($variables as $var) {
            if ($var->group === 'kausalitas') {
                continue;
            }

            $source = $var->inputSource;

            if (! $source) {
                Log::warning("[InputResolver] Variable '{$var->name}' has no input source, defaulting to 0.");
                $inputs[$var->name] = 0.0;
                $meta[$var->name] = [
                    'variable' => $var->name,
                    'label' => $var->description ?: $var->name,
                    'source_type' => null,
                    'status' => 'missing',
                    'value' => 0.0,
                    'message' => 'Variabel belum memiliki sumber data.',
                    'fallback' => true,
                ];
                continue;
            }

            try {
                [$value, $sourceMeta] = $this->resolveSourceWithMeta($source, $coopId, $commodityId, $profileId);

                $inputs[$var->name] = (float) ($value ?? 0.0);
                $meta[$var->name] = array_merge([
                    'variable' => $var->name,
                    'label' => $var->description ?: $var->name,
                    'source_type' => $source->source_type,
                    'value' => $inputs[$var->name],
                    'fallback' => false,
                ], $sourceMeta);
            } catch (\Throwable $e) {
                Log::error("[InputResolver] Error resolving '{$var->name}': " . $e->getMessage());
                $inputs[$var->name] = 0.0;
                $meta[$var->name] = [
                    'variable' => $var->name,
                    'label' => $var->description ?: $var->name,
                    'source_type' => $source->source_type,
                    'status' => 'error',
                    'value' => 0.0,
                    'message' => $e->getMessage(),
                    'fallback' => true,
                ];
            }
        }

        return [
            'inputs' => $inputs,
            'meta' => $meta,
            'profile_id' => $profileId,
            'commodity_id' => $commodityId,
        ];
    }

    private function resolveSourceWithMeta($source, ?string $coopId, ?string $commodityId, ?string $profileId): array
    {
        return match ($source->source_type) {
            'iot' => $this->resolveIotWithMeta($source, $coopId),
            'report_metric' => $this->resolveReportMetricWithMeta($source, $coopId, $commodityId),
            'database' => $this->resolveDatabaseWithMeta($source, $coopId, $commodityId),
            'function' => $this->resolveFunctionWithMeta($source, $coopId, $commodityId, $profileId),
            default => [0.0, [
                'status' => 'missing',
                'message' => 'Tipe sumber data tidak dikenali.',
                'fallback' => true,
            ]],
        };
    }

    private function resolveIot($source, ?string $coopId): float
    {
        return (float) $this->resolveIotWithMeta($source, $coopId)[0];
    }

    private function resolveIotWithMeta($source, ?string $coopId): array
    {
        $config = $source->extra_config ?? [];
        $paramCode = $config['parameterCode'] ?? null;
        $maxAgeMinutes = (int) ($config['maxAgeMinutes'] ?? 30);
        $fallbackValue = $this->iotFallbackValue($paramCode, $config);

        $query = DB::table('iot_sensor_data')
            ->join('iot_parameter', 'iot_parameter.id', '=', 'iot_sensor_data.parameterId')
            ->join('iot_device', 'iot_device.id', '=', 'iot_sensor_data.deviceId')
            ->where('iot_sensor_data.isDeleted', 0);

        if ($coopId) {
            $query->where('iot_device.unitBudidayaId', $coopId);
        }

        if ($paramCode) {
            $query->where('iot_parameter.parameterCode', $paramCode);
        }

        $row = $query
            ->orderBy('iot_sensor_data.sensorTimestamp', 'desc')
            ->select([
                'iot_sensor_data.value',
                'iot_sensor_data.sensorTimestamp',
                'iot_sensor_data.deviceId',
            ])
            ->first();

        if (! $row) {
            $this->markIotMiss(null, $coopId, $paramCode, $config, 'Tidak ada data sensor.');
            return [$fallbackValue, [
                'status' => 'missing',
                'message' => 'Tidak ada data sensor.',
                'parameter_code' => $paramCode,
                'max_age_minutes' => $maxAgeMinutes,
                'fallback' => true,
            ]];
        }

        $timestamp = Carbon::parse($row->sensorTimestamp);
        if ($maxAgeMinutes > 0 && $timestamp->lt(now()->subMinutes($maxAgeMinutes))) {
            $this->markIotMiss((string) $row->deviceId, $coopId, $paramCode, $config, "Data sensor lebih lama dari {$maxAgeMinutes} menit.");
            return [$fallbackValue, [
                'status' => 'stale',
                'message' => "Data sensor lebih lama dari {$maxAgeMinutes} menit.",
                'parameter_code' => $paramCode,
                'device_id' => (string) $row->deviceId,
                'sensor_timestamp' => $timestamp->toDateTimeString(),
                'max_age_minutes' => $maxAgeMinutes,
                'fallback' => true,
            ]];
        }

        $this->markIotOnline((string) $row->deviceId, $timestamp);

        return [(float) ($row->value ?? 0.0), [
            'status' => 'ok',
            'message' => 'Data sensor tersedia.',
            'parameter_code' => $paramCode,
            'device_id' => (string) $row->deviceId,
            'sensor_timestamp' => $timestamp->toDateTimeString(),
            'max_age_minutes' => $maxAgeMinutes,
        ]];
    }

    private function iotFallbackValue(?string $paramCode, array $config): float
    {
        $configured = $config['fallbackValue'] ?? $config['fallback_value'] ?? null;
        if (is_numeric($configured)) {
            return (float) $configured;
        }

        return match (strtoupper((string) $paramCode)) {
            'TEMP', 'TEMPERATURE' => 26.0,
            'HUMID', 'HUMIDITY' => 65.0,
            'AMMON', 'AMMONIA', 'NH3' => 5.0,
            'LIGHT', 'LUX' => 250.0,
            default => 0.0,
        };
    }

    private function resolveReportMetric($source, ?string $coopId, ?string $commodityId): float
    {
        return (float) $this->resolveReportMetricWithMeta($source, $coopId, $commodityId)[0];
    }

    private function resolveReportMetricWithMeta($source, ?string $coopId, ?string $commodityId): array
    {
        $config = $source->extra_config ?? [];
        $metricCode = $config['metricCode'] ?? $source->field_name;

        if (! $metricCode || ! Schema::hasTable('daily_report_metrics')) {
            return [0.0, [
                'status' => 'missing',
                'message' => 'Metric laporan belum tersedia.',
                'metric_code' => $metricCode,
                'fallback' => true,
            ]];
        }

        $aggregation = strtolower((string) ($config['aggregation'] ?? 'sum'));
        $dateScope = strtolower((string) ($config['dateScope'] ?? 'today'));

        $query = DB::table('daily_report_metrics')
            ->join('laporan', 'daily_report_metrics.laporan_id', '=', 'laporan.id')
            ->where('daily_report_metrics.metric_code', $metricCode)
            ->where('daily_report_metrics.isDeleted', 0)
            ->where('laporan.isDeleted', 0);

        $this->applyReportScope($query, $coopId, $commodityId);
        $this->applyDateScope($query, 'laporan.createdAt', $dateScope);

        $recordCount = (clone $query)->count();
        $value = match ($aggregation) {
            'avg', 'average' => (float) ((clone $query)->avg('daily_report_metrics.value') ?? 0.0),
            'count' => (float) $recordCount,
            'latest' => (float) ((clone $query)->orderBy('laporan.createdAt', 'desc')->orderBy('daily_report_metrics.createdAt', 'desc')->value('daily_report_metrics.value') ?? 0.0),
            default => (float) ((clone $query)->sum('daily_report_metrics.value') ?? 0.0),
        };

        return [$value, [
            'status' => $recordCount > 0 ? 'ok' : 'missing',
            'message' => $recordCount > 0 ? 'Metric laporan tersedia.' : 'Metric laporan belum terisi pada periode ini.',
            'metric_code' => $metricCode,
            'aggregation' => $aggregation,
            'date_scope' => $dateScope,
            'records' => $recordCount,
            'fallback' => $recordCount === 0,
        ]];
    }

    private function resolveDatabase($source, ?string $coopId, ?string $commodityId): float
    {
        return (float) $this->resolveDatabaseWithMeta($source, $coopId, $commodityId)[0];
    }

    private function resolveDatabaseWithMeta($source, ?string $coopId, ?string $commodityId): array
    {
        $allowed = $this->allowedDatabaseFields();
        $tableName = $source->source_name;
        $fieldName = $source->field_name;

        if (! $tableName || ! $fieldName || ! isset($allowed[$tableName]) || ! in_array($fieldName, $allowed[$tableName], true)) {
            Log::warning("[InputResolver] Database source '{$tableName}.{$fieldName}' is not allowed.");
            return [0.0, [
                'status' => 'missing',
                'message' => 'Sumber database tidak diizinkan.',
                'table' => $tableName,
                'field' => $fieldName,
                'fallback' => true,
            ]];
        }

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $fieldName)) {
            return [0.0, [
                'status' => 'missing',
                'message' => 'Kolom sumber database belum tersedia.',
                'table' => $tableName,
                'field' => $fieldName,
                'fallback' => true,
            ]];
        }

        $config = $source->extra_config ?? [];
        $aggregation = strtolower((string) ($config['aggregation'] ?? 'sum'));
        $dateScope = strtolower((string) ($config['dateScope'] ?? 'today'));

        $query = DB::table($tableName);

        if ($tableName !== 'laporan' && Schema::hasColumn($tableName, 'laporanId')) {
            $query->join('laporan', "{$tableName}.laporanId", '=', 'laporan.id');
            $this->applyReportScope($query, $coopId, $commodityId);
            $this->applyDateScope($query, 'laporan.createdAt', $dateScope);
            $query->where('laporan.isDeleted', 0);
        } elseif ($tableName === 'laporan') {
            $this->applyReportScope($query, $coopId, $commodityId);
            $this->applyDateScope($query, 'laporan.createdAt', $dateScope);
        }

        if (Schema::hasColumn($tableName, 'isDeleted')) {
            $query->where("{$tableName}.isDeleted", 0);
        }

        $recordCount = (clone $query)->count();
        $value = match ($aggregation) {
            'avg', 'average' => (float) ((clone $query)->avg("{$tableName}.{$fieldName}") ?? 0.0),
            'count' => (float) $recordCount,
            'latest' => (float) ((clone $query)->orderBy("{$tableName}.createdAt", 'desc')->value("{$tableName}.{$fieldName}") ?? 0.0),
            default => (float) ((clone $query)->sum("{$tableName}.{$fieldName}") ?? 0.0),
        };

        return [$value, [
            'status' => $recordCount > 0 ? 'ok' : 'missing',
            'message' => $recordCount > 0 ? 'Data operasional tersedia.' : 'Data operasional belum tersedia pada periode ini.',
            'table' => $tableName,
            'field' => $fieldName,
            'aggregation' => $aggregation,
            'date_scope' => $dateScope,
            'records' => $recordCount,
            'fallback' => $recordCount === 0,
        ]];
    }

    private function resolveFunction($source, ?string $coopId, ?string $commodityId, ?string $profileId): float
    {
        return (float) $this->resolveFunctionWithMeta($source, $coopId, $commodityId, $profileId)[0];
    }

    private function resolveFunctionWithMeta($source, ?string $coopId, ?string $commodityId, ?string $profileId): array
    {
        $className = $source->function_name;

        if (! $className || ! class_exists($className)) {
            Log::warning("[InputResolver] Function class '{$className}' was not found.");
            return [0.0, [
                'status' => 'missing',
                'message' => 'Fungsi kalkulasi belum tersedia.',
                'function' => $className,
                'fallback' => true,
            ]];
        }

        $service = app($className);

        if (! method_exists($service, 'handle')) {
            Log::warning("[InputResolver] Function class '{$className}' does not have handle().");
            return [0.0, [
                'status' => 'missing',
                'message' => 'Fungsi kalkulasi tidak memiliki method handle.',
                'function' => $className,
                'fallback' => true,
            ]];
        }

        $reflection = new \ReflectionMethod($service, 'handle');
        $args = array_slice([$coopId, $commodityId, $profileId], 0, $reflection->getNumberOfParameters());

        $value = (float) $service->handle(...$args);

        return [$value, [
            'status' => 'ok',
            'message' => 'Nilai kalkulasi tersedia.',
            'function' => $className,
        ]];
    }

    private function applyReportScope($query, ?string $coopId, ?string $commodityId): void
    {
        if ($coopId) {
            $query->where('laporan.unitBudidayaId', $coopId);
            return;
        }

        $coopIds = FuzzyScope::coopIds(null, $commodityId);
        if (! empty($coopIds)) {
            $query->whereIn('laporan.unitBudidayaId', $coopIds);
        }
    }

    private function applyDateScope($query, string $column, string $dateScope): void
    {
        match ($dateScope) {
            'all' => null,
            'month' => $query->whereDate($column, '>=', now()->startOfMonth()->toDateString()),
            'week' => $query->whereDate($column, '>=', now()->startOfWeek()->toDateString()),
            default => $query->whereDate($column, now()->toDateString()),
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function allowedDatabaseFields(): array
    {
        return [
            'harianTernak' => ['pakan'],
            'panen' => ['jumlah', 'berat'],
            'kematian' => ['id'],
            'laporan' => ['id'],
        ];
    }

    private function markIotOnline(string $deviceId, Carbon $lastSeenAt): void
    {
        if (! Schema::hasTable('iot_device') || ! Schema::hasColumn('iot_device', 'lastSeenAt')) {
            return;
        }

        $device = DB::table('iot_device')->where('id', $deviceId)->first(['status']);
        if (! $device) {
            return;
        }

        $payload = [
            'lastSeenAt' => $lastSeenAt,
            'missedCount' => 0,
        ];

        if ($device->status !== 'maintenance') {
            $payload['status'] = 'active';
        }

        DB::table('iot_device')->where('id', $deviceId)->update($payload);
    }

    private function markIotMiss(?string $deviceId, ?string $coopId, ?string $paramCode, array $config, string $reason): void
    {
        if (! Schema::hasTable('iot_device') || ! Schema::hasColumn('iot_device', 'missedCount')) {
            return;
        }

        $deviceIds = $deviceId ? [$deviceId] : $this->candidateDeviceIds($coopId, $paramCode);
        $offlineAfterMisses = max(1, (int) ($config['offlineAfterMisses'] ?? 3));

        foreach ($deviceIds as $id) {
            $device = DB::table('iot_device')->where('id', $id)->first(['status', 'missedCount', 'offlineAfterMisses']);
            if (! $device || $device->status === 'maintenance') {
                continue;
            }

            $missedCount = ((int) ($device->missedCount ?? 0)) + 1;
            $threshold = (int) ($device->offlineAfterMisses ?? $offlineAfterMisses);
            $threshold = $threshold > 0 ? $threshold : $offlineAfterMisses;

            DB::table('iot_device')->where('id', $id)->update([
                'missedCount' => $missedCount,
                'lastMissedAt' => now(),
                'status' => $missedCount >= $threshold ? 'inactive' : $device->status,
            ]);

            if (Schema::hasTable('iot_device_log')) {
                DB::table('iot_device_log')->insert([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'deviceId' => $id,
                    'logType' => $missedCount >= $threshold ? 'WARNING' : 'INFO',
                    'message' => "[SPK] {$reason} Miss {$missedCount}/{$threshold}.",
                    'createdAt' => now(),
                    'updatedAt' => now(),
                ]);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function candidateDeviceIds(?string $coopId, ?string $paramCode): array
    {
        $query = DB::table('iot_device')
            ->join('iot_parameter_mapping', 'iot_parameter_mapping.deviceId', '=', 'iot_device.id')
            ->join('iot_parameter', 'iot_parameter.id', '=', 'iot_parameter_mapping.parameterId');

        if ($coopId) {
            $query->where('iot_device.unitBudidayaId', $coopId);
        }

        if ($paramCode) {
            $query->where('iot_parameter.parameterCode', $paramCode);
        }

        return $query->pluck('iot_device.id')
            ->unique()
            ->map(fn ($id) => (string) $id)
            ->values()
            ->toArray();
    }
}
