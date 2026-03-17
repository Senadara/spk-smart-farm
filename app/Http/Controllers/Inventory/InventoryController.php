<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;

class InventoryController extends Controller
{
    /**
     * Dashboard utama inventaris — Inventory Monitoring & Restock Recommendations.
     */
    public function index()
    {
        $kpi = $this->getKpiMetrics();
        $recommendedRestocks = $this->getSpkRestockRanking();
        $inventoryItems = $this->getInventoryList();
        $charts = $this->getChartData();
        $movementLog = $this->getMovementLog();

        return view('inventory.dashboard', compact(
            'kpi',
            'recommendedRestocks',
            'inventoryItems',
            'charts',
            'movementLog'
        ));
    }

    // ═══════════════════════════════════════════════════════════════
    // DUMMY DATA METHODS
    // ═══════════════════════════════════════════════════════════════

    private function getKpiMetrics(): array
    {
        return [
            ['label' => 'Total Inventory Items', 'value' => '124', 'trend' => ['direction' => 'up', 'value' => '+3', 'status' => 'neutral']],
            ['label' => 'Low Stock Items', 'value' => '8', 'trend' => ['direction' => 'up', 'value' => '+2', 'status' => 'warning']],
            ['label' => 'Critical Stock', 'value' => '2', 'trend' => ['direction' => 'stable', 'value' => '0', 'status' => 'negative']],
            ['label' => 'Avg. Days Remaining', 'value' => '14.5', 'trend' => ['direction' => 'down', 'value' => '-1.2', 'status' => 'warning']],
        ];
    }

    private function getSpkRestockRanking(): array
    {
        // AHP-SAW Ranking Dummy Data
        return [
            [
                'rank' => 1,
                'name' => 'Starter Feed (Crumble)',
                'category' => 'Pakan',
                'current_stock' => '2 Sak',
                'days_remaining' => 2,
                'score' => '0.942',
                'priority' => 'Critical',
                'supplier' => 'PT Surya Pakan',
                'price' => 'Rp 350.000',
                'lead_time' => 3,
            ],
            [
                'rank' => 2,
                'name' => 'Newcastle Disease Vac.',
                'category' => 'Obat & Vaksin',
                'current_stock' => '5 Vial',
                'days_remaining' => 5,
                'score' => '0.715',
                'priority' => 'Warning',
                'supplier' => 'Medion',
                'price' => 'Rp 120.000',
                'lead_time' => 2,
            ],
            [
                'rank' => 3,
                'name' => 'Vitamin C Soluble',
                'category' => 'Vitamin',
                'current_stock' => '8 Pack',
                'days_remaining' => 12,
                'score' => '0.450',
                'priority' => 'Safe',
                'supplier' => 'Agrinusa',
                'price' => 'Rp 85.000',
                'lead_time' => 1,
            ],
        ];
    }

    private function getInventoryList(): array
    {
        return [
            ['id' => 'INV-001', 'name' => 'Pakan Layer Grower (50kg)', 'category' => 'Pakan', 'stock' => 12, 'unit' => 'Sak', 'daily_usage' => '5.5', 'days_left' => 2, 'status' => 'critical', 'last_restock' => '2025-10-15', 'photo' => 'https://images.unsplash.com/photo-1626082927389-6cd097cdc6ec?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
            ['id' => 'INV-002', 'name' => 'Pakan Layer Starter (50kg)', 'category' => 'Pakan', 'stock' => 85, 'unit' => 'Sak', 'daily_usage' => '4.2', 'days_left' => 20, 'status' => 'optimal', 'last_restock' => '2025-10-10', 'photo' => 'https://images.unsplash.com/photo-1590422749963-cbf2a9d8c0b5?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
            ['id' => 'INV-003', 'name' => 'Vaksin ND-IB (1000 ds)', 'category' => 'Obat & Vaksin', 'stock' => 5, 'unit' => 'Vial', 'daily_usage' => '1.2', 'days_left' => 4, 'status' => 'critical', 'last_restock' => '2025-09-01', 'photo' => 'https://images.unsplash.com/photo-1626888463133-c24da57c327e?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
            ['id' => 'INV-004', 'name' => 'Vitamin C Soluble (1kg)', 'category' => 'Vitamin', 'stock' => 8, 'unit' => 'Pack', 'daily_usage' => '1.3', 'days_left' => 6, 'status' => 'warning', 'last_restock' => '2025-10-05', 'photo' => 'https://images.unsplash.com/photo-1584017911766-d451b3d0e843?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
            ['id' => 'INV-005', 'name' => 'Antiobiotik Amox (100g)', 'category' => 'Obat & Vaksin', 'stock' => 24, 'unit' => 'Sachet', 'daily_usage' => '0.5', 'days_left' => 48, 'status' => 'optimal', 'last_restock' => '2025-08-20', 'photo' => 'https://images.unsplash.com/photo-1576086213369-97a306d36557?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
            ['id' => 'INV-006', 'name' => 'Desinfektan Kandang (5L)', 'category' => 'Perlengkapan', 'stock' => 3, 'unit' => 'Jerigen', 'daily_usage' => '0.4', 'days_left' => 7, 'status' => 'warning', 'last_restock' => '2025-09-15', 'photo' => 'https://images.unsplash.com/photo-1583947215259-38e31be8751f?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
            ['id' => 'INV-007', 'name' => 'Egg Tray Karton (Isi 30)', 'category' => 'Perlengkapan', 'stock' => 450, 'unit' => 'Ikat', 'daily_usage' => '18', 'days_left' => 25, 'status' => 'optimal', 'last_restock' => '2025-10-22', 'photo' => 'https://images.unsplash.com/photo-1582218412852-c0e487541f53?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
            ['id' => 'INV-008', 'name' => 'Lampu Pemanas (Infrared)', 'category' => 'Peralatan', 'stock' => 18, 'unit' => 'Pcs', 'daily_usage' => '1.2', 'days_left' => 14, 'status' => 'optimal', 'last_restock' => '2025-06-10', 'photo' => 'https://images.unsplash.com/photo-1550989460-0adf9ea622e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=150&q=80'],
        ];
    }

    private function getChartData(): array
    {
        // 1. Line chart: Feed Consumption Trend (7 days)
        $labels = [];
        $feedLayer = [];
        $feedStarter = [];
        for ($i = 6; $i >= 0; $i--) {
            $labels[] = now()->subDays($i)->format('d M');
            $feedLayer[] = round(280 + mt_rand(0, 40) - 20, 1);
            $feedStarter[] = round(120 + mt_rand(0, 20) - 10, 1);
        }

        // 2. Bar chart: Inventory Usage Per Barn (Today)
        $barnLabels = ['Barn A', 'Barn B', 'Barn C', 'Barn D'];
        $usagePakan = [410, 380, 0, 150];
        $usageVitamin = [2.5, 2.0, 0, 1.0];

        return [
            'consumptionTrend' => [
                'labels' => $labels,
                'layer' => $feedLayer,
                'starter' => $feedStarter,
            ],
            'usagePerBarn' => [
                'labels' => $barnLabels,
                'pakan' => $usagePakan,
                'vitamin' => $usageVitamin,
            ],
        ];
    }

    private function getMovementLog(): array
    {
        return [
            ['time' => now()->subMinutes(45)->format('d M, H:i'), 'item' => 'Pakan Layer Grower', 'qty' => '-5 Sak', 'type' => 'outflow', 'note' => 'Distribusi pakan harian Barn A & B', 'user' => 'Petugas Budi'],
            ['time' => now()->subHours(3)->format('d M, H:i'), 'item' => 'Egg Tray Karton', 'qty' => '-20 Ikat', 'type' => 'outflow', 'note' => 'Packing telur pagi', 'user' => 'Petugas Andi'],
            ['time' => now()->subDays(1)->format('d M, H:i'), 'item' => 'Vaksin ND-IB', 'qty' => '+50 Vial', 'type' => 'inflow', 'note' => 'Penerimaan barang dari supplier Medion', 'user' => 'Admin Rini'],
            ['time' => now()->subDays(1)->format('d M, H:i'), 'item' => 'Vitamin C Soluble', 'qty' => '-2 Pack', 'type' => 'outflow', 'note' => 'Aplikasi vitamin rutin Barn D', 'user' => 'Petugas Budi'],
            ['time' => now()->subDays(2)->format('d M, H:i'), 'item' => 'Lampu Pemanas', 'qty' => '-2 Pcs', 'type' => 'adjustment', 'note' => 'Penggantian lampu putus di Barn D', 'user' => 'Teknisi Joko'],
            ['time' => now()->subDays(3)->format('d M, H:i'), 'item' => 'Desinfektan Kandang', 'qty' => '-1 Jerigen', 'type' => 'outflow', 'note' => 'Biosecurity rutin mingguan', 'user' => 'Petugas Andi'],
        ];
    }
}
