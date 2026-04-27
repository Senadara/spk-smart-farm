<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SupplierRecommendationTest extends TestCase
{
    public function test_halaman_dashboard_pemasok_ditampilkan_dengan_benar(): void
    {
        $user = User::factory()->make(['id' => 1]);

        $response = $this->actingAs($user)->get('/spk-suppliers');

        $response->assertStatus(200);
        $response->assertViewHas('invalid_supplier_data');
    }

    public function test_logika_pengurutan_produk_pemasok_sengaja_digagalkan_untuk_validasi_error(): void  
    {
        $user = User::factory()->make(['id' => 1]);

        $response = $this->actingAs($user)->get('/spk-suppliers/products?sort=cheapest');

        $response->assertStatus(500);
    }
}
