<?php

namespace App\Http\Controllers\Iot;

use App\Http\Controllers\Controller;

class IotController extends Controller
{
    // ─── IoT Dashboard ─────────────────────────────────────────────
    public function dashboard()
    {
        return view('iot.dashboard', [
            'stats' => $this->getStats(),
            'devices' => $this->getAllDevices(),
            'recentLogs' => $this->getRecentLogs(),
        ]);
    }

    // ─── Device Management ─────────────────────────────────────────
    public function devices()
    {
        return view('iot.devices', [
            'devices' => $this->getAllDevices(),
            'unitBudidaya' => $this->getUnitBudidaya(),
            'connectionConfigs' => $this->getConnectionConfigs(),
            'parameters' => $this->getParameters(),
            'mappings' => $this->getMappings(),
        ]);
    }

    // ─── Configuration (Tabbed) ────────────────────────────────────
    public function config()
    {
        return view('iot.config', [
            'protocols' => $this->getProtocols(),
            'connectionConfigs' => $this->getConnectionConfigs(),
            'parameters' => $this->getParameters(),
            'commodityParameters' => $this->getCommodityParameters(),
            'commodities' => $this->getCommodities(),
        ]);
    }

    // ─── Monitoring ────────────────────────────────────────────────
    public function monitoring()
    {
        return view('iot.monitoring', [
            'devices' => $this->getAllDevices(),
            'parameters' => $this->getParameters(),
            'sensorData' => $this->getSensorData(),
            'deviceLogs' => $this->getDeviceLogs(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // DUMMY DATA METHODS
    // ═══════════════════════════════════════════════════════════════

    private function getStats(): array
    {
        return [
            ['label' => 'Total Devices', 'value' => '12', 'color' => 'blue', 'icon' => 'cpu'],
            ['label' => 'Active', 'value' => '9', 'color' => 'emerald', 'icon' => 'check'],
            ['label' => 'Inactive', 'value' => '2', 'color' => 'gray', 'icon' => 'pause'],
            ['label' => 'Maintenance', 'value' => '1', 'color' => 'amber', 'icon' => 'wrench'],
            ['label' => 'Protocols', 'value' => '3', 'color' => 'purple', 'icon' => 'link'],
            ['label' => 'Parameters', 'value' => '8', 'color' => 'rose', 'icon' => 'chart'],
        ];
    }

    private function getProtocols(): array
    {
        return [
            ['id' => 'p1', 'protocolName' => 'MQTT', 'description' => 'Message Queuing Telemetry Transport untuk komunikasi real-time sensor'],
            ['id' => 'p2', 'protocolName' => 'REST API', 'description' => 'HTTP REST API untuk polling data sensor berkala'],
            ['id' => 'p3', 'protocolName' => 'WebSocket', 'description' => 'Koneksi persistent dua arah untuk streaming data'],
        ];
    }

    private function getConnectionConfigs(): array
    {
        return [
            [
                'id' => 'cc1',
                'protocolId' => 'p1',
                'protocolName' => 'MQTT',
                'mqttBrokerUrl' => 'mqtts://hivemq.cloud:8883',
                'mqttTopic' => 'farm/sensor/#',
                'baseUrl' => null,
                'endpointPath' => null,
                'authType' => 'basic',
                'authKey' => '***',
            ],
            [
                'id' => 'cc2',
                'protocolId' => 'p2',
                'protocolName' => 'REST API',
                'mqttBrokerUrl' => null,
                'mqttTopic' => null,
                'baseUrl' => 'https://platform.antares.id',
                'endpointPath' => '/api/v2/devices',
                'authType' => 'api_key',
                'authKey' => '***',
            ],
        ];
    }

    private function getUnitBudidaya(): array
    {
        return [
            ['id' => 'ub1', 'nama' => 'Kandang A'],
            ['id' => 'ub2', 'nama' => 'Kandang B'],
            ['id' => 'ub3', 'nama' => 'Kandang C'],
            ['id' => 'ub4', 'nama' => 'Kandang D'],
            ['id' => 'ub5', 'nama' => 'Kandang E'],
            ['id' => 'ub6', 'nama' => 'Kandang F'],
        ];
    }

    private function getAllDevices(): array
    {
        return [
            ['id' => 'd1', 'deviceCode' => 'DHT22-KA-01', 'deviceName' => 'Sensor Suhu Kandang A', 'unitBudidaya' => 'Kandang A', 'connectionConfig' => 'MQTT — HiveMQ', 'status' => 'active', 'pollingInterval' => 300, 'installedAt' => '2025-12-01'],
            ['id' => 'd2', 'deviceCode' => 'DHT22-KB-01', 'deviceName' => 'Sensor Suhu Kandang B', 'unitBudidaya' => 'Kandang B', 'connectionConfig' => 'MQTT — HiveMQ', 'status' => 'active', 'pollingInterval' => 300, 'installedAt' => '2025-12-01'],
            ['id' => 'd3', 'deviceCode' => 'MQ135-KA-01', 'deviceName' => 'Sensor Amonia Kandang A', 'unitBudidaya' => 'Kandang A', 'connectionConfig' => 'MQTT — HiveMQ', 'status' => 'active', 'pollingInterval' => 600, 'installedAt' => '2025-12-15'],
            ['id' => 'd4', 'deviceCode' => 'LDR-KC-01', 'deviceName' => 'Sensor Cahaya Kandang C', 'unitBudidaya' => 'Kandang C', 'connectionConfig' => 'REST API — Antares', 'status' => 'inactive', 'pollingInterval' => 900, 'installedAt' => '2026-01-10'],
            ['id' => 'd5', 'deviceCode' => 'DHT22-KD-01', 'deviceName' => 'Sensor Suhu Kandang D', 'unitBudidaya' => 'Kandang D', 'connectionConfig' => 'MQTT — HiveMQ', 'status' => 'maintenance', 'pollingInterval' => 300, 'installedAt' => '2026-02-01'],
        ];
    }

    private function getParameters(): array
    {
        return [
            ['id' => 'pr1', 'parameterCode' => 'TEMP', 'parameterName' => 'Temperature', 'unit' => '°C', 'description' => 'Suhu udara dalam kandang'],
            ['id' => 'pr2', 'parameterCode' => 'HUM', 'parameterName' => 'Humidity', 'unit' => '%', 'description' => 'Kelembapan relatif udara'],
            ['id' => 'pr3', 'parameterCode' => 'NH3', 'parameterName' => 'Ammonia', 'unit' => 'ppm', 'description' => 'Kadar gas amonia'],
            ['id' => 'pr4', 'parameterCode' => 'LUX', 'parameterName' => 'Light Intensity', 'unit' => 'lux', 'description' => 'Intensitas cahaya'],
            ['id' => 'pr5', 'parameterCode' => 'SOUND', 'parameterName' => 'Sound Level', 'unit' => 'dB', 'description' => 'Tingkat kebisingan'],
        ];
    }

    private function getMappings(): array
    {
        return [
            ['id' => 'm1', 'deviceId' => 'd1', 'deviceName' => 'DHT22-KA-01', 'parameterId' => 'pr1', 'parameterName' => 'Temperature', 'payloadKey' => 'temperature'],
            ['id' => 'm2', 'deviceId' => 'd1', 'deviceName' => 'DHT22-KA-01', 'parameterId' => 'pr2', 'parameterName' => 'Humidity', 'payloadKey' => 'humidity'],
            ['id' => 'm3', 'deviceId' => 'd3', 'deviceName' => 'MQ135-KA-01', 'parameterId' => 'pr3', 'parameterName' => 'Ammonia', 'payloadKey' => 'nh3_ppm'],
        ];
    }

    private function getCommodities(): array
    {
        return [
            ['id' => 'k1', 'nama' => 'Ayam Petelur'],
            ['id' => 'k2', 'nama' => 'Melon'],
        ];
    }

    private function getCommodityParameters(): array
    {
        return [
            ['id' => 'cp1', 'commodityName' => 'Ayam Petelur', 'parameterName' => 'Temperature', 'minValue' => 20, 'maxValue' => 28],
            ['id' => 'cp2', 'commodityName' => 'Ayam Petelur', 'parameterName' => 'Humidity', 'minValue' => 50, 'maxValue' => 70],
            ['id' => 'cp3', 'commodityName' => 'Ayam Petelur', 'parameterName' => 'Ammonia', 'minValue' => 0, 'maxValue' => 15],
            ['id' => 'cp4', 'commodityName' => 'Melon', 'parameterName' => 'Temperature', 'minValue' => 25, 'maxValue' => 35],
        ];
    }

    private function getSensorData(): array
    {
        $data = [];
        $baseTime = now();
        for ($i = 0; $i < 20; $i++) {
            $data[] = [
                'deviceName' => $i % 2 === 0 ? 'DHT22-KA-01' : 'MQ135-KA-01',
                'parameterName' => $i % 2 === 0 ? 'Temperature' : 'Ammonia',
                'value' => $i % 2 === 0 ? round(24 + mt_rand(-20, 30) / 10, 1) : round(10 + mt_rand(0, 80) / 10, 1),
                'unit' => $i % 2 === 0 ? '°C' : 'ppm',
                'sensorTimestamp' => $baseTime->copy()->subMinutes($i * 15)->format('Y-m-d H:i'),
            ];
        }
        return $data;
    }

    private function getDeviceLogs(): array
    {
        return [
            ['deviceName' => 'DHT22-KA-01', 'logType' => 'INFO', 'message' => 'Data received successfully', 'createdAt' => now()->subMinutes(5)->format('Y-m-d H:i')],
            ['deviceName' => 'MQ135-KA-01', 'logType' => 'INFO', 'message' => 'Data received successfully', 'createdAt' => now()->subMinutes(10)->format('Y-m-d H:i')],
            ['deviceName' => 'LDR-KC-01', 'logType' => 'WARNING', 'message' => 'Connection timeout, retrying...', 'createdAt' => now()->subMinutes(30)->format('Y-m-d H:i')],
            ['deviceName' => 'DHT22-KD-01', 'logType' => 'ERROR', 'message' => 'Device unreachable — check wiring', 'createdAt' => now()->subHours(1)->format('Y-m-d H:i')],
            ['deviceName' => 'DHT22-KA-01', 'logType' => 'INFO', 'message' => 'Force save triggered (30 min interval)', 'createdAt' => now()->subHours(2)->format('Y-m-d H:i')],
            ['deviceName' => 'LDR-KC-01', 'logType' => 'ERROR', 'message' => 'Authentication failed — invalid API key', 'createdAt' => now()->subHours(3)->format('Y-m-d H:i')],
        ];
    }

    private function getRecentLogs(): array
    {
        return array_slice($this->getDeviceLogs(), 0, 5);
    }
}
