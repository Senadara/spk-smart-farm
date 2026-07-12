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

        $items = [
            ['INV-001', 'Pakan Layer Grower 50kg', 'Pakan', 12, 'Sak', 5.5, 20, 30, 3, 0],
            ['INV-002', 'Pakan Layer Starter 50kg', 'Pakan', 85, 'Sak', 4.2, 20, 35, 3, 1],
            ['INV-003', 'Vaksin ND-IB 1000 ds', 'Obat & Vaksin', 5, 'Vial', 1.2, 8, 12, 2, 2],
            ['INV-004', 'Vitamin C Soluble 1kg', 'Vitamin', 8, 'Pack', 1.3, 7, 12, 1, 3],
            ['INV-005', 'Antibiotik Amox 100g', 'Obat & Vaksin', 24, 'Sachet', 0.5, 10, 15, 2, 0],
            ['INV-006', 'Desinfektan Kandang 5L', 'Perlengkapan', 3, 'Jerigen', 0.4, 5, 8, 2, 1],
            ['INV-007', 'Egg Tray Karton Isi 30', 'Perlengkapan', 450, 'Ikat', 18, 120, 180, 1, 2],
            ['INV-008', 'Lampu Pemanas Infrared', 'Peralatan', 18, 'Pcs', 1.2, 8, 12, 4, 3],
        ];

        foreach ($items as [$sku, $name, $category, $stock, $unit, $dailyUsage, $minStock, $reorderPoint, $leadTime, $barnIndex]) {
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
                    'supplier_id' => $supplier?->id,
                    'unit_budidaya_id' => $barnIds->get($barnIndex),
                    'last_restock_at' => now()->subDays(rand(2, 18)),
                    'is_active' => true,
                ]
            );

            if (! $item->movements()->exists()) {
                InventoryMovement::create([
                    'inventory_item_id' => $item->id,
                    'type' => 'inflow',
                    'quantity' => $stock,
                    'stock_before' => 0,
                    'stock_after' => $stock,
                    'unit' => $unit,
                    'unit_budidaya_id' => $item->unit_budidaya_id,
                    'user_id' => DB::table('user')->orderBy('createdAt')->value('id'),
                    'note' => 'Saldo awal inventaris demo',
                    'created_at' => now()->subDays(rand(3, 20)),
                    'updated_at' => now()->subDays(rand(3, 20)),
                ]);
            }
        }
    }
}
