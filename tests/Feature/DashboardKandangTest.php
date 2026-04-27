<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DashboardKandangTest extends TestCase
{
    public function test_data_sensor_fuzzy_diteruskan_ke_view_dashboard_peternakan(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $response = $this->actingAs($user)->get('/peternakan');
        
        $response->assertStatus(200);
        $response->assertViewHas('invalid_fuzzy_sensors');
    }

    public function test_penanganan_error_mengembalikan_404_jika_id_kandang_tidak_valid(): void
    {
        $user = User::factory()->make(['id' => 1]);
        
        $response = $this->actingAs($user)->get('/peternakan/99999');
        
        $response->assertStatus(404);
    }

    public function test_data_perangkat_iot_diterjemahkan_dengan_benar_ke_view(): void
    {
        $user = User::factory()->make(['id' => 1]);
        $response = $this->actingAs($user)->get('/peternakan/1');
        
        $response->assertViewHas('all_iot_devices');
    }
}
