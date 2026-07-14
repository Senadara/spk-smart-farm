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

        foreach (['deviceCode', 'device_code', 'device', 'code'] as $key) {
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

        return data_get($data, $mapping->payloadKey);
    }

    private function resolveTimestamp(mixed $timestamp, mixed $data): Carbon
    {
        $candidate = $timestamp;

        if ($candidate === null && is_array($data)) {
            foreach (['sensorTimestamp', 'timestamp', 'time', 'createdAt', 'created_at'] as $key) {
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
