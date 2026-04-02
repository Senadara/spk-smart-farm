<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IotDevice;
use App\Models\IotSensorData;
use App\Models\IotDeviceLog;
use App\Models\IotParameterMapping;
use Illuminate\Support\Facades\Http;

echo "=== DEBUG IoT POLLING ===\n\n";

// 1. Cek devices
$devices = IotDevice::with(['connectionConfig.protocol', 'parameterMappings.parameter'])
    ->where('status', 'active')
    ->whereHas('connectionConfig', fn($q) => $q->whereNotNull('baseUrl'))
    ->get();

echo "Active devices with baseUrl: " . $devices->count() . "\n\n";

foreach ($devices as $device) {
    echo "--- Device: {$device->deviceCode} ---\n";
    echo "  Status: {$device->status}\n";
    
    $config = $device->connectionConfig;
    echo "  baseUrl: {$config->baseUrl}\n";
    echo "  endpointPath: {$config->endpointPath}\n";
    echo "  authType: {$config->authType}\n";
    echo "  authKey: " . substr($config->authKey ?? 'null', 0, 10) . "...\n";
    echo "  headers: " . json_encode($config->headers) . "\n";
    echo "  Protocol: " . ($config->protocol->protocolName ?? 'N/A') . "\n";
    echo "  Parameter Mappings: " . $device->parameterMappings->count() . "\n";
    
    foreach ($device->parameterMappings as $mapping) {
        echo "    - payloadKey: {$mapping->payloadKey}";
        echo " -> parameter: " . ($mapping->parameter->parameterName ?? 'N/A');
        echo " (parameterId: {$mapping->parameterId})\n";
    }

    // Try actual HTTP request
    $baseUrl = rtrim($config->baseUrl, '/');
    $endpointTemplate = ltrim($config->endpointPath, '/');
    
    $headers = is_array($config->headers) ? $config->headers : json_decode($config->headers ?? '{}', true);
    if ($config->authType === 'api_key') {
        if (!isset($headers['X-M2M-Origin'])) {
            $headers['X-M2M-Origin'] = $config->authKey;
        }
    }
    
    // Check if fragmented
    if (str_contains($endpointTemplate, '{payloadKey}')) {
        echo "\n  [FRAGMENTED mode]\n";
        foreach ($device->parameterMappings as $mapping) {
            $url = $baseUrl . '/' . str_replace('{payloadKey}', $mapping->payloadKey, $endpointTemplate);
            echo "  Calling: $url\n";
            try {
                $response = Http::withHeaders($headers)->acceptJson()->timeout(10)->get($url);
                echo "  HTTP Status: {$response->status()}\n";
                echo "  Response (first 500 chars): " . substr($response->body(), 0, 500) . "\n";
                
                $json = $response->json();
                echo "  JSON parsed: " . ($json !== null ? 'YES' : 'NO (null!)') . "\n";
                
                if ($json && isset($json['m2m:cin']['con'])) {
                    $con = $json['m2m:cin']['con'];
                    echo "  m2m:cin.con: " . (is_string($con) ? $con : json_encode($con)) . "\n";
                    $dataTarget = is_string($con) ? json_decode($con, true) : $con;
                    echo "  Parsed dataTarget: " . json_encode($dataTarget) . "\n";
                    
                    $value = is_array($dataTarget) && isset($dataTarget[$mapping->payloadKey]) 
                        ? $dataTarget[$mapping->payloadKey] 
                        : $dataTarget;
                    echo "  Extracted value: " . json_encode($value) . "\n";
                    echo "  is_numeric: " . (is_numeric($value) ? 'YES' : 'NO') . "\n";
                }
            } catch (\Exception $e) {
                echo "  ERROR: " . $e->getMessage() . "\n";
            }
            echo "\n";
        }
    } else {
        $url = $baseUrl . '/' . $endpointTemplate;
        echo "\n  [SINGLE mode] Calling: $url\n";
        try {
            $response = Http::withHeaders($headers)->acceptJson()->timeout(10)->get($url);
            echo "  HTTP Status: {$response->status()}\n";
            echo "  Response (first 500 chars): " . substr($response->body(), 0, 500) . "\n";
            $json = $response->json();
            echo "  JSON parsed: " . ($json !== null ? 'YES' : 'NO (null!)') . "\n";
        } catch (\Exception $e) {
            echo "  ERROR: " . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

// 2. Cek latest sensor data
echo "=== Latest Sensor Data ===\n";
$latestData = IotSensorData::orderBy('sensorTimestamp', 'desc')->take(5)->get();
echo "Total records: " . IotSensorData::count() . "\n";
foreach ($latestData as $d) {
    echo "  [{$d->sensorTimestamp}] value={$d->value}\n";
}

// 3. Cek latest logs
echo "\n=== Latest Device Logs ===\n";
$logs = IotDeviceLog::orderBy('createdAt', 'desc')->take(5)->get();
foreach ($logs as $log) {
    echo "  [{$log->createdAt}] {$log->logType}: {$log->message}\n";
}
