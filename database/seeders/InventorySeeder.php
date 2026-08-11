<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\MasterSupplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $supplier = MasterSupplier::orderBy('id')->first();
        $barnIds = DB::table('unitBudidaya')
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->pluck('id')
            ->values();
        $userId = DB::table('user')->where('email', 'pjawab@email.com')->value('id')
            ?: DB::table('user')->orderBy('createdAt')->value('id');

        $items = [
            ['DEMO-EGG-TRAY-001', 'Egg Tray Karton Isi 30', 'Perlengkapan', 420, 'tray', 18, 120, 180, 1, 0, true, 'Stok aman untuk pengemasan telur harian.'],
            ['DEMO-VIT-C-001', 'Vitamin C Soluble 1 kg', 'Vitamin', 9, 'paket', 1.3, 7, 12, 1, 1, true, 'Stok warning: cukup untuk beberapa hari, perlu masuk pantauan restok.'],
            ['DEMO-VAKSIN-ND-001', 'Vaksin ND-IB 1000 Dosis', 'Obat & Vaksin', 5, 'paket', 0.2, 6, 10, 2, 2, true, 'Stok critical untuk skenario rekomendasi restok kesehatan.'],
            ['DEMO-DISINFEKTAN-001', 'Disinfektan Kandang 5 L', 'Disinfektan', 18, 'L', 2.5, 20, 35, 2, 3, true, 'Stok warning karena kebutuhan sanitasi meningkat.'],
            ['DEMO-PPE-001', 'Sarung Tangan dan Masker Kandang', 'Perlengkapan', 80, 'paket', 3, 20, 30, 2, 4, true, 'Perlengkapan petugas untuk tindakan kandang.'],
            ['DEMO-LAMPU-001', 'Lampu Pemanas Infrared Cadangan', 'Peralatan', 0, 'pcs', 0, 2, 4, 4, 5, false, 'Item nonaktif sebagai contoh arsip inventaris.'],
        ];

        foreach ($items as [$sku, $name, $category, $stock, $unit, $dailyUsage, $minStock, $reorderPoint, $leadTime, $barnIndex, $isActive, $notes]) {
            $item = InventoryItem::updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $name,
                    'category' => $category,
                    'stock' => $stock,
                    'unit' => $unit,
                    'daily_usage' => $dailyUsage,
                    'minimum_stock' => $minStock,
                    'reorder_point' => $reorderPoint,
                    'lead_time_days' => $leadTime,
                    'safety_stock_days' => 3,
                    'supplier_id' => $supplier?->id,
                    'unit_budidaya_id' => $barnIds->get($barnIndex),
                    'notes' => $notes,
                    'last_restock_at' => now()->subDays(12 + $barnIndex),
                    'is_active' => $isActive,
                ]
            );

            InventoryMovement::updateOrCreate(
                [
                    'source_table' => 'database_seeder',
                    'source_id' => $sku,
                ],
                [
                    'inventory_item_id' => $item->id,
                    'type' => 'inflow',
                    'quantity' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'unit' => $unit,
                    'unit_budidaya_id' => $item->unit_budidaya_id,
                    'user_id' => $userId,
                    'note' => 'Saldo awal inventaris demo.',
                    'created_at' => now()->subDays(12 + $barnIndex),
                    'updated_at' => now()->subDays(12 + $barnIndex),
                ]
            );
        }

        $this->command?->info('InventorySeeder: stok demo deterministic untuk skenario aman, warning, critical, dan nonaktif berhasil disiapkan.');
    }
}
