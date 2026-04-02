<?php

namespace App\Http\Controllers\Iot;

use App\Http\Controllers\Controller;
use App\Models\IotConnectionConfig;
use App\Models\IotDevice;
use App\Models\IotDeviceLog;
use App\Models\IotParameter;
use App\Models\IotParameterMapping;
use App\Models\IotProtocol;
use App\Models\IotSensorData;
use App\Models\Komoditas;
use App\Models\UnitBudidaya;
use App\Events\IotSensorDataReceived;
use Illuminate\Http\Request;

class IotController extends Controller
{
    // ─── IoT Dashboard ─────────────────────────────────────────────
    public function dashboard()
    {
        $devices = IotDevice::with(['unitBudidaya', 'connectionConfig.protocol'])->get();
        $recentLogs = IotDeviceLog::with('device')->latest('createdAt')->take(10)->get();

        $stats = [
            ['label' => 'Total Devices', 'value' => $devices->count(), 'color' => 'blue', 'icon' => 'cpu'],
            ['label' => 'Active', 'value' => $devices->where('status', 'active')->count(), 'color' => 'emerald', 'icon' => 'check'],
            ['label' => 'Inactive', 'value' => $devices->where('status', 'inactive')->count(), 'color' => 'gray', 'icon' => 'pause'],
            ['label' => 'Maintenance', 'value' => $devices->where('status', 'maintenance')->count(), 'color' => 'amber', 'icon' => 'wrench'],
            ['label' => 'Protocols', 'value' => IotProtocol::count(), 'color' => 'purple', 'icon' => 'link'],
            ['label' => 'Parameters', 'value' => IotParameter::count(), 'color' => 'rose', 'icon' => 'chart'],
        ];

        return view('iot.dashboard', [
            'stats' => $stats,
            'devices' => $devices,
            'recentLogs' => $recentLogs,
        ]);
    }

    // ─── Device Management ─────────────────────────────────────────
    public function devices()
    {
        return view('iot.devices', [
            'devices' => IotDevice::with(['unitBudidaya', 'connectionConfig.protocol'])->get(),
            'unitBudidaya' => UnitBudidaya::all(),
            'connectionConfigs' => IotConnectionConfig::with('protocol')->get(),
            'parameters' => IotParameter::all(),
            'mappings' => IotParameterMapping::with(['device', 'parameter'])->get(),
        ]);
    }

    public function storeDevice(Request $request)
    {
        $validated = $request->validate([
            'deviceCode' => 'required|string|max:100|unique:iot_device,deviceCode',
            'deviceName' => 'nullable|string|max:150',
            'unitBudidayaId' => 'required|string',
            'connectionConfigId' => 'required|string',
            'pollingInterval' => 'nullable|integer|min:10',
            'status' => 'required|in:active,inactive,maintenance',
            'installedAt' => 'nullable|date',
        ]);

        IotDevice::create($validated);
        return back()->with('success', 'Device berhasil didaftarkan.');
    }

    public function destroyDevice($id)
    {
        IotDevice::findOrFail($id)->delete();
        return back()->with('success', 'Device berhasil dihapus.');
    }

    public function storeMapping(Request $request)
    {
        $validated = $request->validate([
            'deviceId' => 'required|string|exists:iot_device,id',
            'parameterId' => 'required|string|exists:iot_parameter,id',
            'payloadKey' => 'required|string|max:100',
        ]);

        IotParameterMapping::create($validated);
        return back()->with('success', 'Mapping parameter berhasil ditambahkan.');
    }

    public function destroyMapping($id)
    {
        IotParameterMapping::findOrFail($id)->delete();
        return back()->with('success', 'Mapping parameter berhasil dihapus.');
    }

    // ─── Configuration (Tabbed) ────────────────────────────────────
    public function config()
    {
        return view('iot.config', [
            'protocols' => IotProtocol::all(),
            'connectionConfigs' => IotConnectionConfig::with('protocol')->get(),
            'parameters' => IotParameter::all(),
            'commodityParameters' => \App\Models\CommodityParameter::with(['commodity', 'parameter'])->get(),
            'commodities' => Komoditas::all(),
        ]);
    }

    public function storeProtocol(Request $request)
    {
        $validated = $request->validate([
            'protocolName' => 'required|string|max:50',
            'description' => 'nullable|string',
        ]);

        IotProtocol::create($validated);
        return back()->with('success', 'Protokol berhasil ditambahkan.');
    }

    public function storeConnection(Request $request)
    {
        $validated = $request->validate([
            'protocolId' => 'required|string|exists:iot_protocol,id',
            'baseUrl' => 'nullable|string|max:255',
            'endpointPath' => 'nullable|string|max:255',
            'mqttBrokerUrl' => 'nullable|string|max:255',
            'mqttTopic' => 'nullable|string|max:255',
            'authType' => 'nullable|in:none,api_key,bearer,basic',
            'authKey' => 'nullable|string|max:255',
        ]);

        IotConnectionConfig::create($validated);
        return back()->with('success', 'Konfigurasi koneksi berhasil ditambahkan.');
    }

    // ─── Monitoring ────────────────────────────────────────────────
    public function monitoring()
    {
        return view('iot.monitoring', [
            'devices' => IotDevice::all(),
            'parameters' => IotParameter::all(),
            'sensorData' => IotSensorData::with(['device', 'parameter'])->latest('sensorTimestamp')->take(100)->get(),
            'deviceLogs' => IotDeviceLog::with('device')->latest('createdAt')->take(50)->get(),
        ]);
    }

    public function storeParameter(Request $request) 
    {
        $validated = $request->validate([
            'parameterCode' => 'required|string|max:50|unique:iot_parameter',
            'parameterName' => 'required|string|max:100',
            'unit' => 'nullable|string|max:20',
            'description' => 'nullable|string'
        ]);

        IotParameter::create($validated);
        return back()->with('success', 'Parameter berhasil didaftarkan!');
    }

    // ─── Webhook (PUSH) ────────────────────────────────────────────
    public function handleWebhook(Request $request, $deviceCode)
    {
        $device = collect(IotDevice::with('parameterMappings.parameter')->get())
            ->firstWhere('deviceCode', $deviceCode);

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        $payload = $request->all();

        // Sama seperti logic PULL, kita mapping
        $dataTarget = $payload;

        // Fallback untuk struktur Antares
        if (isset($payload['m2m:cin']['con'])) {
            $con = $payload['m2m:cin']['con'];
            $dataTarget = is_string($con) ? json_decode($con, true) : $con;
        }

        $insertedCount = 0;
        foreach ($device->parameterMappings as $mapping) {
            $value = data_get($dataTarget, $mapping->payloadKey);

            if ($value !== null) {
                $sensorModel = IotSensorData::create([
                    'deviceId' => $device->id,
                    'parameterId' => $mapping->parameterId,
                    'value' => (float) $value,
                    'sensorTimestamp' => now(),
                ]);

                // PUSH Realtime
                $payloadData = [
                    'device' => ['deviceCode' => $device->deviceCode, 'deviceName' => $device->deviceName],
                    'parameter' => ['parameterName' => $mapping->parameter->parameterName ?? $mapping->payloadKey, 'unit' => $mapping->parameter->unit ?? ''],
                    'value' => (float) $value,
                    'timestamp' => $sensorModel->sensorTimestamp->format('d M Y H:i:s'),
                ];
                broadcast(new IotSensorDataReceived($payloadData));

                $insertedCount++;
            }
        }

        IotDeviceLog::create([
            'deviceId' => $device->id,
            'logType' => 'INFO',
            'message' => "Proses Webhook PUSH berhasil. ($insertedCount parameter tercatat)"
        ]);

        return response()->json(['message' => 'Data diterima', 'inserted' => $insertedCount]);
    }
}
