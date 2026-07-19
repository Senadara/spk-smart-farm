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
use App\Models\UnitBudidaya;
use App\Events\IotSensorDataReceived;
use App\Services\Iot\IotPayloadIngestor;
use App\Services\Iot\MqttSubscriptionService;
use App\Services\LivestockMasterConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IotController extends Controller
{
    public function __construct(
        protected LivestockMasterConfigService $livestockMasterConfigService
    ) {}

    private const FIXED_PROTOCOLS = [
        'API' => [
            'label' => 'API / Antares',
            'description' => 'Laravel menarik data dari REST API.',
            'tone' => 'blue',
        ],
        'MQTT' => [
            'label' => 'MQTT',
            'description' => 'Laravel subscribe broker MQTT.',
            'tone' => 'violet',
        ],
    ];

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
            ['label' => 'Protocols', 'value' => IotProtocol::whereIn('protocolName', array_keys(self::FIXED_PROTOCOLS))->count(), 'color' => 'purple', 'icon' => 'link'],
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
        $this->ensureFixedProtocols();
        $connectionConfigs = IotConnectionConfig::with('protocol')
            ->withCount('devices')
            ->whereHas('protocol', fn ($query) => $query->whereIn('protocolName', array_keys(self::FIXED_PROTOCOLS)))
            ->get();
        $livestockCommodityIds = $this->livestockMasterConfigService->livestockCommodityIds();
        $visibleUnitBudidaya = $this->visibleUnitBudidayaOptions();
        $visibleUnitIds = $visibleUnitBudidaya->pluck('id')->filter()->values()->all();
        $deviceIds = IotDevice::query()
            ->when(! empty($visibleUnitIds), fn ($query) => $query->whereIn('unitBudidayaId', $visibleUnitIds), fn ($query) => $query->whereRaw('1 = 0'))
            ->pluck('id')
            ->values()
            ->all();

        return view('iot.devices', [
            'devices' => IotDevice::with(['unitBudidaya', 'connectionConfig.protocol'])
                ->when(! empty($visibleUnitIds), fn ($query) => $query->whereIn('unitBudidayaId', $visibleUnitIds), fn ($query) => $query->whereRaw('1 = 0'))
                ->get(),
            'unitBudidaya' => $visibleUnitBudidaya,
            'connectionConfigs' => $connectionConfigs,
            'protocols' => IotProtocol::whereIn('protocolName', array_keys(self::FIXED_PROTOCOLS))->get(),
            'protocolOptions' => $this->fixedProtocolOptions(),
            'parameters' => $this->livestockMasterConfigService->configuredIotParametersForCommodity(null, false),
            'mappings' => IotParameterMapping::with(['device', 'parameter'])
                ->when(! empty($deviceIds), fn ($query) => $query->whereIn('deviceId', $deviceIds), fn ($query) => $query->whereRaw('1 = 0'))
                ->get(),
            'commodityParameters' => CommodityParameter::with(['commodity', 'parameter'])
                ->when(! empty($livestockCommodityIds), fn ($query) => $query->whereIn('commodityId', $livestockCommodityIds), fn ($query) => $query->whereRaw('1 = 0'))
                ->get(),
            'commodities' => $this->livestockMasterConfigService->livestockCommodities(),
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
            'mqttTopic'          => 'nullable|string|max:255',
            'webhookToken'       => 'nullable|string|max:255',
            'status'             => 'required|in:active,inactive,maintenance',
            'installedAt'        => 'nullable|date',
        ], [
            'deviceCode.unique'            => 'Kode device sudah terdaftar.',
            'unitBudidayaId.exists'        => 'Unit budidaya tidak ditemukan.',
            'connectionConfigId.exists'    => 'Konfigurasi koneksi tidak ditemukan.',
            'pollingInterval.min'          => 'Interval polling minimal 10 detik.',
        ]);

        if (! $this->isVisibleLivestockUnit($validated['unitBudidayaId'])) {
            return back()->withErrors(['unitBudidayaId' => 'Device peternakan hanya bisa dipasang ke kandang bertipe hewan.'])->withInput();
        }

        IotDevice::create($this->normalizeDevicePayload($validated));
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
            'mqttTopic'          => 'nullable|string|max:255',
            'webhookToken'       => 'nullable|string|max:255',
            'status'             => 'required|in:active,inactive,maintenance',
            'installedAt'        => 'nullable|date',
        ], [
            'deviceCode.unique' => 'Kode device sudah terdaftar.',
        ]);

        if (! $this->isVisibleLivestockUnit($validated['unitBudidayaId'])) {
            return back()->withErrors(['unitBudidayaId' => 'Device peternakan hanya bisa dipasang ke kandang bertipe hewan.'])->withInput();
        }

        IotDevice::findOrFail($id)->update($this->normalizeDevicePayload($validated));
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

        $deviceUnitId = IotDevice::where('id', $validated['deviceId'])->value('unitBudidayaId');
        if (! $deviceUnitId || ! $this->isVisibleLivestockUnit($deviceUnitId)) {
            return back()->withErrors(['mapping' => 'Mapping hanya bisa dibuat untuk device kandang bertipe hewan.'])->withInput();
        }

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
        return redirect(route('iot.devices').'#advanced-iot-config');
    }

    // ─── Protocols ─────────────────────────────────────────────────

    public function storeProtocol(Request $request)
    {
        return back()->withErrors([
            'protocol' => 'Protokol IoT sudah dipakem: API/Antares dan MQTT.',
        ]);
    }

    public function updateProtocol(Request $request, $id)
    {
        return back()->withErrors([
            'protocol' => 'Protokol IoT tidak perlu diedit manual. Gunakan salah satu jalur pakem yang tersedia.',
        ]);
    }

    public function destroyProtocol($id)
    {
        return back()->withErrors([
            'protocol' => 'Protokol IoT pakem tidak bisa dihapus karena dipakai sebagai standar alur koneksi device.',
        ]);
    }

    // ─── Connections ───────────────────────────────────────────────

    public function storeConnection(Request $request)
    {
        $validated = $request->validate([
            'protocolId'    => 'required|string|exists:iot_protocol,id',
            'baseUrl'       => 'nullable|string|max:255',
            'endpointPath'  => 'nullable|string|max:255',
            'mqttBrokerUrl' => 'nullable|string|max:255',
            'mqttPort'      => 'nullable|integer|min:1|max:65535',
            'mqttTopic'     => 'nullable|string|max:255',
            'mqttClientId'  => 'nullable|string|max:150',
            'mqttUsername'  => 'nullable|string|max:150',
            'mqttPassword'  => 'nullable|string|max:255',
            'mqttUseTls'    => 'nullable|boolean',
            'mqttQos'       => 'nullable|integer|min:0|max:2',
            'mqttKeepAlive' => 'nullable|integer|min:5|max:65535',
            'authType'      => 'nullable|in:none,api_key,bearer,basic',
            'authKey'       => 'nullable|string|max:255',
            'headers'       => 'nullable|string',
        ], [
            'protocolId.exists' => 'Protokol tidak ditemukan.',
        ]);

        $protocol = $this->protocolCode($validated['protocolId']);
        $validationError = $this->validateConnectionByProtocol($protocol, $validated);
        if ($validationError) return $validationError;

        $validated['mqttUseTls'] = (bool) $request->boolean('mqttUseTls');
        $validated['mqttQos'] = (int) ($validated['mqttQos'] ?? 0);
        $validated['mqttKeepAlive'] = (int) ($validated['mqttKeepAlive'] ?? 60);
        $validated = $this->normalizeConnectionPayload($protocol, $validated);

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
            'mqttPort'      => 'nullable|integer|min:1|max:65535',
            'mqttTopic'     => 'nullable|string|max:255',
            'mqttClientId'  => 'nullable|string|max:150',
            'mqttUsername'  => 'nullable|string|max:150',
            'mqttPassword'  => 'nullable|string|max:255',
            'mqttUseTls'    => 'nullable|boolean',
            'mqttQos'       => 'nullable|integer|min:0|max:2',
            'mqttKeepAlive' => 'nullable|integer|min:5|max:65535',
            'authType'      => 'nullable|in:none,api_key,bearer,basic',
            'authKey'       => 'nullable|string|max:255',
            'headers'       => 'nullable|string',
        ]);

        $protocol = $this->protocolCode($validated['protocolId']);
        $validationError = $this->validateConnectionByProtocol($protocol, $validated);
        if ($validationError) return $validationError;

        $validated['mqttUseTls'] = (bool) $request->boolean('mqttUseTls');
        $validated['mqttQos'] = (int) ($validated['mqttQos'] ?? 0);
        $validated['mqttKeepAlive'] = (int) ($validated['mqttKeepAlive'] ?? 60);
        $validated = $this->normalizeConnectionPayload($protocol, $validated);

        if ($protocol === 'MQTT' && empty($validated['mqttPassword'])) {
            unset($validated['mqttPassword']);
        }

        if ($protocol === 'API' && empty($validated['authKey'])) {
            unset($validated['authKey']);
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

    public function testConnection($id, MqttSubscriptionService $mqtt)
    {
        $connection = IotConnectionConfig::with('protocol')->findOrFail($id);

        try {
            $stats = $mqtt->test($connection, 5);
            $message = "MQTT tersambung ke {$stats['broker']['host']}:{$stats['broker']['port']}. Topic aktif: ".implode(', ', $stats['topics']).'.';

            if (($stats['messages'] ?? 0) > 0) {
                $message .= " Pesan diterima: {$stats['messages']}, data tersimpan: {$stats['inserted']}.";
            } else {
                $message .= ' Belum ada pesan masuk selama window test 5 detik.';
            }

            return back()->with('success', $message);
        } catch (\Throwable $error) {
            return back()->withErrors(['connection' => 'Test MQTT gagal: '.$error->getMessage()]);
        }
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

        if (! $this->livestockMasterConfigService->isLivestockCommodity($validated['commodityId'])) {
            return back()->withErrors(['commodity' => 'Komoditas IoT pada menu peternakan hanya boleh bertipe hewan.'])->withInput();
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
    public function handleWebhook(Request $request, $deviceCode, IotPayloadIngestor $ingestor)
    {
        $device = IotDevice::with('parameterMappings.parameter')
            ->where('deviceCode', $deviceCode)
            ->first();

        if (!$device) {
            return response()->json(['error' => 'Device not found'], 404);
        }

        if ($device->webhookToken) {
            $token = $request->bearerToken() ?: $request->header('X-IoT-Token') ?: $request->query('token');
            if (! hash_equals((string) $device->webhookToken, (string) $token)) {
                return response()->json(['error' => 'Invalid webhook token'], 403);
            }
        }

        $result = $ingestor->ingest($device, $request->all(), 'webhook');

        return response()->json(['message' => 'Data diterima', 'inserted' => $result['inserted'], 'skipped' => $result['skipped']]);
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

    private function ensureFixedProtocols(): void
    {
        foreach (self::FIXED_PROTOCOLS as $code => $meta) {
            IotProtocol::firstOrCreate(
                ['protocolName' => $code],
                ['description' => $meta['description']]
            );
        }
    }

    private function visibleUnitBudidayaOptions()
    {
        $query = DB::table('unitBudidaya')
            ->join('jenisBudidaya', 'unitBudidaya.jenisBudidayaId', '=', 'jenisBudidaya.id')
            ->select('unitBudidaya.id', 'unitBudidaya.nama', 'unitBudidaya.lokasi', 'unitBudidaya.status', 'unitBudidaya.isDeleted', 'unitBudidaya.owner_id')
            ->where('jenisBudidaya.tipe', 'hewan')
            ->where('jenisBudidaya.isDeleted', 0)
            ->orderBy('unitBudidaya.nama');

        if (Schema::hasColumn('unitBudidaya', 'isDeleted')) {
            $query->where('unitBudidaya.isDeleted', 0);
        }

        if (Schema::hasColumn('unitBudidaya', 'status')) {
            $query->where('unitBudidaya.status', 1);
        }

        if (Schema::hasColumn('unitBudidaya', 'owner_id')) {
            $user = session('user', []);
            $ownerId = null;

            if (($user['role'] ?? null) === 'pjawab') {
                $ownerId = $user['id'] ?? null;
            } elseif (($user['role'] ?? null) === 'petugas') {
                $ownerId = $user['owner_id'] ?? null;
            }

            if ($ownerId) {
                $query->where(function ($tenantQuery) use ($ownerId) {
                    $tenantQuery->where('unitBudidaya.owner_id', $ownerId)
                        ->orWhereNull('unitBudidaya.owner_id');
                });
            }
        }

        return $query->get();
    }

    private function fixedProtocolOptions()
    {
        $protocols = IotProtocol::whereIn('protocolName', array_keys(self::FIXED_PROTOCOLS))
            ->get()
            ->keyBy('protocolName');

        return collect(self::FIXED_PROTOCOLS)
            ->map(function (array $meta, string $code) use ($protocols) {
                return [
                    'id' => $protocols[$code]->id ?? null,
                    'code' => $code,
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'tone' => $meta['tone'],
                ];
            })
            ->values();
    }

    private function isVisibleLivestockUnit(string $unitBudidayaId): bool
    {
        return $this->visibleUnitBudidayaOptions()
            ->contains(fn ($unit) => (string) $unit->id === (string) $unitBudidayaId);
    }

    private function protocolCode(string $protocolId): string
    {
        $name = IotProtocol::where('id', $protocolId)->value('protocolName') ?? '';
        return $this->normalizeProtocolName($name);
    }

    private function normalizeProtocolName(?string $name): string
    {
        $name = strtoupper(trim((string) $name));

        if (str_contains($name, 'MQTT')) return 'MQTT';
        if (str_contains($name, 'API') || str_contains($name, 'REST') || str_contains($name, 'ANTARES')) return 'API';
        if (str_contains($name, 'WEBHOOK') || str_contains($name, 'PUSH')) return 'WEBHOOK';

        return $name;
    }

    private function connectionProtocolCode(string $connectionConfigId): string
    {
        $connection = IotConnectionConfig::with('protocol')->find($connectionConfigId);

        return $this->normalizeProtocolName($connection?->protocol?->protocolName);
    }

    private function normalizeDevicePayload(array $payload): array
    {
        $protocol = $this->connectionProtocolCode($payload['connectionConfigId']);

        if ($protocol !== 'MQTT') {
            $payload['mqttTopic'] = null;
        }

        $payload['webhookToken'] = null;

        if ($protocol === 'API' && empty($payload['pollingInterval'])) {
            $payload['pollingInterval'] = 300;
        }

        return $payload;
    }

    private function validateConnectionByProtocol(string $protocol, array $payload)
    {
        if ($protocol === 'API' && empty($payload['baseUrl'])) {
            return back()->withErrors(['connection' => 'Koneksi API/Antares wajib mengisi Base URL.'])->withInput();
        }

        if ($protocol === 'MQTT' && empty($payload['mqttBrokerUrl'])) {
            return back()->withErrors(['connection' => 'Koneksi MQTT wajib mengisi MQTT Broker URL.'])->withInput();
        }

        if (! in_array($protocol, array_keys(self::FIXED_PROTOCOLS), true)) {
            return back()->withErrors(['connection' => 'Pilih salah satu protokol pakem: API/Antares atau MQTT.'])->withInput();
        }

        return null;
    }

    private function normalizeConnectionPayload(string $protocol, array $payload): array
    {
        if ($protocol === 'WEBHOOK') {
            $payload['baseUrl'] = null;
            $payload['endpointPath'] = null;
            $payload['mqttBrokerUrl'] = null;
            $payload['mqttPort'] = null;
            $payload['mqttTopic'] = null;
            $payload['mqttClientId'] = null;
            $payload['mqttUsername'] = null;
            $payload['mqttPassword'] = null;
            $payload['mqttUseTls'] = false;
            $payload['mqttQos'] = 0;
            $payload['mqttKeepAlive'] = 60;
            $payload['authType'] = 'none';
            $payload['authKey'] = null;
            $payload['headers'] = null;
        }

        if ($protocol === 'API') {
            $payload['mqttBrokerUrl'] = null;
            $payload['mqttPort'] = null;
            $payload['mqttTopic'] = null;
            $payload['mqttClientId'] = null;
            $payload['mqttUsername'] = null;
            $payload['mqttPassword'] = null;
            $payload['mqttUseTls'] = false;
            $payload['mqttQos'] = 0;
            $payload['mqttKeepAlive'] = 60;
        }

        if ($protocol === 'MQTT') {
            $payload['baseUrl'] = null;
            $payload['endpointPath'] = null;
            $payload['authType'] = 'none';
            $payload['authKey'] = null;
            $payload['headers'] = null;
        }

        return $payload;
    }
}
