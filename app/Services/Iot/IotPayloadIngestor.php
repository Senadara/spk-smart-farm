<?php

namespace App\Services\Iot;

use App\Events\IotSensorDataReceived;
use App\Models\IotDevice;
use App\Models\IotDeviceLog;
use App\Models\IotParameterMapping;
use App\Models\IotSensorData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class IotPayloadIngestor
{
    private const DEVICE_CODE_KEYS = [
        'deviceCode',
        'device_code',
        'deviceId',
        'device_id',
        'device',
        'code',
        'device.code',
        'device.id',
        'metadata.deviceCode',
        'metadata.device_code',
        'meta.deviceCode',
        'data.deviceCode',
        'payload.deviceCode',
    ];

    private const PARAMETER_ALIASES = [
        'TEMP' => ['temperature', 'temp', 'suhu', 'suhu_c', 'temperature_c'],
        'HUMID' => ['humidity', 'humid', 'kelembapan', 'kelembaban', 'rh', 'relative_humidity'],
        'AMMON' => ['amonia', 'ammonia', 'amoniak', 'nh3'],
        'AMMO' => ['amonia', 'ammonia', 'amoniak', 'nh3'],
        'AMMA' => ['amonia', 'ammonia', 'amoniak', 'nh3'],
        'AMMONIA' => ['amonia', 'ammonia', 'amoniak', 'nh3'],
        'LIGHT' => ['light', 'lux', 'cahaya', 'ldr'],
        'LUX' => ['light', 'lux', 'cahaya', 'ldr'],
    ];

    private const PAYLOAD_CONTAINERS = ['data', 'payload', 'values', 'value', 'sensor', 'sensors', 'readings'];

    public function ingest(IotDevice $device, mixed $payload, string $source = 'iot', mixed $timestamp = null): array
    {
        $device->loadMissing('parameterMappings.parameter');

        $data = $this->extractData($payload);
        $sensorTimestamp = $this->resolveTimestamp($timestamp, $data);
        $broadcastPayloads = [];
        $inserted = 0;
        $skipped = 0;

        DB::transaction(function () use ($device, $data, $source, $sensorTimestamp, &$broadcastPayloads, &$inserted, &$skipped) {
            foreach ($device->parameterMappings as $mapping) {
                $value = $this->valueForMapping($data, $mapping);

                if ($value === null || ! is_numeric($value)) {
                    $skipped++;
                    continue;
                }

                $sensor = IotSensorData::create([
                    'deviceId' => $device->id,
                    'parameterId' => $mapping->parameterId,
                    'value' => (float) $value,
                    'sensorTimestamp' => $sensorTimestamp,
                ]);

                $broadcastPayloads[] = $this->broadcastPayload($device, $mapping, $sensor);
                $inserted++;
            }

            if ($inserted > 0) {
                $this->markDeviceOnline($device);
                $this->createLog($device->id, 'INFO', "[{$source}] {$inserted} parameter sensor tercatat.");
            } else {
                $this->markDeviceMiss($device, "[{$source}] Payload diterima tetapi tidak cocok dengan mapping parameter.");
            }
        });

        foreach ($broadcastPayloads as $broadcastPayload) {
            try {
                broadcast(new IotSensorDataReceived($broadcastPayload));
            } catch (\Throwable $error) {
                Log::warning('Broadcast IoT gagal: '.$error->getMessage());
            }
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'deviceCode' => $device->deviceCode,
        ];
    }

    public function ingestMappingValue(IotDevice $device, IotParameterMapping $mapping, mixed $value, string $source = 'iot', mixed $timestamp = null): array
    {
        $mapping->loadMissing('parameter');
        $sensorTimestamp = $this->resolveTimestamp($timestamp, null);
        $broadcastPayload = null;
        $inserted = 0;
        $skipped = 0;

        DB::transaction(function () use ($device, $mapping, $value, $source, $sensorTimestamp, &$broadcastPayload, &$inserted, &$skipped) {
            if ($value === null || ! is_numeric($value)) {
                $skipped++;
                $this->markDeviceMiss($device, "[{$source}] Payload diterima tetapi nilai parameter tidak valid.");
                return;
            }

            $sensor = IotSensorData::create([
                'deviceId' => $device->id,
                'parameterId' => $mapping->parameterId,
                'value' => (float) $value,
                'sensorTimestamp' => $sensorTimestamp,
            ]);

            $this->markDeviceOnline($device);
            $this->createLog($device->id, 'INFO', "[{$source}] 1 parameter sensor tercatat.");
            $broadcastPayload = $this->broadcastPayload($device, $mapping, $sensor);
            $inserted++;
        });

        if ($broadcastPayload) {
            try {
                broadcast(new IotSensorDataReceived($broadcastPayload));
            } catch (\Throwable $error) {
                Log::warning('Broadcast IoT gagal: '.$error->getMessage());
            }
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'deviceCode' => $device->deviceCode,
        ];
    }

    public function extractData(mixed $payload): mixed
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            }
        }

        if (! is_array($payload)) {
            return $payload;
        }

        if (isset($payload['m2m:cin']['con'])) {
            $con = $payload['m2m:cin']['con'];

            if (is_string($con)) {
                $decoded = json_decode($con, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }

                $cleaned = trim(str_replace(["'", '"'], '', $con));
                return is_numeric($cleaned) ? (float) $cleaned : $cleaned;
            }

            return $con;
        }

        return $payload;
    }

    public function deviceCodeFromPayload(mixed $payload): ?string
    {
        $data = $this->extractData($payload);

        if (! is_array($data)) {
            return null;
        }

        foreach (self::DEVICE_CODE_KEYS as $key) {
            $value = data_get($data, $key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function valueForMapping(mixed $data, IotParameterMapping $mapping): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        foreach ($this->candidatePayloadKeys($mapping) as $key) {
            $value = data_get($data, $key);
            if ($value !== null) {
                return $value;
            }

            foreach (self::PAYLOAD_CONTAINERS as $container) {
                $value = data_get($data, "{$container}.{$key}");
                if ($value !== null) {
                    return $value;
                }
            }
        }

        return null;
    }

    private function candidatePayloadKeys(IotParameterMapping $mapping): array
    {
        $keys = [];
        $this->appendKeyVariants($keys, (string) $mapping->payloadKey);

        $parameter = $mapping->parameter;
        $code = strtoupper((string) ($parameter->parameterCode ?? ''));
        $name = strtolower((string) ($parameter->parameterName ?? ''));

        foreach (self::PARAMETER_ALIASES[$code] ?? [] as $alias) {
            $this->appendKeyVariants($keys, $alias);
        }

        if (str_contains($name, 'suhu')) {
            foreach (self::PARAMETER_ALIASES['TEMP'] as $alias) {
                $this->appendKeyVariants($keys, $alias);
            }
        } elseif (str_contains($name, 'lembap')) {
            foreach (self::PARAMETER_ALIASES['HUMID'] as $alias) {
                $this->appendKeyVariants($keys, $alias);
            }
        } elseif (str_contains($name, 'amonia') || str_contains($name, 'ammonia')) {
            foreach (self::PARAMETER_ALIASES['AMMON'] as $alias) {
                $this->appendKeyVariants($keys, $alias);
            }
        } elseif (str_contains($name, 'cahaya') || str_contains($name, 'lux')) {
            foreach (self::PARAMETER_ALIASES['LIGHT'] as $alias) {
                $this->appendKeyVariants($keys, $alias);
            }
        }

        return array_values(array_unique(array_filter($keys, fn (string $key) => trim($key) !== '')));
    }

    private function appendKeyVariants(array &$keys, string $key): void
    {
        $key = trim($key);
        if ($key === '') {
            return;
        }

        $keys[] = $key;
        $keys[] = strtolower($key);
        $keys[] = strtoupper($key);
    }

    private function resolveTimestamp(mixed $timestamp, mixed $data): Carbon
    {
        $candidate = $timestamp;

        if ($candidate === null && is_array($data)) {
            foreach (['sensorTimestamp', 'sensor_timestamp', 'timestamp', 'time', 'createdAt', 'created_at', 'data.timestamp', 'payload.timestamp'] as $key) {
                $value = data_get($data, $key);
                if ($value !== null) {
                    $candidate = $value;
                    break;
                }
            }
        }

        if ($candidate instanceof \DateTimeInterface) {
            return Carbon::instance($candidate);
        }

        if (is_numeric($candidate)) {
            return Carbon::createFromTimestamp((int) $candidate);
        }

        if (is_string($candidate) && trim($candidate) !== '') {
            try {
                return Carbon::parse($candidate);
            } catch (\Throwable) {
                return now();
            }
        }

        return now();
    }

    private function broadcastPayload(IotDevice $device, IotParameterMapping $mapping, IotSensorData $sensor): array
    {
        return [
            'device' => [
                'deviceCode' => $device->deviceCode,
                'deviceName' => $device->deviceName,
            ],
            'parameter' => [
                'parameterName' => $mapping->parameter->parameterName ?? $mapping->payloadKey,
                'unit' => $mapping->parameter->unit ?? '',
            ],
            'value' => (float) $sensor->value,
            'timestamp' => $sensor->sensorTimestamp?->format('d M Y H:i:s') ?? now()->format('d M Y H:i:s'),
        ];
    }

    private function markDeviceOnline(IotDevice $device): void
    {
        if (! Schema::hasColumn('iot_device', 'lastSeenAt')) {
            return;
        }

        $payload = [
            'lastSeenAt' => now(),
            'missedCount' => 0,
        ];

        if ($device->status !== 'maintenance') {
            $payload['status'] = 'active';
        }

        $device->update($payload);
    }

    private function markDeviceMiss(IotDevice $device, string $reason): void
    {
        if (! Schema::hasColumn('iot_device', 'missedCount') || $device->status === 'maintenance') {
            return;
        }

        $missedCount = ((int) ($device->missedCount ?? 0)) + 1;
        $threshold = max(1, (int) ($device->offlineAfterMisses ?? 3));

        $device->update([
            'missedCount' => $missedCount,
            'lastMissedAt' => now(),
            'status' => $missedCount >= $threshold ? 'inactive' : $device->status,
        ]);

        $this->createLog(
            $device->id,
            $missedCount >= $threshold ? 'WARNING' : 'INFO',
            "{$reason} Miss {$missedCount}/{$threshold}."
        );
    }

    private function createLog(string $deviceId, string $type, string $message): void
    {
        IotDeviceLog::create([
            'deviceId' => $deviceId,
            'logType' => $type,
            'message' => $message,
        ]);
    }
}
