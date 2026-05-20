<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IotManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user' => ['id' => 'admin-uuid-1', 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ];
    }

    private function seedIotBaseData()
    {
        $jenisBudidayaId = (string) Str::uuid();
        DB::table('jenisBudidaya')->insert([
            'id' => $jenisBudidayaId,
            'nama' => 'Ayam Layer',
            'latin' => 'Gallus',
            'status' => 1,
            'tipe' => 'hewan',
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $barnId = (string) Str::uuid();
        DB::table('unitBudidaya')->insert([
            'id' => $barnId,
            'jenisBudidayaId' => $jenisBudidayaId,
            'nama' => 'Kandang Layer A',
            'lokasi' => 'Blok IoT',
            'tipe' => 'kolektif',
            'status' => 1,
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $protocolId = (string) Str::uuid();
        DB::table('iot_protocol')->insert([
            'id' => $protocolId,
            'protocolName' => 'MQTT-QA',
            'description' => 'Test Protocol',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $connectionConfigId = (string) Str::uuid();
        DB::table('iot_connection_config')->insert([
            'id' => $connectionConfigId,
            'protocolId' => $protocolId,
            'baseUrl' => 'http://localhost',
            'endpointPath' => '/data',
            'authType' => 'none',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $deviceId = (string) Str::uuid();
        DB::table('iot_device')->insert([
            'id' => $deviceId,
            'unitBudidayaId' => $barnId,
            'connectionConfigId' => $connectionConfigId,
            'deviceCode' => 'DEV-QA-001',
            'deviceName' => 'Sensor Suhu Layer',
            'pollingInterval' => 300,
            'status' => 'active',
            'installedAt' => now(),
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $parameterId = (string) Str::uuid();
        DB::table('iot_parameter')->insert([
            'id' => $parameterId,
            'parameterCode' => 'TEMP_QA',
            'parameterName' => 'Suhu Udara QA',
            'unit' => 'C',
            'description' => 'Sensor QA',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $satuanId = (string) Str::uuid();
        DB::table('satuan')->insert([
            'id' => $satuanId,
            'nama' => 'Kilogram',
            'lambang' => 'kg',
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        return [
            'jenisBudidayaId' => $jenisBudidayaId,
            'barnId' => $barnId,
            'protocolId' => $protocolId,
            'connectionConfigId' => $connectionConfigId,
            'deviceId' => $deviceId,
            'parameterId' => $parameterId,
            'satuanId' => $satuanId,
        ];
    }

    public function test_halaman_dashboard_iot_ditampilkan_dengan_benar(): void
    {
        /* --- Arrange --- */
        $data = $this->seedIotBaseData();

        DB::table('iot_device_log')->insert([
            'id' => (string) Str::uuid(),
            'deviceId' => $data['deviceId'],
            'logType' => 'INFO',
            'message' => 'System started',
            'createdAt' => now(),
        ]);

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/iot');

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewHasAll(['stats', 'devices', 'recentLogs']);
    }

    public function test_webhook_api_menangani_perangkat_yang_tidak_terdaftar_dengan_tepat(): void
    {
        /* --- Arrange --- */
        $payload = [
            'm2m:cin' => [
                'con' => '{"suhu": 32.5, "kelembaban": 80.2}'
            ]
        ];

        /* --- Act --- */
        $response = $this->postJson('/iot/webhook/UNKNOWN_DEVICE_CODE_123', $payload);

        /* --- Assert --- */
        $response->assertStatus(404);
        $response->assertJsonPath('error', 'Device not found');
    }

    public function test_halaman_daftar_device_iot_menampilkan_semua_data_yang_dibutuhkan(): void
    {
        /* --- Arrange --- */
        $data = $this->seedIotBaseData();

        DB::table('iot_parameter_mapping')->insert([
            'id' => (string) Str::uuid(),
            'deviceId' => $data['deviceId'],
            'parameterId' => $data['parameterId'],
            'payloadKey' => 'temperature',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/iot/devices');

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewHasAll(['devices', 'unitBudidaya', 'connectionConfigs', 'parameters', 'mappings']);
    }

    public function test_halaman_konfigurasi_iot_menampilkan_data_konfigurasi(): void
    {
        /* --- Arrange --- */
        $data = $this->seedIotBaseData();

        $komoditasId = (string) Str::uuid();
        DB::table('komoditas')->insert([
            'id' => $komoditasId,
            'jenisBudidayaId' => $data['jenisBudidayaId'],
            'satuanId' => $data['satuanId'],
            'nama' => 'Komoditas Layer QA',
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        DB::table('commodity_parameter')->insert([
            'id' => (string) Str::uuid(),
            'commodityId' => $komoditasId,
            'parameterId' => $data['parameterId'],
            'minValue' => 20.0,
            'maxValue' => 30.0,
            'createdAt' => now(),
        ]);

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/iot/config');

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewHasAll(['protocols', 'connectionConfigs', 'parameters', 'commodityParameters', 'commodities']);
    }

    public function test_halaman_monitoring_iot_menampilkan_data_sensor_dan_log(): void
    {
        /* --- Arrange --- */
        $data = $this->seedIotBaseData();

        DB::table('iot_sensor_data')->insert([
            'id' => (string) Str::uuid(),
            'deviceId' => $data['deviceId'],
            'parameterId' => $data['parameterId'],
            'value' => 25.5,
            'sensorTimestamp' => now(),
            'createdAt' => now(),
        ]);

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/iot/monitoring');

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewHasAll(['devices', 'parameters', 'sensorData', 'deviceLogs']);
    }
}
