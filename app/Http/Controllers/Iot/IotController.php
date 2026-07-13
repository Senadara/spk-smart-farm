<?php

namespace App\Http\Controllers\Iot;

use App\Http\Controllers\Controller;
use App\Models\CommodityParameter;
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
use Illuminate\Support\Facades\Schema;

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
            'deviceCode'         => 'required|string|max:100|unique:iot_device,deviceCode',
            'deviceName'         => 'nullable|string|max:150',
            'unitBudidayaId'     => 'required|string|exists:unitBudidaya,id',
            'connectionConfigId' => 'required|string|exists:iot_connection_config,id',
            'pollingInterval'    => 'nullable|integer|min:10|max:86400',
            'status'             => 'required|in:active,inactive,maintenance',
            'installedAt'        => 'nullable|date',
        ], [
            'deviceCode.unique'            => 'Kode device sudah terdaftar.',
            'unitBudidayaId.exists'        => 'Unit budidaya tidak ditemukan.',
            'connectionConfigId.exists'    => 'Konfigurasi koneksi tidak ditemukan.',
            'pollingInterval.min'          => 'Interval polling minimal 10 detik.',
        ]);

        IotDevice::create($validated);
        return back()->with('success', 'Device berhasil didaftarkan.');
    }

    public function updateDevice(Request $request, $id)
    {
        $validated = $request->validate([
            'deviceCode'         => 'required|string|max:100|unique:iot_device,deviceCode,' . $id,
            'deviceName'         => 'nullable|string|max:150',
            'unitBudidayaId'     => 'required|string|exists:unitBudidaya,id',
            'connectionConfigId' => 'required|string|exists:iot_connection_config,id',
            'pollingInterval'    => 'nullable|integer|min:10|max:86400',
            'status'             => 'required|in:active,inactive,maintenance',
            'installedAt'        => 'nullable|date',
        ], [
            'deviceCode.unique' => 'Kode device sudah terdaftar.',
        ]);

        IotDevice::findOrFail($id)->update($validated);
        return back()->with('success', 'Device berhasil diperbarui.');
    }

    public function destroyDevice($id)
    {
        $device = IotDevice::findOrFail($id);

        // Cek relasi
        $mappingCount = IotParameterMapping::where('deviceId', $id)->count();
        $sensorCount  = IotSensorData::where('deviceId', $id)->count();

        if ($sensorCount > 0) {
            return back()->withErrors(['delete' => "Device '{$device->deviceCode}' memiliki {$sensorCount} data sensor. Hapus data sensor terlebih dahulu atau nonaktifkan device."]);
        }

        // Cascade delete mappings & logs
        IotParameterMapping::where('deviceId', $id)->delete();
        IotDeviceLog::where('deviceId', $id)->delete();
        $device->delete();

        return back()->with('success', "Device '{$device->deviceCode}' beserta {$mappingCount} mapping berhasil dihapus.");
    }

    // ─── Parameter Mappings ────────────────────────────────────────

    public function storeMapping(Request $request)
    {
        $validated = $request->validate([
            'deviceId'    => 'required|string|exists:iot_device,id',
            'parameterId' => 'required|string|exists:iot_parameter,id',
            'payloadKey'  => 'required|string|max:100',
        ], [
            'deviceId.exists'    => 'Device tidak ditemukan.',
            'parameterId.exists' => 'Parameter tidak ditemukan.',
            'payloadKey.required'=> 'Payload key wajib diisi.',
        ]);

        // Cek duplikat kombinasi
        if (IotParameterMapping::where('deviceId', $validated['deviceId'])->where('parameterId', $validated['parameterId'])->exists()) {
            return back()->withErrors(['mapping' => 'Parameter ini sudah di-mapping ke device yang dipilih.'])->withInput();
        }

        IotParameterMapping::create($validated);
        return back()->with('success', 'Mapping parameter berhasil ditambahkan.');
    }

    public function updateMapping(Request $request, $id)
    {
        $validated = $request->validate([
            'payloadKey' => 'required|string|max:100',
        ]);

        IotParameterMapping::findOrFail($id)->update($validated);
        return back()->with('success', 'Mapping parameter berhasil diperbarui.');
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
            'protocols'           => IotProtocol::all(),
            'connectionConfigs'   => IotConnectionConfig::with('protocol')->get(),
            'parameters'          => IotParameter::all(),
            'commodityParameters' => CommodityParameter::with(['commodity', 'parameter'])->get(),
            'commodities'         => Komoditas::all(),
        ]);
    }

    // ─── Protocols ─────────────────────────────────────────────────

    public function storeProtocol(Request $request)
    {
        $validated = $request->validate([
            'protocolName' => 'required|string|max:50|unique:iot_protocol,protocolName',
            'description'  => 'nullable|string|max:500',
        ], [
            'protocolName.unique' => 'Nama protokol sudah ada.',
        ]);

        IotProtocol::create($validated);
        return back()->with('success', 'Protokol berhasil ditambahkan.');
    }

    public function updateProtocol(Request $request, $id)
    {
        $validated = $request->validate([
            'protocolName' => 'required|string|max:50|unique:iot_protocol,protocolName,' . $id,
            'description'  => 'nullable|string|max:500',
        ], [
            'protocolName.unique' => 'Nama protokol sudah ada.',
        ]);

        IotProtocol::findOrFail($id)->update($validated);
        return back()->with('success', 'Protokol berhasil diperbarui.');
    }

    public function destroyProtocol($id)
    {
        $protocol = IotProtocol::findOrFail($id);
        $connCount = IotConnectionConfig::where('protocolId', $id)->count();

        if ($connCount > 0) {
            return back()->withErrors(['delete' => "Protokol '{$protocol->protocolName}' masih digunakan oleh {$connCount} konfigurasi koneksi. Hapus koneksi terlebih dahulu."]);
        }

        $protocol->delete();
        return back()->with('success', "Protokol '{$protocol->protocolName}' berhasil dihapus.");
    }

    // ─── Connections ───────────────────────────────────────────────

    public function storeConnection(Request $request)
    {
        $validated = $request->validate([
            'protocolId'    => 'required|string|exists:iot_protocol,id',
            'baseUrl'       => 'nullable|string|max:255',
            'endpointPath'  => 'nullable|string|max:255',
            'mqttBrokerUrl' => 'nullable|string|max:255',
            'mqttTopic'     => 'nullable|string|max:255',
            'authType'      => 'nullable|in:none,api_key,bearer,basic',
            'authKey'       => 'nullable|string|max:255',
            'headers'       => 'nullable|string',
        ], [
            'protocolId.exists' => 'Protokol tidak ditemukan.',
        ]);

        // Validasi: harus ada minimal satu endpoint
        if (empty($validated['baseUrl']) && empty($validated['mqttBrokerUrl'])) {
            return back()->withErrors(['connection' => 'Harus mengisi minimal Base URL atau MQTT Broker URL.'])->withInput();
        }

        // Parse headers JSON jika ada
        if (!empty($validated['headers'])) {
            $decoded = json_decode($validated['headers'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['headers' => 'Format headers harus JSON yang valid.'])->withInput();
            }
            $validated['headers'] = $decoded;
        }

        IotConnectionConfig::create($validated);
        return back()->with('success', 'Konfigurasi koneksi berhasil ditambahkan.');
    }

    public function updateConnection(Request $request, $id)
    {
        $validated = $request->validate([
            'protocolId'    => 'required|string|exists:iot_protocol,id',
            'baseUrl'       => 'nullable|string|max:255',
            'endpointPath'  => 'nullable|string|max:255',
            'mqttBrokerUrl' => 'nullable|string|max:255',
            'mqttTopic'     => 'nullable|string|max:255',
            'authType'      => 'nullable|in:none,api_key,bearer,basic',
            'authKey'       => 'nullable|string|max:255',
            'headers'       => 'nullable|string',
        ]);

        if (empty($validated['baseUrl']) && empty($validated['mqttBrokerUrl'])) {
            return back()->withErrors(['connection' => 'Harus mengisi minimal Base URL atau MQTT Broker URL.'])->withInput();
        }

        if (!empty($validated['headers'])) {
            $decoded = json_decode($validated['headers'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return back()->withErrors(['headers' => 'Format headers harus JSON yang valid.'])->withInput();
            }
            $validated['headers'] = $decoded;
        }

        IotConnectionConfig::findOrFail($id)->update($validated);
        return back()->with('success', 'Konfigurasi koneksi berhasil diperbarui.');
    }

    public function destroyConnection($id)
    {
        $conn = IotConnectionConfig::findOrFail($id);
        $deviceCount = IotDevice::where('connectionConfigId', $id)->count();

        if ($deviceCount > 0) {
            return back()->withErrors(['delete' => "Koneksi ini masih digunakan oleh {$deviceCount} device. Pindahkan device terlebih dahulu."]);
        }

        $conn->delete();
        return back()->with('success', 'Konfigurasi koneksi berhasil dihapus.');
    }

    // ─── Parameters ────────────────────────────────────────────────

    public function storeParameter(Request $request)
    {
        $validated = $request->validate([
            'parameterCode' => 'required|string|max:50|unique:iot_parameter,parameterCode',
            'parameterName' => 'required|string|max:100',
            'unit'          => 'nullable|string|max:20',
            'description'   => 'nullable|string|max:500',
        ], [
            'parameterCode.unique' => 'Kode parameter sudah terdaftar.',
        ]);

        IotParameter::create($validated);
        return back()->with('success', 'Parameter berhasil didaftarkan.');
    }

    public function updateParameter(Request $request, $id)
    {
        $validated = $request->validate([
            'parameterCode' => 'required|string|max:50|unique:iot_parameter,parameterCode,' . $id,
            'parameterName' => 'required|string|max:100',
            'unit'          => 'nullable|string|max:20',
            'description'   => 'nullable|string|max:500',
        ], [
            'parameterCode.unique' => 'Kode parameter sudah terdaftar.',
        ]);

        IotParameter::findOrFail($id)->update($validated);
        return back()->with('success', 'Parameter berhasil diperbarui.');
    }

    public function destroyParameter($id)
    {
        $param = IotParameter::findOrFail($id);
        $mappingCount   = IotParameterMapping::where('parameterId', $id)->count();
        $commodityCount = CommodityParameter::where('parameterId', $id)->count();

        if ($mappingCount > 0 || $commodityCount > 0) {
            return back()->withErrors(['delete' => "Parameter '{$param->parameterCode}' masih digunakan oleh {$mappingCount} mapping dan {$commodityCount} komoditas. Hapus referensi terlebih dahulu."]);
        }

        $param->delete();
        return back()->with('success', "Parameter '{$param->parameterCode}' berhasil dihapus.");
    }

    // ─── Commodity Parameters ──────────────────────────────────────

    public function storeCommodityParam(Request $request)
    {
        $validated = $request->validate([
            'commodityId'  => 'required|string|exists:komoditas,id',
            'parameterId'  => 'required|string|exists:iot_parameter,id',
            'minValue'     => 'nullable|numeric',
            'maxValue'     => 'nullable|numeric',
        ], [
            'commodityId.exists'  => 'Komoditas tidak ditemukan.',
            'parameterId.exists'  => 'Parameter tidak ditemukan.',
        ]);

        // Cek duplikat kombinasi
        if (CommodityParameter::where('commodityId', $validated['commodityId'])->where('parameterId', $validated['parameterId'])->exists()) {
            return back()->withErrors(['commodity' => 'Parameter ini sudah ditambahkan ke komoditas yang dipilih.'])->withInput();
        }

        // Validasi min < max
        if (isset($validated['minValue']) && isset($validated['maxValue']) && $validated['minValue'] >= $validated['maxValue']) {
            return back()->withErrors(['commodity' => 'Nilai minimum harus lebih kecil dari nilai maksimum.'])->withInput();
        }

        CommodityParameter::create($validated);
        return back()->with('success', 'Parameter komoditas berhasil ditambahkan.');
    }

    public function updateCommodityParam(Request $request, $id)
    {
        $cp = CommodityParameter::findOrFail($id);

        $validated = $request->validate([
            'minValue' => 'nullable|numeric',
            'maxValue' => 'nullable|numeric',
        ]);

        if (isset($validated['minValue']) && isset($validated['maxValue']) && $validated['minValue'] >= $validated['maxValue']) {
            return back()->withErrors(['commodity' => 'Nilai minimum harus lebih kecil dari nilai maksimum.'])->withInput();
        }

        $cp->update($validated);
        return back()->with('success', 'Parameter komoditas berhasil diperbarui.');
    }

    public function destroyCommodityParam($id)
    {
        CommodityParameter::findOrFail($id)->delete();
        return back()->with('success', 'Parameter komoditas berhasil dihapus.');
    }

    // ─── Monitoring ────────────────────────────────────────────────
    public function monitoring(Request $request)
    {
        $sensorQuery = IotSensorData::with(['device', 'parameter'])->latest('sensorTimestamp');
        if ($request->filled('sensor_device_id')) $sensorQuery->where('deviceId', $request->sensor_device_id);
        if ($request->filled('sensor_parameter_id')) $sensorQuery->where('parameterId', $request->sensor_parameter_id);
        if ($request->filled('sensor_date_from')) $sensorQuery->whereDate('sensorTimestamp', '>=', $request->sensor_date_from);
        if ($request->filled('sensor_date_to')) $sensorQuery->whereDate('sensorTimestamp', '<=', $request->sensor_date_to);

        $logQuery = IotDeviceLog::with('device')->latest('createdAt');
        if ($request->filled('log_device_id')) $logQuery->where('deviceId', $request->log_device_id);
        if ($request->filled('log_type')) $logQuery->where('logType', $request->log_type);
        if ($request->filled('log_date')) $logQuery->whereDate('createdAt', $request->log_date);

        return view('iot.monitoring', [
            'devices' => IotDevice::all(),
            'parameters' => IotParameter::all(),
            'sensorData' => $sensorQuery->take(25)->get(),
            'deviceLogs' => $logQuery->take(25)->get(),
        ]);
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
            if (is_string($con)) {
                $decoded = json_decode($con, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $dataTarget = $decoded;
                } else {
                    $cleanCon = trim(str_replace(["'", '"'], "", $con));
                    $dataTarget = is_numeric($cleanCon) ? (float)$cleanCon : $cleanCon;
                }
            } else {
                $dataTarget = $con;
            }
        }

        $insertedCount = 0;
        foreach ($device->parameterMappings as $mapping) {
            // Jika response hanyalah scalar nilai
            $value = is_array($dataTarget) && isset($dataTarget[$mapping->payloadKey])
                        ? data_get($dataTarget, $mapping->payloadKey)
                        : (is_array($dataTarget) ? data_get($dataTarget, $mapping->payloadKey) : $dataTarget);

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

        if ($insertedCount > 0) {
            $this->markDeviceOnline($device->id);
        } else {
            $this->markDeviceMiss($device->id, 'Webhook diterima tetapi tidak ada payload yang cocok dengan mapping parameter.');
        }

        return response()->json(['message' => 'Data diterima', 'inserted' => $insertedCount]);
    }

    private function markDeviceOnline(string $deviceId): void
    {
        if (! Schema::hasColumn('iot_device', 'lastSeenAt')) {
            return;
        }

        $device = IotDevice::find($deviceId);
        if (! $device) {
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

    private function markDeviceMiss(string $deviceId, string $reason): void
    {
        if (! Schema::hasColumn('iot_device', 'missedCount')) {
            return;
        }

        $device = IotDevice::find($deviceId);
        if (! $device || $device->status === 'maintenance') {
            return;
        }

        $missedCount = ((int) ($device->missedCount ?? 0)) + 1;
        $threshold = max(1, (int) ($device->offlineAfterMisses ?? 3));

        $device->update([
            'missedCount' => $missedCount,
            'lastMissedAt' => now(),
            'status' => $missedCount >= $threshold ? 'inactive' : $device->status,
        ]);

        IotDeviceLog::create([
            'deviceId' => $device->id,
            'logType' => $missedCount >= $threshold ? 'WARNING' : 'INFO',
            'message' => "{$reason} Miss {$missedCount}/{$threshold}.",
        ]);
    }
}
