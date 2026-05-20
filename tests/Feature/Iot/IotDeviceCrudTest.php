<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IotDeviceCrudTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware; // Tambahkan WithoutMiddleware untuk bypass CSRF Mismatch 419 di environment unit testing

    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user' => ['id' => 'admin-uuid-1', 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ];
    }

    private function seedDependencies()
    {
        $jenisBudidayaId = (string) Str::uuid();
        DB::table('jenisBudidaya')->insert([
            'id' => $jenisBudidayaId, 'nama' => 'Ayam Layer', 'status' => 1, 'tipe' => 'hewan', 'isDeleted' => 0, 'createdAt' => now(), 'updatedAt' => now()
        ]);

        $barnId = (string) Str::uuid();
        DB::table('unitBudidaya')->insert([
            'id' => $barnId, 'jenisBudidayaId' => $jenisBudidayaId, 'nama' => 'Kandang A', 'tipe' => 'kolektif', 'status' => 1, 'isDeleted' => 0, 'createdAt' => now(), 'updatedAt' => now()
        ]);

        $protocolId = (string) Str::uuid();
        DB::table('iot_protocol')->insert([
            'id' => $protocolId, 'protocolName' => 'HTTP', 'createdAt' => now(), 'updatedAt' => now()
        ]);

        $connectionConfigId = (string) Str::uuid();
        DB::table('iot_connection_config')->insert([
            'id' => $connectionConfigId, 'protocolId' => $protocolId, 'baseUrl' => 'http://local', 'authType' => 'none', 'createdAt' => now(), 'updatedAt' => now()
        ]);

        return [
            'barnId' => $barnId,
            'connectionConfigId' => $connectionConfigId
        ];
    }

    public function test_admin_bisa_menyimpan_device_iot_baru(): void
    {
        $deps = $this->seedDependencies();

        $payload = [
            'deviceCode' => 'DEV-NEW-001',
            'deviceName' => 'Sensor Kelembaban',
            'unitBudidayaId' => $deps['barnId'],
            'connectionConfigId' => $deps['connectionConfigId'],
            'pollingInterval' => 60,
            'status' => 'active'
        ];

        $response = $this->withSession($this->authSession())->post('/iot/devices', $payload);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('iot_device', [
            'deviceCode' => 'DEV-NEW-001',
            'deviceName' => 'Sensor Kelembaban',
            'status' => 'active'
        ]);
    }

    public function test_validasi_kode_device_tidak_boleh_duplikat(): void
    {
        $deps = $this->seedDependencies();

        DB::table('iot_device')->insert([
            'id' => (string) Str::uuid(),
            'deviceCode' => 'DEV-DUP',
            'unitBudidayaId' => $deps['barnId'],
            'connectionConfigId' => $deps['connectionConfigId'],
            'status' => 'active',
            'createdAt' => now(), 'updatedAt' => now()
        ]);

        $payload = [
            'deviceCode' => 'DEV-DUP', // Duplikat
            'unitBudidayaId' => $deps['barnId'],
            'connectionConfigId' => $deps['connectionConfigId'],
            'status' => 'active'
        ];

        $response = $this->withSession($this->authSession())->post('/iot/devices', $payload);

        $response->assertSessionHasErrors('deviceCode');
    }

    public function test_admin_bisa_menghapus_device_iot(): void
    {
        $deps = $this->seedDependencies();
        
        $deviceId = (string) Str::uuid();
        DB::table('iot_device')->insert([
            'id' => $deviceId,
            'deviceCode' => 'DEV-DELETE',
            'unitBudidayaId' => $deps['barnId'],
            'connectionConfigId' => $deps['connectionConfigId'],
            'status' => 'inactive',
            'createdAt' => now(), 'updatedAt' => now()
        ]);

        $response = $this->withSession($this->authSession())->delete('/iot/devices/' . $deviceId);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('iot_device', [
            'id' => $deviceId
        ]);
    }
}
