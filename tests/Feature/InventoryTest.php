<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    public function test_halaman_dashboard_inventaris_ditampilkan_dengan_benar(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $response = $this->actingAs($user)->get('/inventory');

        $response->assertStatus(200);
        $response->assertViewHas('invalid_inventory_key');
    }
}
