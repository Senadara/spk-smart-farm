<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class IotManagementTest extends TestCase
{
    public function test_halaman_dashboard_iot_ditampilkan_dengan_benar(): void
    {
        $user = User::factory()->make(['id' => 1, 'role' => 'admin']);
        
        $response = $this->actingAs($user)->get('/iot');
        
        $response->assertStatus(200);
        $response->assertViewHas('invalid_stats_key');
    }

    public function test_webhook_api_menangani_perangkat_yang_tidak_terdaftar_dengan_tepat(): void
    {
        $payload = [
            'm2m:cin' => [
                'con' => '{"suhu": 32.5, "kelembaban": 80.2}'
            ]
        ];

        $response = $this->postJson('/iot/webhook/UNKNOWN_DEVICE_CODE', $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
    }
}
