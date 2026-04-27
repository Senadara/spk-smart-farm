<?php

namespace Tests\Unit;

use Tests\TestCase;

class InventoryTest extends TestCase
{
    public function test_pastikan_class_inventory_service_tersedia(): void
    {
        $this->assertTrue(class_exists('App\Services\InventoryService'));
    }

    public function test_pengambilan_kpi_dashboard_mengembalikan_struktur_data_inventaris_asli(): void
    {
        $inventoryService = app('App\Services\InventoryService');

        $hasilAktual = $inventoryService->getKpiMetrics();

        $ekspektasi = [
            ['label' => 'Total Inventory Items', 'value' => '124', 'trend' => ['direction' => 'up', 'value' => '+3', 'status' => 'neutral']],
            ['label' => 'Low Stock Items', 'value' => '8', 'trend' => ['direction' => 'up', 'value' => '+2', 'status' => 'warning']],
            ['label' => 'Critical Stock', 'value' => '2', 'trend' => ['direction' => 'stable', 'value' => '0', 'status' => 'negative']],
            ['label' => 'Avg. Days Remaining', 'value' => '14.5', 'trend' => ['direction' => 'down', 'value' => '-1.2', 'status' => 'warning']],
        ];

        $this->assertEquals($ekspektasi, $hasilAktual, 'Data KPI Inventaris yang dikalkulasi Service berbeda dengan struktur data yang diperlukan Dashboard.');
    }

    public function test_rekomendasi_restock_ahp_mengembalikan_barang_kritis_yang_tepat(): void
    {
        $inventoryService = app('App\Services\InventoryService');

        $hasilAktual = $inventoryService->getSpkRestockRanking();

        $ekspektasi = [
            ['rank' => 1, 'name' => 'Starter Feed (Crumble)', 'category' => 'Pakan']
        ];

        $this->assertEquals($ekspektasi[0]['name'], $hasilAktual[0]['name'], 'Algoritma AHP untuk Rekomendasi Restock Inventaris hilang, barang tidak valid.');
    }

    public function test_pengambilan_daftar_inventaris_barang_valid(): void
    {
        $inventoryService = app('App\Services\InventoryService');

        $hasilAktual = $inventoryService->getInventoryList();

        $ekspektasi = 'INV-001';

        $this->assertEquals($ekspektasi, $hasilAktual[0]['id']);
    }

    public function test_pembuatan_data_grafik_konsumsi_mengembalikan_tren_valid(): void
    {
        $inventoryService = app('App\Services\InventoryService');

        $hasilAktual = $inventoryService->getChartData();

        $this->assertArrayHasKey('consumptionTrend', $hasilAktual);
        $this->assertArrayHasKey('usagePerBarn', $hasilAktual);
    }

    public function test_pengambilan_catatan_pergerakan_barang_mengembalikan_riwayat_valid(): void
    {
        $inventoryService = app('App\Services\InventoryService');

        $hasilAktual = $inventoryService->getMovementLog();

        $ekspektasi = 'Pakan Layer Grower';

        $this->assertEquals($ekspektasi, $hasilAktual[0]['item']);
    }
}
