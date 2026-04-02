<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\IotParameter;
use App\Models\IotDevice;
use App\Models\IotParameterMapping;
use Illuminate\Support\Facades\Artisan;

// 1. Create Parameters
$p1 = IotParameter::firstOrCreate(
    ['parameterCode' => 'TEMP'],
    ['parameterName' => 'Suhu', 'unit' => '°C']
);

$p2 = IotParameter::firstOrCreate(
    ['parameterCode' => 'HUMID'],
    ['parameterName' => 'Kelembaban', 'unit' => '%']
);

// 2. Fetch Device ANT-01
$device = IotDevice::where('deviceCode', 'ANT-01')->first();
if ($device) {
    // 3. Map Parameters using path templates
    IotParameterMapping::firstOrCreate(
        ['deviceId' => $device->id, 'parameterId' => $p1->id],
        ['payloadKey' => 'temp']
    );

    IotParameterMapping::firstOrCreate(
        ['deviceId' => $device->id, 'parameterId' => $p2->id],
        ['payloadKey' => 'humidity']
    );

    echo "Mappings injected to ANT-01.\n";
} else {
    echo "Device ANT-01 not found!\n";
}

// 4. Trigger Polling
echo "Triggering iot:poll...\n";
Artisan::call('iot:poll');
echo Artisan::output();
