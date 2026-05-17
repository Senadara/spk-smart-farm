<?php

namespace Tests\Feature;

use App\Models\IotDevice;
use App\Models\IotDeviceLog;
use App\Models\IotConnectionConfig;
use App\Models\IotParameterMapping;
use App\Models\IotParameter;
use App\Models\IotSensorData;
use App\Models\IotProtocol;
use App\Models\Komoditas;
use App\Models\UnitBudidaya;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class IotManagementTest extends TestCase
{
    public function test_halaman_dashboard_iot_ditampilkan_dengan_benar(): void
    {
        $deviceQuery = Mockery::mock();
        $deviceQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'device-1',
                'deviceCode' => 'DEV-1',
                'deviceName' => 'Sensor A',
                'status' => 'active',
                'pollingInterval' => 300,
                'unitBudidaya' => (object) ['nama' => 'Kandang A'],
                'connectionConfig' => (object) ['protocol' => (object) ['protocolName' => 'MQTT']],
            ],
            (object) [
                'id' => 'device-2',
                'deviceCode' => 'DEV-2',
                'deviceName' => 'Sensor B',
                'status' => 'inactive',
                'pollingInterval' => 300,
                'unitBudidaya' => (object) ['nama' => 'Kandang B'],
                'connectionConfig' => (object) ['protocol' => (object) ['protocolName' => 'HTTP']],
            ],
            (object) [
                'id' => 'device-3',
                'deviceCode' => 'DEV-3',
                'deviceName' => 'Sensor C',
                'status' => 'maintenance',
                'pollingInterval' => 600,
                'unitBudidaya' => (object) ['nama' => 'Kandang C'],
                'connectionConfig' => (object) ['protocol' => (object) ['protocolName' => 'MQTT']],
            ],
        ]));
        $deviceMock = Mockery::mock('alias:' . IotDevice::class);
        $deviceMock->shouldReceive('with')->andReturn($deviceQuery);

        $logQuery = Mockery::mock();
        $logQuery->shouldReceive('latest')->andReturnSelf();
        $logQuery->shouldReceive('take')->andReturnSelf();
        $logQuery->shouldReceive('get')->andReturn(collect());
        $logMock = Mockery::mock('alias:' . IotDeviceLog::class);
        $logMock->shouldReceive('with')->andReturn($logQuery);

        $protocolMock = Mockery::mock('alias:' . IotProtocol::class);
        $protocolMock->shouldReceive('count')->andReturn(2);
        $parameterMock = Mockery::mock('alias:' . IotParameter::class);
        $parameterMock->shouldReceive('count')->andReturn(3);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);

        $response = $this->get('/iot');

        $response->assertStatus(200);
        $response->assertViewHasAll(['stats', 'devices', 'recentLogs']);
    }

    public function test_webhook_api_menangani_perangkat_yang_tidak_terdaftar_dengan_tepat(): void
    {
        $deviceQuery = Mockery::mock();
        $deviceQuery->shouldReceive('get')->andReturn(collect());
        $deviceMock = Mockery::mock('alias:' . IotDevice::class);
        $deviceMock->shouldReceive('with')->andReturn($deviceQuery);

        $payload = [
            'm2m:cin' => [
                'con' => '{"suhu": 32.5, "kelembaban": 80.2}'
            ]
        ];

        $response = $this->postJson('/iot/webhook/UNKNOWN_DEVICE_CODE', $payload);

        $response->assertStatus(404);
        $response->assertJsonPath('error', 'Device not found');
    }

    public function test_halaman_daftar_device_iot_menampilkan_semua_data_yang_dibutuhkan(): void
    {
        $deviceQuery = Mockery::mock();
        $deviceQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'device-1',
                'deviceCode' => 'DEV-1',
                'deviceName' => 'Sensor A',
                'status' => 'active',
                'pollingInterval' => 300,
                'installedAt' => null,
                'unitBudidayaId' => 'ub-1',
                'connectionConfigId' => 'conn-1',
                'unitBudidaya' => (object) ['id' => 'ub-1', 'nama' => 'Kandang A'],
                'connectionConfig' => (object) ['id' => 'conn-1', 'protocol' => (object) ['protocolName' => 'MQTT']],
            ],
        ]));
        $deviceMock = Mockery::mock('alias:' . IotDevice::class);
        $deviceMock->shouldReceive('with')->with(['unitBudidaya', 'connectionConfig.protocol'])->andReturn($deviceQuery);

        $configQuery = Mockery::mock();
        $configQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'conn-1',
                'protocol' => (object) ['protocolName' => 'MQTT'],
                'mqttBrokerUrl' => 'mqtt://broker',
                'baseUrl' => null,
            ],
        ]));
        $configMock = Mockery::mock('alias:' . IotConnectionConfig::class);
        $configMock->shouldReceive('with')->with('protocol')->andReturn($configQuery);

        $unitMock = Mockery::mock('alias:' . UnitBudidaya::class);
        $unitMock->shouldReceive('all')->andReturn(collect([(object) ['id' => 'ub-1', 'nama' => 'Kandang A']]));

        $parameterMock = Mockery::mock('alias:' . IotParameter::class);
        $parameterMock->shouldReceive('all')->andReturn(collect([
            (object) ['id' => 'param-1', 'parameterCode' => 'TEMP', 'parameterName' => 'Suhu', 'unit' => 'C'],
        ]));

        $mappingQuery = Mockery::mock();
        $mappingQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'map-1',
                'payloadKey' => 'temperature',
                'device' => (object) ['deviceCode' => 'DEV-1', 'deviceName' => 'Sensor A'],
                'parameter' => (object) ['parameterName' => 'Suhu'],
            ],
        ]));
        $mappingMock = Mockery::mock('alias:' . IotParameterMapping::class);
        $mappingMock->shouldReceive('with')->with(['device', 'parameter'])->andReturn($mappingQuery);

        $response = $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ])->get('/iot/devices');

        $response->assertStatus(200);
        $response->assertViewHasAll(['devices', 'unitBudidaya', 'connectionConfigs', 'parameters', 'mappings']);
    }

    public function test_halaman_konfigurasi_iot_menampilkan_data_konfigurasi(): void
    {
        $protocol = new class {
            public $id = 'proto-1';
            public $protocolName = 'MQTT';
            public $description = 'MQTT protocol';

            public function toJson(): string
            {
                return json_encode([
                    'id' => $this->id,
                    'protocolName' => $this->protocolName,
                    'description' => $this->description,
                ]);
            }
        };

        $protocolMock = Mockery::mock('alias:' . IotProtocol::class);
        $protocolMock->shouldReceive('all')->andReturn(collect([$protocol]));

        $connectionQuery = Mockery::mock();
        $connectionQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'conn-1',
                'protocol' => (object) ['protocolName' => 'MQTT'],
                'baseUrl' => null,
                'endpointPath' => '/data',
                'mqttBrokerUrl' => 'mqtt://broker',
                'mqttTopic' => 'farm/topic',
                'authType' => 'none',
            ],
        ]));
        $connectionMock = Mockery::mock('alias:' . IotConnectionConfig::class);
        $connectionMock->shouldReceive('with')->with('protocol')->andReturn($connectionQuery);

        $parameter = new class extends \ArrayObject {
            public function __construct()
            {
                parent::__construct([
                    'id' => 'param-1',
                    'parameterCode' => 'TEMP',
                    'parameterName' => 'Suhu',
                    'unit' => 'C',
                    'description' => 'Temperatur',
                ], \ArrayObject::ARRAY_AS_PROPS);
            }

            public function toJson(): string
            {
                return json_encode($this->getArrayCopy());
            }
        };

        $parameterMock = Mockery::mock('alias:' . IotParameter::class);
        $parameterMock->shouldReceive('all')->andReturn(collect([$parameter]));

        $commodity = new class extends \ArrayObject {
            public function __construct()
            {
                parent::__construct([
                    'id' => 'kom-1',
                    'nama' => 'Ayam Layer',
                ], \ArrayObject::ARRAY_AS_PROPS);
            }

            public function toJson(): string
            {
                return json_encode($this->getArrayCopy());
            }
        };

        $commodityParam = new class ($commodity, $parameter) {
            public $id = 'cp-1';
            public $minValue = 20;
            public $maxValue = 30;
            public $commodity;
            public $parameter;

            public function __construct($commodity, $parameter)
            {
                $this->commodity = $commodity;
                $this->parameter = $parameter;
            }

            public function toJson(): string
            {
                return json_encode([
                    'id' => $this->id,
                    'minValue' => $this->minValue,
                    'maxValue' => $this->maxValue,
                ]);
            }
        };

        $commodityQuery = Mockery::mock();
        $commodityQuery->shouldReceive('get')->andReturn(collect([$commodityParam]));
        $commodityMock = Mockery::mock('alias:' . Komoditas::class);
        $commodityMock->shouldReceive('all')->andReturn(collect([$commodity]));

        $commodityParamMock = Mockery::mock('alias:' . \App\Models\CommodityParameter::class);
        $commodityParamMock->shouldReceive('with')->with(['commodity', 'parameter'])->andReturn($commodityQuery);

        $response = $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ])->get('/iot/config');

        $response->assertStatus(200);
        $response->assertViewHasAll(['protocols', 'connectionConfigs', 'parameters', 'commodityParameters', 'commodities']);
    }

    public function test_halaman_monitoring_iot_menampilkan_data_sensor_dan_log(): void
    {
        $deviceMock = Mockery::mock('alias:' . IotDevice::class);
        $deviceMock->shouldReceive('all')->andReturn(collect([
            (object) ['id' => 'device-1', 'deviceCode' => 'DEV-1', 'deviceName' => 'Sensor A'],
        ]));

        $parameterMock = Mockery::mock('alias:' . IotParameter::class);
        $parameterMock->shouldReceive('all')->andReturn(collect([
            (object) ['id' => 'param-1', 'parameterName' => 'Suhu', 'unit' => 'C'],
        ]));

        $sensorQuery = Mockery::mock();
        $sensorQuery->shouldReceive('latest')->with('sensorTimestamp')->andReturnSelf();
        $sensorQuery->shouldReceive('take')->with(25)->andReturnSelf();
        $sensorQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'sensor-1',
                'value' => 29.5,
                'sensorTimestamp' => Carbon::parse('2026-05-15 10:00:00'),
                'device' => (object) ['deviceCode' => 'DEV-1', 'deviceName' => 'Sensor A'],
                'parameter' => (object) ['parameterName' => 'Suhu', 'unit' => 'C'],
            ],
        ]));
        $sensorMock = Mockery::mock('alias:' . IotSensorData::class);
        $sensorMock->shouldReceive('with')->with(['device', 'parameter'])->andReturn($sensorQuery);

        $logQuery = Mockery::mock();
        $logQuery->shouldReceive('latest')->with('createdAt')->andReturnSelf();
        $logQuery->shouldReceive('take')->with(25)->andReturnSelf();
        $logQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'id' => 'log-1',
                'logType' => 'INFO',
                'message' => 'Polling berhasil',
                'createdAt' => Carbon::parse('2026-05-15 10:05:00'),
                'device' => (object) ['deviceCode' => 'DEV-1', 'deviceName' => 'Sensor A'],
            ],
        ]));
        $logMock = Mockery::mock('alias:' . IotDeviceLog::class);
        $logMock->shouldReceive('with')->with('device')->andReturn($logQuery);

        $response = $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ])->get('/iot/monitoring');

        $response->assertStatus(200);
        $response->assertViewHasAll(['devices', 'parameters', 'sensorData', 'deviceLogs']);
    }
}
