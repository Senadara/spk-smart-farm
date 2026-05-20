<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IotWebhookTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    private function seedWebhookDependencies()
    {
        $jenisBudidayaId = (string) Str::uuid();
        DB::table('jenisBudidaya')->insert([
            'id' => $jenisBudidayaId, 'nama' => 'Ayam Layer', 'status' => 1, 'tipe' => 'hewan', 'isDeleted' => 0, 'createdAt' => now(), 'updatedAt' => now()
        ]);

        $barnId = (string) Str::uuid();
        DB::table('unitBudidaya')->insert([
            'id' => $barnId, 'jenisBudidayaId' => $jenisBudidayaId, 'nama' => 'Kandang X', 'isDeleted' => 0, 'createdAt' => now(), 'updatedAt' => now()
        ]);
        
        $protocolId = (string) Str::uuid();
        DB::table('iot_protocol')->insert(['id' => $protocolId, 'protocolName' => 'HTTP', 'createdAt' => now(), 'updatedAt' => now()]);
        
        $configId = (string) Str::uuid();
        DB::table('iot_connection_config')->insert(['id' => $configId, 'protocolId' => $protocolId, 'authType' => 'none', 'createdAt' => now(), 'updatedAt' => now()]);

        $deviceId = (string) Str::uuid();
        DB::table('iot_device')->insert([
            'id' => $deviceId,
            'deviceCode' => 'WEBHOOK-DEV-1',
            'unitBudidayaId' => $barnId,
            'connectionConfigId' => $configId,
            'status' => 'active',
            'createdAt' => now(), 'updatedAt' => now()
        ]);

        $parameterId = (string) Str::uuid();
        DB::table('iot_parameter')->insert([
            'id' => $parameterId,
            'parameterCode' => 'TEMP',
            'parameterName' => 'Suhu',
            'unit' => 'C',
            'createdAt' => now(), 'updatedAt' => now()
        ]);

        DB::table('iot_parameter_mapping')->insert([
            'id' => (string) Str::uuid(),
            'deviceId' => $deviceId,
            'parameterId' => $parameterId,
            'payloadKey' => 'temperature_value',
            'createdAt' => now(), 'updatedAt' => now()
        ]);

        return ['deviceCode' => 'WEBHOOK-DEV-1', 'deviceId' => $deviceId, 'parameterId' => $parameterId];
    }

    public function test_webhook_menyimpan_data_sensor_baru_dengan_format_standar(): void
    {
        $deps = $this->seedWebhookDependencies();

        $payload = [
            'temperature_value' => 31.5,
            'humidity_value' => 60.0
        ];

        $response = $this->postJson('/iot/webhook/' . $deps['deviceCode'], $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure(['message']); // Developer sering membalasnya dengan string message daripada true/false boolean success.
        
        $this->assertDatabaseHas('iot_sensor_data', [
            'deviceId' => $deps['deviceId'],
            'parameterId' => $deps['parameterId'],
            'value' => 31.5
        ]);

        $this->assertDatabaseHas('iot_device_log', [
            'deviceId' => $deps['deviceId'],
            'logType' => 'INFO'
        ]);
    }

    public function test_webhook_menangani_format_antares_m2m_cin_con(): void
    {
        $deps = $this->seedWebhookDependencies();

        $payload = [
            'm2m:cin' => [
                'con' => '{"temperature_value": 33.2}'
            ]
        ];

        $response = $this->postJson('/iot/webhook/' . $deps['deviceCode'], $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('iot_sensor_data', [
            'deviceId' => $deps['deviceId'],
            'value' => 33.2
        ]);
    }
}
