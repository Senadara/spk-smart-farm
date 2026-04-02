<?php

namespace App\Jobs;

use App\Models\IotDevice;
use App\Models\IotDeviceLog;
use App\Models\IotSensorData;
use App\Events\IotSensorDataReceived;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollIotDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $deviceId;

    public $tries = 3;
    public $backoff = 10;

    public function __construct($deviceId)
    {
        $this->deviceId = $deviceId;
    }

    public function handle()
    {
        $device = IotDevice::with(['connectionConfig.protocol', 'parameterMappings.parameter'])
            ->find($this->deviceId);

        if (!$device) return;

        $config = $device->connectionConfig;
        $baseUrl = rtrim($config->baseUrl, '/');
        $endpointTemplate = ltrim($config->endpointPath, '/');

        $headers = is_array($config->headers) ? $config->headers : json_decode($config->headers ?? '{}', true);

        if ($config->authType === 'bearer') {
            $headers['Authorization'] = 'Bearer ' . $config->authKey;
        } elseif ($config->authType === 'basic') {
            $headers['Authorization'] = 'Basic ' . base64_encode($config->authKey);
        } elseif ($config->authType === 'api_key') {
            if (!isset($headers['X-M2M-Origin'])) {
                $headers['X-M2M-Origin'] = $config->authKey;
            }
        }

        if (str_contains($endpointTemplate, '{payloadKey}')) {
            $this->pollFragmented($device, $baseUrl, $endpointTemplate, $headers);
        } else {
            $this->pollSingle($device, $baseUrl . '/' . $endpointTemplate, $headers);
        }
    }

    private function pollSingle($device, $url, $headers)
    {
        try {
            $response = Http::withHeaders($headers)->acceptJson()->timeout(10)->get($url);

            if ($response->successful()) {
                $this->processPayload($device, $response->json());

                IotDeviceLog::create([
                    'deviceId' => $device->id,
                    'logType' => 'INFO',
                    'message' => 'Polling success (QUEUE).'
                ]);
            } else {
                $this->logError($device, "HTTP {$response->status()}");
            }
        } catch (\Exception $e) {
            $this->logError($device, $e->getMessage());
        }
    }

    private function pollFragmented($device, $baseUrl, $endpointTemplate, $headers)
    {
        foreach ($device->parameterMappings as $mapping) {
            $url = $baseUrl . '/' . str_replace('{payloadKey}', $mapping->payloadKey, $endpointTemplate);

            try {
                $response = Http::withHeaders($headers)->acceptJson()->timeout(10)->get($url);

                if ($response->successful()) {
                    $payload = $response->json();
                    $dataTarget = $this->extractData($payload);

                    $value = is_array($dataTarget) && isset($dataTarget[$mapping->payloadKey])
                        ? $dataTarget[$mapping->payloadKey]
                        : $dataTarget;

                    $this->storeAndBroadcast($device, $mapping, $value);
                }

                usleep(200000); // rate limit ringan

            } catch (\Exception $e) {
                Log::warning("Fragment error: " . $e->getMessage());
            }
        }
    }

    private function processPayload($device, $payload)
    {
        $dataTarget = $this->extractData($payload);

        foreach ($device->parameterMappings as $mapping) {
            $value = data_get($dataTarget, $mapping->payloadKey);
            $this->storeAndBroadcast($device, $mapping, $value);
        }
    }

    private function extractData($payload)
    {
        if (isset($payload['m2m:cin']['con'])) {
            $con = $payload['m2m:cin']['con'];

            // FIX untuk kasus "'355'"
            $con = trim($con, "'");

            return is_string($con) ? json_decode($con, true) ?? $con : $con;
        }

        return $payload;
    }

    private function storeAndBroadcast($device, $mapping, $value)
    {
        if ($value !== null && is_numeric($value)) {

            $sensor = IotSensorData::create([
                'deviceId' => $device->id,
                'parameterId' => $mapping->parameterId,
                'value' => (float) $value,
                'sensorTimestamp' => now(),
            ]);

            $payload = [
                'device' => [
                    'deviceCode' => $device->deviceCode,
                    'deviceName' => $device->deviceName
                ],
                'parameter' => [
                    'parameterName' => $mapping->parameter->parameterName ?? $mapping->payloadKey,
                    'unit' => $mapping->parameter->unit ?? ''
                ],
                'value' => (float) $value,
                'timestamp' => $sensor->sensorTimestamp->format('d M Y H:i:s'),
            ];

            try {
                broadcast(new IotSensorDataReceived($payload));
            } catch (\Exception $e) {
                Log::warning("Broadcast gagal: " . $e->getMessage());
            }
        }
    }

    private function logError($device, $message)
    {
        IotDeviceLog::create([
            'deviceId' => $device->id,
            'logType' => 'ERROR',
            'message' => $message
        ]);
    }
}