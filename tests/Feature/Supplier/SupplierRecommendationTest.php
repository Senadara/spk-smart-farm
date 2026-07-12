<?php

namespace Tests\Feature;

use Tests\TestCase;

class SupplierRecommendationTest extends TestCase
{
    public function test_halaman_dashboard_pemasok_ditampilkan_dengan_benar(): void
    {
        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/spk-suppliers');

        $response->assertStatus(200);
        $response->assertViewHasAll(['suppliers', 'category', 'search']);
    }

    public function test_halaman_produk_supplier_menghasilkan_comparison_table_yang_valid(): void
    {
        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/spk-suppliers/products?sort=cheapest');

        $response->assertStatus(200);
        $response->assertViewHasAll(['products', 'activeProduct', 'comparison', 'search', 'filterSort', 'filterStock']);
    }

    public function test_halaman_detail_supplier_menghasilkan_view_yang_valid(): void
    {
        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/spk-suppliers/S-001');

        $response->assertStatus(200);
        $response->assertViewHasAll(['supplier', 'inventories']);
    }
}
