<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use DatabaseTransactions;

    private function authSession(): array
    {
        return [
            "api_token" => "fake-token",
            "user" => ["id" => "admin-uuid-1", "name" => "QA Tester", "email" => "qa@farm.com", "role" => "admin"],
        ];
    }

    /**
     * Skenario: Menampilkan dashboard inventory 
     * 
     * Given pengguna login sebagai admin
     * When mengakses halaman "/inventory"
     * Then status HTTP adalah 200 OK dan me-load indikator serta list barang
     */
    public function test_halaman_dashboard_inventaris_ditampilkan_dengan_benar(): void
    {
        /* --- Arrange --- */
        // Data dashboard saat ini ditarik secara statis dari controller (Mock/Dummy)

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get("/inventory");

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewIs("inventory.dashboard");
        $response->assertViewHasAll(["kpi", "recommendedRestocks", "inventoryItems", "charts", "movementLog"]);
    }
}

