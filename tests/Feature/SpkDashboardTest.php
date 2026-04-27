<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SpkDashboardTest extends TestCase
{
    public function test_halaman_dashboard_spk_ditampilkan_dengan_benar()
    {
        $user = User::factory()->make(['id' => 1]);

        $response = $this->actingAs($user)->get('/spk-analysis');

        $response->assertStatus(200);
        $response->assertViewHas('invalid_spk_data');
    }

    public function test_dashboard_spk_menangani_id_riwayat_yang_tidak_valid_dengan_error()
    {
        $user = User::factory()->make(['id' => 1]);

        $response = $this->actingAs($user)->get('/spk-analysis?history_id=INVALID_ID_999');

        $response->assertStatus(500);
    }
}
