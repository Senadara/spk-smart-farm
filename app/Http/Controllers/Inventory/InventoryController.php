<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\MasterSupplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryController extends Controller
{
    public function index()
    {
        $items = InventoryItem::with('supplier')
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $barnMap = $this->barnMap();
        $inventoryItems = $items->map(fn (InventoryItem $item) => $this->formatItem($item, $barnMap))->values()->all();
        $kpi = $this->getKpiMetrics($inventoryItems);
        $recommendedRestocks = $this->getSpkRestockRanking($inventoryItems);
        $charts = $this->getChartData($items, $barnMap);
        $movementLog = $this->getMovementLog($barnMap);
        $categoryOptions = $items->pluck('category')->unique()->sort()->values()->all();
        $barnOptions = DB::table('unitBudidaya')
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->get(['id', 'nama']);
        $supplierOptions = MasterSupplier::orderBy('nama')->get(['id', 'nama']);

        return view('inventory.dashboard', compact(
            'kpi',
            'recommendedRestocks',
            'inventoryItems',
            'charts',
            'movementLog',
            'categoryOptions',
            'barnOptions',
            'supplierOptions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'nullable|string|max:50|unique:inventory_items,sku',
            'name' => 'required|string|max:255',
            'category' => 'required|string|max:80',
            'stock' => 'required|numeric|min:0',
            'unit' => 'required|string|max:30',
            'daily_usage' => 'nullable|numeric|min:0',
            'minimum_stock' => 'nullable|numeric|min:0',
            'reorder_point' => 'nullable|numeric|min:0',
            'lead_time_days' => 'nullable|integer|min:0|max:365',
            'supplier_id' => 'nullable|exists:master_suppliers,id',
            'unit_budidaya_id' => 'nullable|string|max:36',
            'notes' => 'nullable|string',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('inventory-items', 'public')
            : null;

        $sku = $validated['sku'] ?? null;

        $item = InventoryItem::create([
            'sku' => $sku ?: $this->nextSku(),
            'name' => $validated['name'],
            'category' => $validated['category'],
            'stock' => $validated['stock'],
            'unit' => $validated['unit'],
            'daily_usage' => $validated['daily_usage'] ?? 0,
            'minimum_stock' => $validated['minimum_stock'] ?? 0,
            'reorder_point' => $validated['reorder_point'] ?? ($validated['minimum_stock'] ?? 0),
            'lead_time_days' => $validated['lead_time_days'] ?? 1,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'unit_budidaya_id' => $validated['unit_budidaya_id'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'photo_path' => $photoPath,
            'last_restock_at' => ((float) $validated['stock']) > 0 ? now() : null,
        ]);

        if ($item->stock > 0) {
            $this->recordMovement($item, 'inflow', $item->stock, 0, $item->stock, 'Stok awal saat item dibuat.');
        }

        return redirect()->route('inventory')->with('success', 'Item inventaris berhasil ditambahkan.');
    }

    public function show(InventoryItem $item)
    {
        $barnMap = $this->barnMap();

        return response()->json([
            'item' => $this->formatItem($item->load('supplier'), $barnMap),
            'movements' => $item->movements()
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (InventoryMovement $movement) => $this->formatMovement($movement->load('user'), $barnMap))
                ->values(),
        ]);
    }

    public function adjust(Request $request, InventoryItem $item)
    {
        $validated = $request->validate([
            'type' => 'required|in:inflow,outflow,adjustment',
            'quantity' => 'required|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $stockBefore = (float) $item->stock;
        $inputQty = (float) $validated['quantity'];
        $type = $validated['type'];

        $stockAfter = match ($type) {
            'inflow' => $stockBefore + $inputQty,
            'outflow' => max(0, $stockBefore - $inputQty),
            'adjustment' => $inputQty,
        };
        $delta = $stockAfter - $stockBefore;

        $item->stock = $stockAfter;
        if ($type === 'inflow' && $inputQty > 0) {
            $item->last_restock_at = now();
        }
        $item->save();

        $this->recordMovement(
            $item,
            $type,
            $type === 'inflow' ? $inputQty : $delta,
            $stockBefore,
            $stockAfter,
            $validated['note'] ?? null
        );

        return redirect()->route('inventory')->with('success', 'Stok inventaris berhasil diperbarui.');
    }

    public function purchaseOrder()
    {
        $items = InventoryItem::with('supplier')
            ->where('is_active', true)
            ->get()
            ->map(fn (InventoryItem $item) => $this->formatItem($item, $this->barnMap()))
            ->filter(fn (array $item) => in_array($item['status'], ['critical', 'warning'], true))
            ->sortByDesc('restock_score')
            ->take(8)
            ->values()
            ->map(function (array $item) {
                $target = max((float) $item['reorder_point'], (float) $item['minimum_stock'] * 2, (float) $item['daily_usage'] * max(7, (int) $item['lead_time']));
                $qty = max(1, ceil($target - (float) $item['stock']));

                return [
                    'sku' => $item['id'],
                    'name' => $item['name'],
                    'supplier' => $item['supplier'] ?: 'Supplier belum dipilih',
                    'qty' => $qty,
                    'unit' => $item['unit'],
                    'priority' => $item['priority'],
                    'days_left' => $item['days_left_label'],
                ];
            });

        return response()->json([
            'po_number' => 'PO-'.now()->format('Ymd-His'),
            'generated_at' => now()->format('d M Y H:i'),
            'items' => $items,
            'message' => $items->isEmpty()
                ? 'Tidak ada item yang membutuhkan restock saat ini.'
                : 'Draft PO dibuat dari item berstatus critical/warning.',
        ]);
    }

    public function analysis()
    {
        $items = InventoryItem::where('is_active', true)->get()
            ->map(fn (InventoryItem $item) => $this->formatItem($item, $this->barnMap()));

        return response()->json([
            'total_items' => $items->count(),
            'critical' => $items->where('status', 'critical')->count(),
            'warning' => $items->where('status', 'warning')->count(),
            'optimal' => $items->where('status', 'optimal')->count(),
            'top_risk' => $items->sortByDesc('restock_score')->take(5)->values(),
        ]);
    }

    private function getKpiMetrics(array $inventoryItems): array
    {
        $items = collect($inventoryItems);
        $days = $items->pluck('days_left')->filter(fn ($value) => is_numeric($value));
        $avgDays = $days->isNotEmpty() ? round($days->avg(), 1) : '-';

        return [
            ['label' => 'Total Item', 'value' => (string) $items->count(), 'trend' => ['direction' => 'stable', 'value' => 'aktif', 'status' => 'neutral']],
            ['label' => 'Low Stock', 'value' => (string) $items->where('status', 'warning')->count(), 'trend' => ['direction' => 'up', 'value' => 'review', 'status' => 'warning']],
            ['label' => 'Critical Stock', 'value' => (string) $items->where('status', 'critical')->count(), 'trend' => ['direction' => 'up', 'value' => 'urgent', 'status' => 'negative']],
            ['label' => 'Avg. Sisa Hari', 'value' => (string) $avgDays, 'trend' => ['direction' => 'stable', 'value' => 'hari', 'status' => 'neutral']],
        ];
    }

    private function getSpkRestockRanking(array $inventoryItems): array
    {
        $items = collect($inventoryItems)
            ->sortByDesc('restock_score')
            ->take(5)
            ->values();

        return $items->map(function (array $item, int $index) {
            return [
                'rank' => $index + 1,
                'id' => $item['raw_id'],
                'name' => $item['name'],
                'category' => $item['category'],
                'current_stock' => $item['stock_label'],
                'days_remaining' => $item['days_left'] ?? 999,
                'days_label' => $item['days_left_label'],
                'score' => number_format($item['restock_score'], 3),
                'priority' => $item['priority'],
                'supplier' => $item['supplier'] ?: 'Belum dipilih',
                'price' => '-',
                'lead_time' => $item['lead_time'],
            ];
        })->all();
    }

    private function getChartData($items, array $barnMap): array
    {
        $labels = collect(range(6, 0))->map(fn ($days) => now()->subDays($days)->format('d M'))->all();
        $dateKeys = collect(range(6, 0))->map(fn ($days) => now()->subDays($days)->toDateString())->all();
        $movements = InventoryMovement::with('item')
            ->where('type', 'outflow')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->get();

        $feed = [];
        $vitamin = [];
        foreach ($dateKeys as $date) {
            $daily = $movements->filter(fn ($movement) => $movement->created_at->toDateString() === $date);
            $feed[] = round(abs($daily->filter(fn ($movement) => $movement->item?->category === 'Pakan')->sum('quantity')), 1);
            $vitamin[] = round(abs($daily->filter(fn ($movement) => $movement->item?->category === 'Vitamin')->sum('quantity')), 1);
        }

        if (array_sum($feed) <= 0) {
            $feed = $items->where('category', 'Pakan')->pluck('daily_usage')->map(fn ($value) => round((float) $value, 1))->pad(7, 0)->take(7)->values()->all();
        }
        if (array_sum($vitamin) <= 0) {
            $vitamin = $items->where('category', 'Vitamin')->pluck('daily_usage')->map(fn ($value) => round((float) $value, 1))->pad(7, 0)->take(7)->values()->all();
        }

        $byBarn = $items->groupBy(fn ($item) => $barnMap[$item->unit_budidaya_id] ?? 'Umum');

        return [
            'consumptionTrend' => [
                'labels' => $labels,
                'layer' => $feed,
                'starter' => $vitamin,
            ],
            'usagePerBarn' => [
                'labels' => $byBarn->keys()->values()->all(),
                'pakan' => $byBarn->map(fn ($group) => round($group->where('category', 'Pakan')->sum('daily_usage'), 1))->values()->all(),
                'vitamin' => $byBarn->map(fn ($group) => round($group->where('category', 'Vitamin')->sum('daily_usage'), 1))->values()->all(),
            ],
        ];
    }

    private function getMovementLog(array $barnMap): array
    {
        return InventoryMovement::with(['item', 'user'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn (InventoryMovement $movement) => $this->formatMovement($movement, $barnMap))
            ->all();
    }

    private function formatItem(InventoryItem $item, array $barnMap): array
    {
        $daysLeft = $item->daily_usage > 0 ? (int) floor($item->stock / $item->daily_usage) : null;
        $status = $this->statusFor($item, $daysLeft);
        $priority = match ($status) {
            'critical' => 'Critical',
            'warning' => 'Warning',
            default => 'Safe',
        };
        $restockScore = $this->restockScore($item, $daysLeft, $status);

        return [
            'raw_id' => $item->id,
            'id' => $item->sku,
            'name' => $item->name,
            'category' => $item->category,
            'stock' => round($item->stock, 2),
            'stock_label' => $this->numberLabel($item->stock).' '.$item->unit,
            'unit' => $item->unit,
            'daily_usage' => round($item->daily_usage, 2),
            'daily_usage_label' => $this->numberLabel($item->daily_usage).' '.$item->unit,
            'minimum_stock' => round($item->minimum_stock, 2),
            'reorder_point' => round($item->reorder_point, 2),
            'lead_time' => $item->lead_time_days,
            'days_left' => $daysLeft,
            'days_left_label' => $daysLeft === null ? '-' : $daysLeft.' Hari',
            'status' => $status,
            'priority' => $priority,
            'restock_score' => $restockScore,
            'last_restock' => $item->last_restock_at?->format('d M Y') ?? '-',
            'supplier' => $item->supplier?->nama,
            'barn' => $barnMap[$item->unit_budidaya_id] ?? 'Umum',
            'unit_budidaya_id' => $item->unit_budidaya_id,
            'notes' => $item->notes,
            'photo' => $item->photo_path ? asset('storage/'.$item->photo_path) : null,
        ];
    }

    private function formatMovement(InventoryMovement $movement, array $barnMap): array
    {
        $quantity = $movement->quantity;
        $sign = $quantity > 0 ? '+' : '';

        return [
            'time' => $movement->created_at?->format('d M, H:i') ?? '-',
            'item' => $movement->item?->name ?? 'Item dihapus',
            'qty' => $sign.$this->numberLabel($quantity).' '.$movement->unit,
            'type' => $movement->type,
            'note' => $movement->note ?: 'Tanpa catatan',
            'user' => $movement->user?->name ?? 'Sistem',
            'barn' => $barnMap[$movement->unit_budidaya_id] ?? 'Umum',
            'stock_after' => $this->numberLabel($movement->stock_after).' '.$movement->unit,
        ];
    }

    private function statusFor(InventoryItem $item, ?int $daysLeft): string
    {
        if ($item->stock <= $item->minimum_stock || ($daysLeft !== null && $daysLeft <= max(2, $item->lead_time_days))) {
            return 'critical';
        }

        if ($item->stock <= $item->reorder_point || ($daysLeft !== null && $daysLeft <= $item->lead_time_days + 5)) {
            return 'warning';
        }

        return 'optimal';
    }

    private function restockScore(InventoryItem $item, ?int $daysLeft, string $status): float
    {
        $statusWeight = ['critical' => 0.55, 'warning' => 0.35, 'optimal' => 0.1][$status] ?? 0.1;
        $daysWeight = $daysLeft === null ? 0.1 : max(0, min(0.3, (30 - min($daysLeft, 30)) / 100));
        $leadWeight = min(0.15, $item->lead_time_days / 60);

        return round($statusWeight + $daysWeight + $leadWeight, 3);
    }

    private function recordMovement(InventoryItem $item, string $type, float $quantity, float $before, float $after, ?string $note): void
    {
        InventoryMovement::create([
            'inventory_item_id' => $item->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $before,
            'stock_after' => $after,
            'unit' => $item->unit,
            'unit_budidaya_id' => $item->unit_budidaya_id,
            'user_id' => $this->currentUserId(),
            'note' => $note,
        ]);
    }

    private function nextSku(): string
    {
        do {
            $sku = 'INV-'.now()->format('ymd').'-'.Str::upper(Str::random(4));
        } while (InventoryItem::where('sku', $sku)->exists());

        return $sku;
    }

    private function barnMap(): array
    {
        return DB::table('unitBudidaya')->pluck('nama', 'id')->toArray();
    }

    private function currentUserId(): ?string
    {
        return data_get(session('user'), 'id') ?? session('user_id');
    }

    private function numberLabel(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
