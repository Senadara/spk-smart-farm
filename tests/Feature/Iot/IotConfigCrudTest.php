<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IotConfigCrudTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user' => ['id' => 'admin-uuid-1', 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ];
    }

    public function test_admin_bisa_menambah_protokol_iot_baru(): void
    {
        $payload = [
            'protocolName' => 'AMQP',
            'description' => 'Advanced Message Queuing Protocol'
        ];

        $response = $this->withSession($this->authSession())->post('/iot/protocols', $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('iot_protocol', [
            'protocolName' => 'AMQP',
            'description' => 'Advanced Message Queuing Protocol'
        ]);
    }

    public function test_validasi_menambah_protokol_duplikat_ditolak(): void
    {
        DB::table('iot_protocol')->insert([
            'id' => (string) Str::uuid(),
            'protocolName' => 'MQTT-DUP',
            'createdAt' => now(), 'updatedAt' => now()
        ]);

        $payload = [
            'protocolName' => 'MQTT-DUP',
        ];

        $response = $this->withSession($this->authSession())->post('/iot/protocols', $payload);

        $response->assertSessionHasErrors('protocolName');
    }

    public function test_admin_bisa_membuat_mapping_parameter(): void
    {
        $jenisBudidayaId = (string) Str::uuid();
        DB::table('jenisBudidaya')->insert(['id' => $jenisBudidayaId, 'nama' => 'Ayam', 'status' => 1, 'tipe' => 'hewan', 'isDeleted' => 0, 'createdAt' => now(), 'updatedAt' => now()]);

        $barnId = (string) Str::uuid();
        DB::table('unitBudidaya')->insert(['id' => $barnId, 'jenisBudidayaId' => $jenisBudidayaId, 'nama' => 'Kandang A', 'tipe' => 'kolektif', 'status' => 1, 'isDeleted' => 0, 'createdAt' => now(), 'updatedAt' => now()]);

        $protocolId = (string) Str::uuid();
        DB::table('iot_protocol')->insert(['id' => $protocolId, 'protocolName' => 'HTTP', 'createdAt' => now(), 'updatedAt' => now()]);

        $configId = (string) Str::uuid();
        DB::table('iot_connection_config')->insert(['id' => $configId, 'protocolId' => $protocolId, 'authType' => 'none', 'createdAt' => now(), 'updatedAt' => now()]);

        $deviceId = (string) Str::uuid();
        DB::table('iot_device')->insert(['id' => $deviceId, 'deviceCode' => 'DEV-MAP', 'unitBudidayaId' => $barnId, 'connectionConfigId' => $configId, 'status' => 'active', 'createdAt' => now(), 'updatedAt' => now()]);

        $parameterId = (string) Str::uuid();
        DB::table('iot_parameter')->insert(['id' => $parameterId, 'parameterCode' => 'HUM', 'parameterName' => 'Kelembaban', 'unit' => '%', 'createdAt' => now(), 'updatedAt' => now()]);

        $payload = [
            'deviceId' => $deviceId,
            'parameterId' => $parameterId,
            'payloadKey' => 'hum_val'
        ];

        $response = $this->withSession($this->authSession())->post('/iot/mappings', $payload);

        $response->assertStatus(302);
        
        $this->assertDatabaseHas('iot_parameter_mapping', [
            'deviceId' => $deviceId,
            'parameterId' => $parameterId,
            'payloadKey' => 'hum_val'
        ]);
    }
}
