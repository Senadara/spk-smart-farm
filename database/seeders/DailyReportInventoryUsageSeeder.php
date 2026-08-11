<?php

namespace Database\Seeders;

use App\Models\InventoryItem;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailyReportInventoryUsageSeeder extends Seeder
{
    private const KG_ID = '11111111-1111-1111-1111-111111111111';

    private const LITER_ID = '33333333-3333-3333-3333-333333333333';

    private const ML_ID = '44444444-4444-4444-4444-444444444444';

    private const PACK_ID = '88888888-8888-8888-8888-888888888888';

    public function run(): void
    {
        if (! Schema::hasTable('laporan') || ! Schema::hasTable('harianTernak') || ! Schema::hasTable('unitBudidaya')) {
            return;
        }

        $userId = DB::table('user')->orderBy('createdAt')->value('id');
        if (! $userId) {
            return;
        }

        $catalog = $this->ensureMobileInventoryCatalog();

        $this->syncLayerFeedUsage($userId, $catalog);
        $this->seedLayerCareActions($userId, $catalog);
    }

    private function ensureMobileInventoryCatalog(): array
    {
        if (! Schema::hasTable('inventaris') || ! Schema::hasTable('kategoriInventaris') || ! Schema::hasTable('satuan')) {
            return [];
        }

        $this->ensureSatuan(self::KG_ID, 'Kilogram', 'kg');
        $this->ensureSatuan(self::LITER_ID, 'Liter', 'L');
        $this->ensureSatuan(self::ML_ID, 'Mililiter', 'ml');
        $this->ensureSatuan(self::PACK_ID, 'Paket', 'paket');

        $categories = [
            'Pakan' => $this->ensureKategoriInventaris('Pakan', 'f00d1000-0000-4000-8000-000000000001'),
            'Vitamin' => $this->ensureKategoriInventaris('Vitamin', 'ac3a537e-486f-4f99-ace0-e398765bcd0d'),
            'Vaksin' => $this->ensureKategoriInventaris('Vaksin', '082802a5-54ba-470f-925e-f90ff6ad447f'),
            'Disinfektan' => $this->ensureKategoriInventaris('Disinfektan', '1dd42017-1358-4141-859c-5084f347f534'),
        ];

        $items = [
            'feed_a' => [
                'id' => 'f00d0001-0000-4000-8000-000000000001',
                'category_id' => $categories['Pakan'],
                'satuan_id' => self::KG_ID,
                'name' => 'Pakan Layer Complete - Kandang Layer A Optimal',
                'initial_stock' => 4200.0,
                'minimum_stock' => 1000.0,
                'detail' => 'Pakan utama ayam petelur fase puncak produksi untuk Kandang Layer A - Optimal. Satuan pemakaian laporan harian: kilogram.',
                'expires_at' => now()->addMonths(5),
            ],
            'feed_b' => [
                'id' => 'f00d0002-0000-4000-8000-000000000002',
                'category_id' => $categories['Pakan'],
                'satuan_id' => self::KG_ID,
                'name' => 'Pakan Layer Complete - Kandang Layer B Produksi Turun',
                'initial_stock' => 2200.0,
                'minimum_stock' => 850.0,
                'detail' => 'Pakan utama ayam petelur untuk Kandang Layer B - Produksi Turun. Stok sengaja mendekati batas untuk demo rekomendasi restok.',
                'expires_at' => now()->addMonths(5),
            ],
            'feed_c' => [
                'id' => 'f00d0003-0000-4000-8000-000000000003',
                'category_id' => $categories['Pakan'],
                'satuan_id' => self::KG_ID,
                'name' => 'Pakan Layer Complete - Kandang Layer C Lingkungan',
                'initial_stock' => 2600.0,
                'minimum_stock' => 750.0,
                'detail' => 'Pakan utama ayam petelur untuk Kandang Layer C - Lingkungan Waspada.',
                'expires_at' => now()->addMonths(5),
            ],
            'feed_d' => [
                'id' => 'f00d0004-0000-4000-8000-000000000004',
                'category_id' => $categories['Pakan'],
                'satuan_id' => self::KG_ID,
                'name' => 'Pakan Layer Complete - Kandang Layer D Afkir',
                'initial_stock' => 1800.0,
                'minimum_stock' => 650.0,
                'detail' => 'Pakan utama ayam petelur tua untuk Kandang Layer D - Menjelang Afkir.',
                'expires_at' => now()->addMonths(4),
            ],
            'feed_e' => [
                'id' => 'f00d0005-0000-4000-8000-000000000005',
                'category_id' => $categories['Pakan'],
                'satuan_id' => self::KG_ID,
                'name' => 'Pakan Grower Layer - Kandang Layer E',
                'initial_stock' => 1600.0,
                'minimum_stock' => 450.0,
                'detail' => 'Pakan grower untuk Kandang Layer E - Pre Layer yang belum menghasilkan telur.',
                'expires_at' => now()->addMonths(4),
            ],
            'feed_f' => [
                'id' => 'f00d0006-0000-4000-8000-000000000006',
                'category_id' => $categories['Pakan'],
                'satuan_id' => self::KG_ID,
                'name' => 'Pakan Layer Complete - Kandang Layer F',
                'initial_stock' => 2000.0,
                'minimum_stock' => 600.0,
                'detail' => 'Pakan utama ayam petelur untuk Kandang Layer F - Belum Lapor Hari Ini.',
                'expires_at' => now()->addMonths(5),
            ],
            'vitamin' => [
                'id' => 'cafe0001-0000-4000-8000-000000000001',
                'category_id' => $categories['Vitamin'],
                'satuan_id' => self::ML_ID,
                'name' => 'Vitamin AD3E + Elektrolit Layer',
                'initial_stock' => 10000.0,
                'minimum_stock' => 1500.0,
                'detail' => 'Vitamin larut air untuk dukungan kesehatan layer saat cuaca berubah.',
                'expires_at' => now()->addMonths(10),
            ],
            'vaccine' => [
                'id' => 'cafe0002-0000-4000-8000-000000000002',
                'category_id' => $categories['Vaksin'],
                'satuan_id' => self::PACK_ID,
                'name' => 'Vaksin ND-IB 1000 Dosis',
                'initial_stock' => 24.0,
                'minimum_stock' => 6.0,
                'detail' => 'Vaksin ND-IB untuk program kesehatan ayam petelur. Satu paket untuk satu tindakan kandang.',
                'expires_at' => now()->addMonths(8),
            ],
            'disinfectant' => [
                'id' => 'cafe0003-0000-4000-8000-000000000003',
                'category_id' => $categories['Disinfektan'],
                'satuan_id' => self::LITER_ID,
                'name' => 'Disinfektan Kandang Quat 5L',
                'initial_stock' => 120.0,
                'minimum_stock' => 20.0,
                'detail' => 'Disinfektan kandang untuk sanitasi rutin jalur pakan, lantai, dan area telur.',
                'expires_at' => now()->addMonths(12),
            ],
        ];

        foreach ($items as $item) {
            $this->upsertMobileInventoryItem($item);
        }

        return $items;
    }

    private function syncLayerFeedUsage(string $userId, array $catalog): void
    {
        $plans = [
            'Kandang Layer A - Optimal' => [
                'mobile_key' => 'feed_a',
                'sku' => 'LAYER-A-FEED-KG',
                'web_name' => 'Pakan Layer Complete - Kandang Layer A Optimal',
                'initial_stock' => 4200.0,
                'minimum_stock' => 1000.0,
                'reorder_point' => 1500.0,
            ],
            'Kandang Layer B - Produksi Turun' => [
                'mobile_key' => 'feed_b',
                'sku' => 'LAYER-B-FEED-KG',
                'web_name' => 'Pakan Layer Complete - Kandang Layer B Produksi Turun',
                'initial_stock' => 2200.0,
                'minimum_stock' => 850.0,
                'reorder_point' => 1300.0,
            ],
            'Kandang Layer C - Lingkungan Waspada' => [
                'mobile_key' => 'feed_c',
                'sku' => 'LAYER-C-FEED-KG',
                'web_name' => 'Pakan Layer Complete - Kandang Layer C Lingkungan',
                'initial_stock' => 2600.0,
                'minimum_stock' => 750.0,
                'reorder_point' => 1100.0,
            ],
            'Kandang Layer D - Menjelang Afkir' => [
                'mobile_key' => 'feed_d',
                'sku' => 'LAYER-D-FEED-KG',
                'web_name' => 'Pakan Layer Complete - Kandang Layer D Afkir',
                'initial_stock' => 1800.0,
                'minimum_stock' => 650.0,
                'reorder_point' => 900.0,
            ],
            'Kandang Layer E - Pre Layer' => [
                'mobile_key' => 'feed_e',
                'sku' => 'LAYER-E-FEED-KG',
                'web_name' => 'Pakan Grower Layer - Kandang Layer E',
                'initial_stock' => 1600.0,
                'minimum_stock' => 450.0,
                'reorder_point' => 650.0,
            ],
            'Kandang Layer F - Belum Lapor Hari Ini' => [
                'mobile_key' => 'feed_f',
                'sku' => 'LAYER-F-FEED-KG',
                'web_name' => 'Pakan Layer Complete - Kandang Layer F',
                'initial_stock' => 2000.0,
                'minimum_stock' => 600.0,
                'reorder_point' => 850.0,
            ],
        ];

        $coopIds = DB::table('unitBudidaya')
            ->whereIn('nama', array_keys($plans))
            ->pluck('id', 'nama');

        foreach ($plans as $coopName => $plan) {
            $coopId = $coopIds[$coopName] ?? null;
            if (! $coopId) {
                continue;
            }

            $records = DB::table('harianTernak as ht')
                ->join('laporan as l', 'l.id', '=', 'ht.laporanId')
                ->where('l.unitBudidayaId', $coopId)
                ->where('l.isDeleted', 0)
                ->where('ht.isDeleted', 0)
                ->where('ht.pakan', '>', 0)
                ->orderBy('l.createdAt')
                ->get([
                    'ht.id as harian_id',
                    'ht.pakan',
                    'l.id as laporan_id',
                    'l.createdAt',
                    'l.catatan',
                ]);

            if ($records->isEmpty()) {
                continue;
            }

            $mobileItem = $catalog[$plan['mobile_key']] ?? null;
            $averageFeedKg = round((float) $records->avg('pakan'), 2);
            $totalFeedKg = round((float) $records->sum('pakan'), 2);
            $reserveStockKg = round(max($plan['reorder_point'], $averageFeedKg * 14), 2);
            $initialStockKg = round(max($plan['initial_stock'], $totalFeedKg + $reserveStockKg), 2);

            $webItem = $this->ensureWebInventoryItem([
                'sku' => $plan['sku'],
                'name' => $plan['web_name'],
                'category' => 'Pakan',
                'stock' => $initialStockKg,
                'unit' => 'kg',
                'daily_usage' => $averageFeedKg,
                'minimum_stock' => $plan['minimum_stock'],
                'reorder_point' => $plan['reorder_point'],
                'lead_time_days' => 3,
                'unit_budidaya_id' => $coopId,
                'mobile_inventaris_id' => $mobileItem['id'] ?? null,
                'notes' => 'Stok pakan demo yang disinkronkan dengan laporan harian kandang.',
            ]);

            $runningStock = $initialStockKg;
            $firstUsageAt = Carbon::parse($records->first()->createdAt)->subDay()->setTime(8, 0);

            if ($webItem) {
                $this->upsertWebInventoryMovement($webItem->id, [
                    'type' => 'inflow',
                    'quantity' => $initialStockKg,
                    'stock_before' => 0,
                    'stock_after' => $initialStockKg,
                    'unit' => 'kg',
                    'unit_budidaya_id' => $coopId,
                    'user_id' => $userId,
                    'note' => "Saldo awal demo pakan {$coopName}.",
                    'created_at' => $firstUsageAt,
                ]);
            }

            foreach ($records as $record) {
                $feedKg = round((float) $record->pakan, 2);
                $usageAt = Carbon::parse($record->createdAt)->setTime(7, 30);
                $stockBefore = $runningStock;
                $runningStock = max(0, round($runningStock - $feedKg, 2));

                if ($mobileItem) {
                    $this->upsertMobileFeedUsage($record->laporan_id, $mobileItem['id'], $feedKg, $usageAt);
                }

                if ($webItem) {
                    $this->upsertWebInventoryMovement($webItem->id, [
                        'type' => 'outflow',
                        'quantity' => -$feedKg,
                        'stock_before' => $stockBefore,
                        'stock_after' => $runningStock,
                        'unit' => 'kg',
                        'unit_budidaya_id' => $coopId,
                        'user_id' => $userId,
                        'note' => "Demo laporan {$record->laporan_id}: pemberian pakan {$coopName} {$feedKg} kg.",
                        'created_at' => $usageAt,
                    ]);
                }

                $this->appendFeedNoteToReport(
                    $record->laporan_id,
                    (string) $record->catatan,
                    "Inventaris pakan: {$feedKg} kg {$plan['web_name']} digunakan untuk {$coopName}."
                );
            }

            if ($mobileItem) {
                DB::table('inventaris')
                    ->where('id', $mobileItem['id'])
                    ->update(['jumlah' => $runningStock, 'updatedAt' => now()]);
            }

            if ($webItem) {
                DB::table('inventory_items')
                    ->where('id', $webItem->id)
                    ->update([
                        'stock' => $runningStock,
                        'daily_usage' => $averageFeedKg,
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function seedLayerCareActions(string $userId, array $catalog): void
    {
        if (! Schema::hasTable('vitamin') || ! Schema::hasTable('laporan')) {
            return;
        }

        $coops = DB::table('unitBudidaya')
            ->whereIn('nama', [
                'Kandang Layer A - Optimal',
                'Kandang Layer B - Produksi Turun',
                'Kandang Layer C - Lingkungan Waspada',
                'Kandang Layer D - Menjelang Afkir',
                'Kandang Layer E - Pre Layer',
                'Kandang Layer F - Belum Lapor Hari Ini',
            ])
            ->pluck('id', 'nama');

        if ($coops->isEmpty()) {
            return;
        }

        $carePlans = [
            'vitamin' => [
                'mobile_key' => 'vitamin',
                'sku' => 'CARE-VITAMIN-AD3E',
                'name' => 'Vitamin AD3E + Elektrolit Layer',
                'category' => 'Vitamin',
                'unit' => 'ml',
                'initial_stock' => 10000.0,
                'daily_usage' => 70.0,
                'minimum_stock' => 1500.0,
                'reorder_point' => 2500.0,
                'quantity' => 250.0,
                'type' => 'vitamin',
                'days_ago' => 10,
                'title' => 'Pemberian Vitamin Layer',
                'note' => 'Vitamin diberikan melalui air minum untuk menjaga nafsu makan dan daya tahan.',
            ],
            'vaccine' => [
                'mobile_key' => 'vaccine',
                'sku' => 'CARE-VACCINE-NDIB',
                'name' => 'Vaksin ND-IB 1000 Dosis',
                'category' => 'Obat & Vaksin',
                'unit' => 'paket',
                'initial_stock' => 24.0,
                'daily_usage' => 0.2,
                'minimum_stock' => 6.0,
                'reorder_point' => 10.0,
                'quantity' => 1.0,
                'type' => 'vaksin',
                'days_ago' => 7,
                'title' => 'Vaksinasi ND-IB',
                'note' => 'Vaksin ND-IB diberikan sesuai jadwal kesehatan kandang.',
            ],
            'disinfectant' => [
                'mobile_key' => 'disinfectant',
                'sku' => 'CARE-DISINFECTANT-QUAT',
                'name' => 'Disinfektan Kandang Quat 5L',
                'category' => 'Perlengkapan',
                'unit' => 'L',
                'initial_stock' => 120.0,
                'daily_usage' => 2.5,
                'minimum_stock' => 20.0,
                'reorder_point' => 35.0,
                'quantity' => 2.5,
                'type' => 'disinfektan',
                'days_ago' => 14,
                'title' => 'Sanitasi Kandang',
                'note' => 'Disinfektan digunakan untuk sanitasi lantai, jalur pakan, dan area telur.',
            ],
        ];

        $webItems = [];
        $runningStocks = [];

        foreach ($carePlans as $key => $plan) {
            $webItems[$key] = $this->ensureWebInventoryItem([
                'sku' => $plan['sku'],
                'name' => $plan['name'],
                'category' => $plan['category'],
                'stock' => $plan['initial_stock'],
                'unit' => $plan['unit'],
                'daily_usage' => $plan['daily_usage'],
                'minimum_stock' => $plan['minimum_stock'],
                'reorder_point' => $plan['reorder_point'],
                'lead_time_days' => 2,
                'unit_budidaya_id' => null,
                'mobile_inventaris_id' => $catalog[$plan['mobile_key']]['id'] ?? null,
                'notes' => 'Stok tindakan kesehatan demo untuk input laporan mobile.',
            ]);
            $runningStocks[$key] = $plan['initial_stock'];

            if ($webItems[$key]) {
                $this->upsertWebInventoryMovement($webItems[$key]->id, [
                    'type' => 'inflow',
                    'quantity' => $plan['initial_stock'],
                    'stock_before' => 0,
                    'stock_after' => $plan['initial_stock'],
                    'unit' => $plan['unit'],
                    'unit_budidaya_id' => null,
                    'user_id' => $userId,
                    'note' => "Saldo awal demo {$plan['name']}.",
                    'created_at' => now()->subDays(32)->setTime(8, 0),
                ]);
            }
        }

        foreach ($coops as $coopName => $coopId) {
            foreach ($carePlans as $key => $plan) {
                $mobileItem = $catalog[$plan['mobile_key']] ?? null;
                if (! $mobileItem) {
                    continue;
                }

                $actionAt = now()->subDays($plan['days_ago'])->setTime(8, $key === 'vaccine' ? 30 : 0);
                $reportId = $this->stableUuid("laporan-care:{$coopId}:{$key}:{$actionAt->toDateString()}");
                $vitaminId = $this->stableUuid("vitamin-care:{$reportId}:{$mobileItem['id']}");
                $quantity = (float) $plan['quantity'];

                DB::table('laporan')->updateOrInsert(
                    ['id' => $reportId],
                    [
                        'unitBudidayaId' => $coopId,
                        'objekBudidayaId' => null,
                        'userId' => $userId,
                        'judul' => "{$plan['title']} - {$coopName}",
                        'tipe' => 'vitamin',
                        'gambar' => null,
                        'catatan' => "{$plan['note']} Inventaris: {$quantity} {$plan['unit']} {$plan['name']}.",
                        'isDeleted' => 0,
                        'createdAt' => $actionAt,
                        'updatedAt' => $actionAt,
                    ]
                );

                DB::table('vitamin')->updateOrInsert(
                    ['id' => $vitaminId],
                    [
                        'laporanId' => $reportId,
                        'inventarisId' => $mobileItem['id'],
                        'tipe' => $plan['type'],
                        'jumlah' => $quantity,
                        'isDeleted' => 0,
                        'createdAt' => $actionAt,
                        'updatedAt' => $actionAt,
                    ]
                );

                $stockBefore = $runningStocks[$key];
                $runningStocks[$key] = max(0, round($runningStocks[$key] - $quantity, 2));

                if ($webItems[$key]) {
                    $this->upsertWebInventoryMovement($webItems[$key]->id, [
                        'type' => 'outflow',
                        'quantity' => -$quantity,
                        'stock_before' => $stockBefore,
                        'stock_after' => $runningStocks[$key],
                        'unit' => $plan['unit'],
                        'unit_budidaya_id' => $coopId,
                        'user_id' => $userId,
                        'note' => "Demo laporan {$reportId}: {$plan['title']} {$coopName} {$quantity} {$plan['unit']}.",
                        'created_at' => $actionAt,
                    ]);
                }
            }
        }

        foreach ($carePlans as $key => $plan) {
            $mobileItem = $catalog[$plan['mobile_key']] ?? null;
            if ($mobileItem) {
                DB::table('inventaris')
                    ->where('id', $mobileItem['id'])
                    ->update(['jumlah' => $runningStocks[$key], 'updatedAt' => now()]);
            }

            if ($webItems[$key]) {
                DB::table('inventory_items')
                    ->where('id', $webItems[$key]->id)
                    ->update(['stock' => $runningStocks[$key], 'updated_at' => now()]);
            }
        }
    }

    private function ensureKategoriInventaris(string $name, string $fallbackId): string
    {
        $existingId = DB::table('kategoriInventaris')->where('nama', $name)->value('id');
        if ($existingId) {
            DB::table('kategoriInventaris')->where('id', $existingId)->update([
                'isDeleted' => 0,
                'updatedAt' => now(),
            ]);

            return $existingId;
        }

        DB::table('kategoriInventaris')->insert([
            'id' => $fallbackId,
            'nama' => $name,
            'isDeleted' => 0,
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        return $fallbackId;
    }

    private function ensureSatuan(string $id, string $name, string $symbol): void
    {
        DB::table('satuan')->updateOrInsert(
            ['id' => $id],
            [
                'nama' => $name,
                'lambang' => $symbol,
                'isDeleted' => 0,
                'createdAt' => now(),
                'updatedAt' => now(),
            ]
        );
    }

    private function upsertMobileInventoryItem(array $item): void
    {
        if (! $item['category_id']) {
            return;
        }

        DB::table('inventaris')->updateOrInsert(
            ['id' => $item['id']],
            [
                'kategoriInventarisId' => $item['category_id'],
                'satuanId' => $item['satuan_id'],
                'nama' => $item['name'],
                'jumlah' => $item['initial_stock'],
                'stokMinim' => $item['minimum_stock'],
                'gambar' => null,
                'detail' => $item['detail'],
                'tanggalKadaluwarsa' => $item['expires_at'],
                'ketersediaan' => 'tersedia',
                'isDeleted' => 0,
                'createdAt' => now(),
                'updatedAt' => now(),
            ]
        );
    }

    private function ensureWebInventoryItem(array $item): ?InventoryItem
    {
        if (! Schema::hasTable('inventory_items')) {
            return null;
        }

        return InventoryItem::updateOrCreate(
            ['sku' => $item['sku']],
            [
                'name' => $item['name'],
                'mobile_inventaris_id' => $item['mobile_inventaris_id'] ?? null,
                'category' => $item['category'],
                'stock' => $item['stock'],
                'unit' => $item['unit'],
                'daily_usage' => $item['daily_usage'],
                'minimum_stock' => $item['minimum_stock'],
                'reorder_point' => $item['reorder_point'],
                'lead_time_days' => $item['lead_time_days'],
                'unit_budidaya_id' => $item['unit_budidaya_id'],
                'notes' => $item['notes'],
                'last_restock_at' => now()->subDays(32),
                'synced_from_mobile_at' => now(),
                'is_active' => true,
            ]
        );
    }

    private function upsertMobileFeedUsage(string $laporanId, string $inventarisId, float $quantity, Carbon $createdAt): void
    {
        if (! Schema::hasTable('penggunaanInventaris')) {
            return;
        }

        DB::table('penggunaanInventaris')->updateOrInsert(
            ['id' => $this->stableUuid("feed-usage:{$laporanId}:{$inventarisId}")],
            [
                'inventarisId' => $inventarisId,
                'laporanId' => $laporanId,
                'jumlah' => $quantity,
                'isDeleted' => 0,
                'createdAt' => $createdAt,
                'updatedAt' => $createdAt,
            ]
        );
    }

    private function upsertWebInventoryMovement(int $itemId, array $movement): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        DB::table('inventory_movements')->updateOrInsert(
            [
                'inventory_item_id' => $itemId,
                'note' => $movement['note'],
            ],
            [
                'type' => $movement['type'],
                'quantity' => $movement['quantity'],
                'stock_before' => $movement['stock_before'],
                'stock_after' => $movement['stock_after'],
                'unit' => $movement['unit'],
                'unit_budidaya_id' => $movement['unit_budidaya_id'],
                'user_id' => $movement['user_id'],
                'created_at' => $movement['created_at'],
                'updated_at' => $movement['created_at'],
            ]
        );
    }

    private function appendFeedNoteToReport(string $laporanId, string $currentNote, string $feedNote): void
    {
        if (! Schema::hasColumn('laporan', 'catatan') || str_contains($currentNote, 'Inventaris pakan:')) {
            return;
        }

        DB::table('laporan')->where('id', $laporanId)->update([
            'catatan' => trim($currentNote."\n".$feedNote),
            'updatedAt' => now(),
        ]);
    }

    private function stableUuid(string $seed): string
    {
        $hash = md5($seed);

        return sprintf(
            '%s-%s-4%s-8%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            substr($hash, 17, 3),
            substr($hash, 20, 12)
        );
    }
}
