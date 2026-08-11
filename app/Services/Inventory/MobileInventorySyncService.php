<?php

namespace App\Services\Inventory;

use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileInventorySyncService
{
    public function sync(): int
    {
        if (! $this->hasRequiredTables()) {
            return 0;
        }

        $rows = DB::table('inventaris as inventory')
            ->leftJoin('kategoriInventaris as category', 'category.id', '=', 'inventory.kategoriInventarisId')
            ->leftJoin('satuan as unit', 'unit.id', '=', 'inventory.satuanId')
            ->select([
                'inventory.id',
                'inventory.nama',
                'inventory.jumlah',
                'inventory.stokMinim',
                'inventory.gambar',
                'inventory.detail',
                'inventory.tanggalKadaluwarsa',
                'inventory.ketersediaan',
                'inventory.isDeleted',
                'inventory.createdAt',
                'inventory.updatedAt',
                'category.nama as category_name',
                'unit.nama as unit_name',
                'unit.lambang as unit_symbol',
            ])
            ->get();

        $sourceIds = [];
        $synced = 0;

        foreach ($rows as $row) {
            $sourceIds[] = $row->id;

            $item = $this->findExistingItem($row);
            $previousStock = $item?->stock;
            $stock = max(0, (float) ($row->jumlah ?? 0));
            $minimumStock = max(0, (float) ($row->stokMinim ?? 0));
            $dailyUsage = $this->dailyUsage($row->id);
            $unit = $this->unitLabel($row);
            $category = $row->category_name ?: 'Umum';
            $leadTime = max(1, (int) ($item?->lead_time_days ?? $this->leadTimeFor($category)));
            $safetyStockDays = max(0, (int) ($item?->safety_stock_days ?? $this->safetyStockDaysFor($category)));
            $reorderPointOverride = $item?->reorder_point_override;
            $reorderPoint = $this->reorderPoint($minimumStock, $dailyUsage, $leadTime, $safetyStockDays, $reorderPointOverride);

            $payload = [
                'sku' => $this->skuFor($row->id),
                'name' => $row->nama ?: 'Inventaris tanpa nama',
                'category' => $category,
                'stock' => $stock,
                'unit' => $unit,
                'daily_usage' => $dailyUsage > 0 ? $dailyUsage : (float) ($item?->daily_usage ?? 0),
                'minimum_stock' => $minimumStock,
                'reorder_point' => $reorderPoint,
                'lead_time_days' => $leadTime,
                'unit_budidaya_id' => $this->latestUnitBudidayaId($row->id) ?? $item?->unit_budidaya_id,
                'photo_path' => $row->gambar ?: $item?->photo_path,
                'notes' => $this->notesFor($row),
                'is_active' => (int) ($row->isDeleted ?? 0) === 0,
                'synced_from_mobile_at' => now(),
            ];

            if (Schema::hasColumn('inventory_items', 'mobile_inventaris_id')) {
                $payload['mobile_inventaris_id'] = $row->id;
            }

            if (Schema::hasColumn('inventory_items', 'safety_stock_days')) {
                $payload['safety_stock_days'] = $safetyStockDays;
            }

            if (Schema::hasColumn('inventory_items', 'reorder_point_override')) {
                $payload['reorder_point_override'] = $reorderPointOverride;
            }

            if ($item) {
                $item->fill($payload)->save();
            } else {
                $item = InventoryItem::create($payload);
            }

            $this->recordStockSnapshot($item, $previousStock, $stock, $unit);
            $synced++;
        }

        $this->deactivateMissingMobileItems($sourceIds);
        $this->syncUsageMovements();

        return $synced;
    }

    private function hasRequiredTables(): bool
    {
        return Schema::hasTable('inventaris')
            && Schema::hasTable('inventory_items')
            && Schema::hasTable('kategoriInventaris')
            && Schema::hasTable('satuan');
    }

    private function findExistingItem(object $row): ?InventoryItem
    {
        if (Schema::hasColumn('inventory_items', 'mobile_inventaris_id')) {
            $item = InventoryItem::where('mobile_inventaris_id', $row->id)->first();
            if ($item) {
                return $item;
            }
        }

        $item = InventoryItem::where('sku', $this->skuFor($row->id))->first();
        if ($item) {
            return $item;
        }

        return InventoryItem::where('name', $row->nama)
            ->where('category', $row->category_name ?: 'Umum')
            ->first();
    }

    private function skuFor(string $mobileId): string
    {
        return 'MOB-'.$mobileId;
    }

    private function unitLabel(object $row): string
    {
        return $row->unit_symbol ?: ($row->unit_name ?: 'unit');
    }

    private function dailyUsage(string $inventarisId): float
    {
        $since = now()->subDays(30)->startOfDay();
        $dailyTotals = collect();

        foreach (['penggunaanInventaris', 'vitamin'] as $table) {
            foreach ($this->dailyUsageRows($table, $inventarisId, $since) as $row) {
                $date = (string) $row->usage_date;
                $dailyTotals[$date] = (float) ($dailyTotals[$date] ?? 0) + abs((float) $row->total);
            }
        }

        return $this->usageRateFromDailyTotals($dailyTotals);
    }

    private function dailyUsageRows(string $table, string $inventarisId, Carbon $since)
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }

        $dateExpression = 'DATE(usage.createdAt)';

        return DB::table($table.' as usage')
            ->where('usage.inventarisId', $inventarisId)
            ->where(function ($query) {
                $query->where('usage.isDeleted', false)->orWhereNull('usage.isDeleted');
            })
            ->where('usage.createdAt', '>=', $since)
            ->selectRaw($dateExpression.' as usage_date, SUM(ABS(usage.jumlah)) as total')
            ->groupBy(DB::raw($dateExpression))
            ->get();
    }

    private function usageRateFromDailyTotals($dailyTotals): float
    {
        $dailyTotals = collect($dailyTotals)
            ->map(fn ($total) => abs((float) $total))
            ->filter(fn (float $total) => $total > 0)
            ->sortKeys();

        if ($dailyTotals->isEmpty()) {
            return 0.0;
        }

        $activeDayAverage = (float) $dailyTotals->avg();
        $latestDayTotal = (float) $dailyTotals->last();

        return round(max($activeDayAverage, $latestDayTotal), 2);
    }

    private function latestUnitBudidayaId(string $inventarisId): ?string
    {
        $sources = [
            ['table' => 'penggunaanInventaris', 'alias' => 'usage'],
            ['table' => 'vitamin', 'alias' => 'usage'],
        ];

        foreach ($sources as $source) {
            if (! Schema::hasTable($source['table'])) {
                continue;
            }

            $unitId = DB::table($source['table'].' as '.$source['alias'])
                ->join('laporan as report', 'report.id', '=', $source['alias'].'.laporanId')
                ->where($source['alias'].'.inventarisId', $inventarisId)
                ->where(function ($query) use ($source) {
                    $query->where($source['alias'].'.isDeleted', false)
                        ->orWhereNull($source['alias'].'.isDeleted');
                })
                ->orderByDesc($source['alias'].'.createdAt')
                ->value('report.unitBudidayaId');

            if ($unitId) {
                return $unitId;
            }
        }

        return null;
    }

    private function leadTimeFor(string $category): int
    {
        $lower = strtolower($category);

        if (str_contains($lower, 'pakan')) {
            return 3;
        }

        if (str_contains($lower, 'vaksin') || str_contains($lower, 'obat') || str_contains($lower, 'vitamin')) {
            return 2;
        }

        return 4;
    }

    private function safetyStockDaysFor(string $category): int
    {
        $lower = strtolower($category);

        if (str_contains($lower, 'pakan')) {
            return 5;
        }

        if (str_contains($lower, 'vaksin') || str_contains($lower, 'obat') || str_contains($lower, 'vitamin')) {
            return 3;
        }

        return 4;
    }

    private function reorderPoint(
        float $minimumStock,
        float $dailyUsage,
        int $leadTime,
        int $safetyStockDays,
        mixed $override
    ): float {
        if ($override !== null && (float) $override >= 0) {
            return round((float) $override, 2);
        }

        $usageBasedPoint = $dailyUsage > 0
            ? $dailyUsage * ($leadTime + $safetyStockDays)
            : $minimumStock * 1.25;

        return round(max($minimumStock, $usageBasedPoint), 2);
    }

    private function notesFor(object $row): string
    {
        $parts = collect([
            $row->detail,
            $row->tanggalKadaluwarsa ? 'Kadaluwarsa: '.Carbon::parse($row->tanggalKadaluwarsa)->format('d M Y') : null,
            'Sinkron dari inventaris mobile: '.$row->id,
        ])->filter()->values();

        return $parts->implode("\n");
    }

    private function recordStockSnapshot(InventoryItem $item, mixed $previousStock, float $stock, string $unit): void
    {
        if (! Schema::hasTable('inventory_movements')) {
            return;
        }

        $previous = $previousStock === null ? null : (float) $previousStock;
        if ($previous !== null && abs($previous - $stock) < 0.0001) {
            return;
        }

        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'type' => $previous === null ? 'inflow' : 'adjustment',
            'quantity' => $previous === null ? $stock : round($stock - $previous, 2),
            'stock_before' => $previous ?? 0,
            'stock_after' => $stock,
            'unit' => $unit,
            'unit_budidaya_id' => $item->unit_budidaya_id,
            'user_id' => null,
            'note' => $previous === null
                ? 'Saldo awal hasil sinkronisasi inventaris mobile.'
                : 'Penyesuaian stok dari inventaris mobile.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function syncUsageMovements(): void
    {
        if (! Schema::hasTable('inventory_movements')
            || ! Schema::hasColumn('inventory_movements', 'source_table')
            || ! Schema::hasColumn('inventory_movements', 'source_id')) {
            return;
        }

        foreach ($this->usageRows() as $usage) {
            $item = $this->itemForMobileInventory((string) $usage->inventaris_id);
            $quantity = abs((float) ($usage->jumlah ?? 0));

            if (! $item || $quantity <= 0) {
                continue;
            }

            $occurredAt = Carbon::parse($usage->report_created_at ?: $usage->usage_created_at);
            $stockAfter = max(0, (float) $item->stock);
            $stockBefore = $stockAfter + $quantity;

            InventoryMovement::updateOrCreate(
                [
                    'source_table' => $usage->source_table,
                    'source_id' => $usage->source_id,
                ],
                [
                    'inventory_item_id' => $item->id,
                    'type' => 'outflow',
                    'quantity' => -$quantity,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'unit' => $item->unit,
                    'unit_budidaya_id' => $usage->unit_budidaya_id ?: $item->unit_budidaya_id,
                    'user_id' => $usage->user_id,
                    'note' => 'Penggunaan inventaris dari laporan mobile '.$usage->laporan_id.'.',
                    'created_at' => $occurredAt,
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function usageRows()
    {
        $rows = collect();

        if (Schema::hasTable('penggunaanInventaris') && Schema::hasTable('laporan')) {
            $rows = $rows->merge(
                DB::table('penggunaanInventaris as usage')
                    ->leftJoin('laporan as report', 'report.id', '=', 'usage.laporanId')
                    ->where(function ($query) {
                        $query->where('usage.isDeleted', false)->orWhereNull('usage.isDeleted');
                    })
                    ->where(function ($query) {
                        $query->where('report.isDeleted', false)->orWhereNull('report.isDeleted');
                    })
                    ->select([
                        DB::raw("'penggunaanInventaris' as source_table"),
                        'usage.id as source_id',
                        'usage.inventarisId as inventaris_id',
                        'usage.laporanId as laporan_id',
                        'usage.jumlah',
                        'usage.createdAt as usage_created_at',
                        'report.createdAt as report_created_at',
                        'report.unitBudidayaId as unit_budidaya_id',
                        'report.userId as user_id',
                    ])
                    ->get()
            );
        }

        if (Schema::hasTable('vitamin') && Schema::hasTable('laporan')) {
            $rows = $rows->merge(
                DB::table('vitamin as usage')
                    ->leftJoin('laporan as report', 'report.id', '=', 'usage.laporanId')
                    ->where(function ($query) {
                        $query->where('usage.isDeleted', false)->orWhereNull('usage.isDeleted');
                    })
                    ->where(function ($query) {
                        $query->where('report.isDeleted', false)->orWhereNull('report.isDeleted');
                    })
                    ->select([
                        DB::raw("'vitamin' as source_table"),
                        'usage.id as source_id',
                        'usage.inventarisId as inventaris_id',
                        'usage.laporanId as laporan_id',
                        'usage.jumlah',
                        'usage.createdAt as usage_created_at',
                        'report.createdAt as report_created_at',
                        'report.unitBudidayaId as unit_budidaya_id',
                        'report.userId as user_id',
                    ])
                    ->get()
            );
        }

        return $rows;
    }

    private function itemForMobileInventory(string $inventarisId): ?InventoryItem
    {
        if (Schema::hasColumn('inventory_items', 'mobile_inventaris_id')) {
            $item = InventoryItem::where('mobile_inventaris_id', $inventarisId)->first();
            if ($item) {
                return $item;
            }
        }

        return InventoryItem::where('sku', $this->skuFor($inventarisId))->first();
    }

    private function deactivateMissingMobileItems(array $sourceIds): void
    {
        if (! Schema::hasColumn('inventory_items', 'mobile_inventaris_id')) {
            return;
        }

        InventoryItem::whereNotNull('mobile_inventaris_id')
            ->whereNotIn('mobile_inventaris_id', $sourceIds)
            ->update([
                'is_active' => false,
                'synced_from_mobile_at' => now(),
            ]);
    }
}
