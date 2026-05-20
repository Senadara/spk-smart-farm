<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardKandangTest extends TestCase
{
    use DatabaseTransactions;

    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user' => ['id' => 'admin-uuid-1', 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ];
    }

    /**
     * Skenario: Menampilkan halaman utama peternakan
     * 
     * Given pengguna login sebagai admin
     * When mengakses halaman '/peternakan'
     * Then status adalah 200 OK dan me-load view 'peternakan.dashboard' 
     */
    public function test_halaman_index_peternakan_dimuat_dengan_benar(): void
    {
        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/peternakan');

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewIs('peternakan.dashboard');
        $response->assertViewHasAll(['komoditas', 'barnEnvironment', 'spkResults', 'fuzzySensors']);
    }

    /**
     * Skenario: Halaman detail kandang dapat memuat ringkasan KPI dan list kandang saat data query tersedia
     */
    public function test_ringkasan_kandang_ditampilkan_saat_data_tersedia(): void
    {
        /* --- Arrange --- */
        $jenisBudidayaId = (string) Str::uuid();
        DB::table('jenisBudidaya')->insert([
            'id' => $jenisBudidayaId,
            'nama' => 'Ayam Petelur',
            'latin' => 'Gallus gallus domesticus',
            'status' => 1,
            'detail' => 'Data QA',
            'tipe' => 'hewan',
            'gambar' => null,
            'periodePanen' => null,
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $barnId = (string) Str::uuid();

        DB::table('unitBudidaya')->insert([
            'id' => $barnId,
            'jenisBudidayaId' => $jenisBudidayaId,
            'nama' => 'Kandang A',
            'lokasi' => 'Blok A',
            'tipe' => 'kolektif',
            'jumlah' => 1000,
            'status' => 1,
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $protocolId = (string) Str::uuid();
        DB::table('iot_protocol')->insert([
            'id' => $protocolId,
            'protocolName' => 'API',
            'description' => 'Protocol untuk QA test',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $connectionConfigId = (string) Str::uuid();
        DB::table('iot_connection_config')->insert([
            'id' => $connectionConfigId,
            'protocolId' => $protocolId,
            'baseUrl' => 'http://localhost',
            'endpointPath' => '/device',
            'mqttBrokerUrl' => null,
            'mqttTopic' => null,
            'authType' => 'none',
            'authKey' => null,
            'headers' => null,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/peternakan/' . $barnId);

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewIs('peternakan.show');
        $response->assertViewHasAll(['barn', 'sensors', 'kpi', 'iotDevice']);
    }

    /**
     * Skenario: Kandang yang tidak ditemukan tetap memakai fallback yang aman dari controller
     */
    public function test_halaman_kandang_tidak_ditemukan_memakai_fallback_controller(): void
    {
        /* --- Arrange --- */
        // Endpoint Peternakan menggunakan id yang tidak akan ditemukan dalam database
        $invalidBarnId = 'fake-uuid-not-exists-999';

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/peternakan/' . $invalidBarnId);

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewIs('peternakan.show');
        $response->assertViewHasAll(['barn', 'sensors', 'kpi', 'iotDevice']);
    }

    /**
     * Skenario: Menampilkan data IOT jika ada relasi perangkat di unit budidaya
     */
    public function test_data_perangkat_iot_diterjemahkan_dengan_benar_ke_view(): void
    {
        /* --- Arrange --- */
        $jenisBudidayaId = (string) Str::uuid();
        DB::table('jenisBudidaya')->insert([
            'id' => $jenisBudidayaId,
            'nama' => 'Ayam Petelur',
            'latin' => 'Gallus gallus domesticus',
            'status' => 1,
            'detail' => 'Data QA',
            'tipe' => 'hewan',
            'gambar' => null,
            'periodePanen' => null,
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $barnId = (string) Str::uuid();

        DB::table('unitBudidaya')->insert([
            'id' => $barnId,
            'jenisBudidayaId' => $jenisBudidayaId,
            'nama' => 'Kandang A IOT',
            'lokasi' => 'Blok B',
            'tipe' => 'kolektif',
            'jumlah' => 1000,
            'status' => 1,
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $protocolId = (string) Str::uuid();
        DB::table('iot_protocol')->insert([
            'id' => $protocolId,
            'protocolName' => 'API',
            'description' => 'Protocol untuk QA test',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        $connectionConfigId = (string) Str::uuid();
        DB::table('iot_connection_config')->insert([
            'id' => $connectionConfigId,
            'protocolId' => $protocolId,
            'baseUrl' => 'http://localhost',
            'endpointPath' => '/device',
            'mqttBrokerUrl' => null,
            'mqttTopic' => null,
            'authType' => 'none',
            'authKey' => null,
            'headers' => null,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        DB::table('iot_device')->insert([
            'id' => (string) Str::uuid(),
            'unitBudidayaId' => $barnId,
            'connectionConfigId' => $connectionConfigId,
            'deviceCode' => 'DEV-QA-001',
            'deviceName' => 'Sensor QA',
            'pollingInterval' => 300,
            'status' => 'active',
            'installedAt' => now(),
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get('/peternakan/' . $barnId);

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewIs('peternakan.show');
        $response->assertViewHas('iotDevice');
    }
}
