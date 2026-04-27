<?php

namespace Tests\Unit;

use Tests\TestCase;

class SupplierRecommendationTest extends TestCase
{
    public function test_pastikan_class_supplier_service_tersedia(): void
    {
        $this->assertTrue(class_exists('App\Services\SupplierService'));
    }

    public function test_pencarian_dan_penyaringan_pemasok_memeriksa_semua_kategori_dan_deskripsi_produk(): void
    {
        $supplierService = app('App\Services\SupplierService');

        $parameterFilter = ['search' => 'kandang', 'category' => 'peralatan'];

        $hasilAktual = $supplierService->getFilteredSuppliers($parameterFilter);

        $ekspektasiSatuPemasokFilter = [
            'id' => 3,
            'name' => 'PT Kandang Sejahtera',
            'categories' => ['Peralatan'],
            'categories_slug' => ['peralatan'],
        ];

        $this->assertEquals($ekspektasiSatuPemasokFilter['name'], $hasilAktual[0]['name']);
    }

    public function test_pengurutan_produk_berdasarkan_harga_termurah_mereturn_susunan_yang_tepat(): void
    {
        $supplierService = app('App\Services\SupplierService');

        $dataProdukAcak = [
            ['id' => 1, 'nama' => 'Pakan A', 'harga' => 150000],
            ['id' => 2, 'nama' => 'Pakan B', 'harga' => 120000],
            ['id' => 3, 'nama' => 'Pakan C', 'harga' => 175000]
        ];

        $hasilAktual = $supplierService->sortProductsByCheapest($dataProdukAcak);

        $ekspektasi = [
            ['id' => 2, 'nama' => 'Pakan B', 'harga' => 120000],
            ['id' => 1, 'nama' => 'Pakan A', 'harga' => 150000],
            ['id' => 3, 'nama' => 'Pakan C', 'harga' => 175000]
        ];

        $this->assertEquals($ekspektasi, $hasilAktual);
    }

    public function test_pengambilan_detail_pemasok_dengan_id_tidak_valid_mengembalikan_data_kosong(): void
    {
        $supplierService = app('App\Services\SupplierService');

        $idPemasokFiktif = 999;

        $hasilAktual = $supplierService->findSupplierById($idPemasokFiktif);

        $this->assertNull($hasilAktual);
    }
}
