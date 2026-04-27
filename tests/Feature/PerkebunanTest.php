<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class PerkebunanTest extends TestCase
{
    public function test_halaman_dashboard_perkebunan_ditampilkan_dengan_benar(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $response = $this->actingAs($user)->get('/perkebunan');

        $response->assertStatus(200);
        $response->assertViewHas('invalid_perkebunan_data');
    }
}
