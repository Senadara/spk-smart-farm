<?php

namespace App\Services\Fuzzy;

use App\Models\SpkFuzzyVariable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * InputResolver — mengumpulkan semua input fuzzy dari multi-source:
 *   - 'iot'      → ambil nilai sensor terbaru dari iot_sensor_data
 *   - 'database' → query agregasi dari tabel operasional
 *   - 'function' → panggil service kalkulasi (HDP, FCR, dst.)
 *
 * Hasilnya adalah array asosiatif unified input siap diproses MamdaniEngine.
 */
class InputResolver
{
    /**
     * Resolve semua input variabel fuzzy dari sumber data.
     *
     * @param  string|null $coopId UUID unitBudidaya. Null = global.
     * @return array<string, float>  Contoh: ['suhu' => 32.5, 'kelembapan' => 60.0, 'hdp' => 92.1]
     */
    public function resolve(?string $coopId = null): array
    {
        // Ambil semua variabel input dengan sumber datanya
        $variables = SpkFuzzyVariable::with('inputSource')
            ->where('type', 'input')
            ->get();

        $inputs = [];

        foreach ($variables as $var) {
            // Variabel kausalitas (label_lingkungan, label_kesehatan) adalah output dari tahap sebelumnya,
            // jadi tidak perlu di-resolve dari database/sensor.
            if ($var->group === 'kausalitas') {
                continue;
            }

            $source = $var->inputSource;

            if (!$source) {
                // Tidak ada sumber → default 0, log warning
                \Log::warning("[InputResolver] Variabel '{$var->name}' tidak punya input source, default 0.");
                $inputs[$var->name] = 0.0;
                continue;
            }

            try {
                $value = match ($source->source_type) {
                    'iot'      => $this->resolveIot($source, $coopId),
                    'database' => $this->resolveDatabase($source, $coopId),
                    'function' => $this->resolveFunction($source, $coopId),
                    default    => 0.0,
                };

                // Null-guard: ganti null dengan 0
                $inputs[$var->name] = (float) ($value ?? 0.0);

            } catch (\Throwable $e) {
                \Log::error("[InputResolver] Error resolving '{$var->name}': " . $e->getMessage());
                $inputs[$var->name] = 0.0;
            }
        }

        return $inputs;
    }

    // ──────────────────────────────────────────────────────────────
    // PRIVATE RESOLVERS
    // ──────────────────────────────────────────────────────────────

    /**
     * Ambil nilai sensor IoT terbaru.
     * extra_config['parameterCode'] digunakan untuk filter jenis sensor.
     */
    private function resolveIot($source, ?string $coopId): float
    {
        $config      = $source->extra_config ?? [];
        $paramCode   = $config['parameterCode'] ?? null;

        $query = DB::table('iot_sensor_data')
            ->join('iot_parameter', 'iot_parameter.id', '=', 'iot_sensor_data.parameterId')
            ->join('iot_device', 'iot_device.id', '=', 'iot_sensor_data.deviceId');

        // Filter per kandang jika diminta
        if ($coopId) {
            $query->where('iot_device.unitBudidayaId', $coopId);
        }

        // Filter berdasarkan kode parameter sensor
        if ($paramCode) {
            $query->where('iot_parameter.parameterCode', $paramCode);
        }

        $value = $query
            ->where('iot_sensor_data.isDeleted', 0)
            ->orderBy('iot_sensor_data.sensorTimestamp', 'desc')
            ->value('iot_sensor_data.value');

        return (float) ($value ?? 0.0);
    }

    /**
     * Ambil nilai dari tabel database operasional.
     * field_name adalah kolom yang di-SUM/AVG.
     */
    private function resolveDatabase($source, ?string $coopId): float
    {
        $tableName = $source->source_name;
        $fieldName = $source->field_name;

        if (!$tableName || !$fieldName) {
            return 0.0;
        }

        $value = DB::table($tableName)->sum($fieldName);

        return (float) ($value ?? 0.0);
    }

    /**
     * Panggil service kalkulasi (HDP, FCR, dll.) via service container.
     * Service harus punya method handle(?string $coopId): float.
     */
    private function resolveFunction($source, ?string $coopId): float
    {
        $className = $source->function_name;

        if (!$className || !class_exists($className)) {
            \Log::warning("[InputResolver] Function class '{$className}' tidak ditemukan.");
            return 0.0;
        }

        // Resolve via container agar bisa inject dependencies
        $service = app($className);

        // Panggil handle dengan coopId jika service mendukungnya
        $reflection = new \ReflectionMethod($service, 'handle');
        $params     = $reflection->getParameters();

        if (count($params) > 0) {
            return (float) $service->handle($coopId);
        }

        return (float) $service->handle();
    }
}
