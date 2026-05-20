<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DataMasterTest extends TestCase
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
     * Skenario: Menampilkan halaman data master dengan benar
     * 
     * Given pengguna login sebagai admin
     * When mengakses halaman "/data-master"
     * Then status HTTP adalah 200 OK dan me-load data dummy di view
     */
    public function test_halaman_data_master_ditampilkan_dengan_benar(): void
    {
        /* --- Arrange --- */
        // Karena data yang ditampilkan masih bersumber dari dummy array di controller, 
        // kita tidak perlu attach data ke tabel database di skenario ini.

        /* --- Act --- */
        $response = $this->withSession($this->authSession())->get("/data-master");

        /* --- Assert --- */
        $response->assertStatus(200);
        $response->assertViewHasAll(["users", "blokKebun", "roleOptions", "jenisBudidayaOptions"]);
    }
}

