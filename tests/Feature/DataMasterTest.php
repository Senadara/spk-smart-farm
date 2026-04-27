<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DataMasterTest extends TestCase
{
    public function test_halaman_data_master_ditampilkan_dengan_benar(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $response = $this->actingAs($user)->get('/data-master');

        $response->assertStatus(200);
        $response->assertViewHas('invalid_data_master_var');
    }
}
