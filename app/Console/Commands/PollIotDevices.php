<?php

namespace App\Console\Commands;

use App\Jobs\PollIotDeviceJob;
use App\Models\IotDevice;
use App\Models\IotDeviceLog;
use App\Events\IotSensorDataReceived;
use App\Models\IotSensorData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PollIotDevices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'iot:poll';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poll data dari IoT Devices yang menggunakan komunikasi REST API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai proses polling IoT Devices...');

        // Ambil semua device aktif yang konfigurasinya memiliki baseUrl
        // Dan relasi connectionConfig dipanggil
        $devices = IotDevice::with(['connectionConfig.protocol', 'parameterMappings.parameter'])
            ->where('status', 'active')
            ->whereHas('connectionConfig', function ($q) {
                // Kita asumsikan jika punya baseUrl, ia menggunakan REST
                $q->whereNotNull('baseUrl');
            })
            ->get();

        if ($devices->isEmpty()) {
            $this->info('Tidak ada device REST aktif yang perlu dipoll.');
            return;
        }

        /** @var \App\Models\IotDevice $device */
        foreach ($devices as $device) {
            PollIotDeviceJob::dispatch($device->id);
        }

        $this->info('Proses polling selesai.');
    }

    private function pollDevice($device)
    {
        $config = $device->connectionConfig;
        $baseUrl = rtrim($config->baseUrl, '/');
        $endpointTemplate = ltrim($config->endpointPath, '/');

        // Setup Headers
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

        // Jika endpoint menuntut parameter spesifik per payloadKey (seperti di struktur container terpisah Antares)
        if (str_contains($endpointTemplate, '{payloadKey}')) {
            $this->pollFragmentedEndpoints($device, $baseUrl, $endpointTemplate, $headers);
        } else {
            $this->pollSingleEndpoint($device, $baseUrl . '/' . $endpointTemplate, $headers);
        }
    }

    private function pollSingleEndpoint($device, $url, $headers)
    {
        $this->info("Menghubungi: $url untuk Device: {$device->deviceCode}");

        try {
            $response = Http::withHeaders($headers)->acceptJson()->timeout(10)->get($url);

            if ($response->successful()) {
                $this->processPayload($device, $response->json());
                
                IotDeviceLog::create([
                    'deviceId' => $device->id,
                    'logType' => 'INFO',
                    'message' => 'Berhasil mengambil data via polling (PULL).'
                ]);
            } else {
                $this->logError($device, "HTTP {$response->status()}: " . $response->body());
            }
        } catch (\Exception $e) {
            $this->logError($device, 'Exception Timeout/Connection: ' . $e->getMessage());
        }
    }

    private function pollFragmentedEndpoints($device, $baseUrl, $endpointTemplate, $headers)
    {
        $successCount = 0;

        foreach ($device->parameterMappings as $mapping) {
            $url = $baseUrl . '/' . str_replace('{payloadKey}', $mapping->payloadKey, $endpointTemplate);
            $this->info("Menghubungi Fragment: $url");

            try {
                $response = Http::withHeaders($headers)->acceptJson()->timeout(10)->get($url);
                if ($response->successful()) {
                    $payload = $response->json();
                    $dataTarget = $payload;

                    if (isset($payload['m2m:cin']['con'])) {
                        $con = $payload['m2m:cin']['con'];
                        $dataTarget = is_string($con) ? json_decode($con, true) : $con;
                    }

                    // Jika response hanyalah scalar nilai
                    $value = is_array($dataTarget) && isset($dataTarget[$mapping->payloadKey]) 
                                ? $dataTarget[$mapping->payloadKey] 
                                : $dataTarget;

                    if ($value !== null && is_numeric($value)) {
                        $sensorModel = IotSensorData::create([
                            'deviceId' => $device->id,
                            'parameterId' => $mapping->parameterId,
                            'value' => (float) $value,
                            'sensorTimestamp' => now(),
                        ]);

                        // Broadcast ke Frontend (Monitoring Web)
                        $broadcastPayload = [
                            'device' => ['deviceCode' => $device->deviceCode, 'deviceName' => $device->deviceName],
                            'parameter' => ['parameterName' => $mapping->parameter->parameterName ?? $mapping->payloadKey, 'unit' => $mapping->parameter->unit ?? ''],
                            'value' => (float) $value,
                            'timestamp' => $sensorModel->sensorTimestamp->format('d M Y H:i:s'),
                        ];
                        try {
                            broadcast(new IotSensorDataReceived($broadcastPayload));
                        } catch (\Exception $e) {
                            Log::warning("Broadcast gagal untuk device {$device->deviceCode}: " . $e->getMessage());
                        }

                        $successCount++;
                    }
                }
            } catch (\Exception $e) {
                $this->error("Fragment {$mapping->payloadKey} timeout.");
            }
        }

        if ($successCount > 0) {
            IotDeviceLog::create([
                'deviceId' => $device->id,
                'logType' => 'INFO',
                'message' => "Berhasil menarik $successCount parameter terfragmen (PULL)."
            ]);
        }
    }

    private function logError($device, $message)
    {
        IotDeviceLog::create([
            'deviceId' => $device->id,
            'logType' => 'ERROR',
            'message' => $message
        ]);
        $this->error("Error untuk device {$device->deviceCode}: $message");
    }

    private function processPayload($device, $payload)
    {
        $dataTarget = $payload;

        if (isset($payload['m2m:cin']['con'])) {
            $con = $payload['m2m:cin']['con'];
            $dataTarget = is_string($con) ? json_decode($con, true) : $con;
        }

        foreach ($device->parameterMappings as $mapping) {
            $value = data_get($dataTarget, $mapping->payloadKey);

            if ($value !== null && is_numeric($value)) {
                $sensorModel = IotSensorData::create([
                    'deviceId' => $device->id,
                    'parameterId' => $mapping->parameterId,
                    'value' => (float) $value,
                    'sensorTimestamp' => now(),
                ]);

                // Broadcast ke UI
                $broadcastPayload = [
                    'device' => ['deviceCode' => $device->deviceCode, 'deviceName' => $device->deviceName],
                    'parameter' => ['parameterName' => $mapping->parameter->parameterName ?? $mapping->payloadKey, 'unit' => $mapping->parameter->unit ?? ''],
                    'value' => (float) $value,
                    'timestamp' => $sensorModel->sensorTimestamp->format('d M Y H:i:s'),
                ];
                try {
                    broadcast(new IotSensorDataReceived($broadcastPayload));
                } catch (\Exception $e) {
                    Log::warning("Broadcast gagal untuk device {$device->deviceCode}: " . $e->getMessage());
                }
            }
        }
    }
}
