<?php

namespace Tests\Feature;

use Tests\TestCase;

class InventoryTest extends TestCase
{
    public function test_halaman_dashboard_inventaris_ditampilkan_dengan_benar(): void
    {
        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/inventory');

        $response->assertStatus(200);
        $response->assertViewHasAll(['kpi', 'recommendedRestocks', 'inventoryItems', 'charts', 'movementLog']);
    }
}
