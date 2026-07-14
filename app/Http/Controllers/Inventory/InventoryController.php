<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventorySupplierProductLink;
use App\Models\SupplierProduct;
use App\Services\Inventory\MobileInventorySyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(private MobileInventorySyncService $mobileInventorySync) {}

    public function index()
    {
        $this->mobileInventorySync->sync();

        $items = InventoryItem::with([
            'supplier',
            'preferredSupplierProductLink.product.store',
        ])
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

        return view('inventory.dashboard', compact(
            'kpi',
            'recommendedRestocks',
            'inventoryItems',
            'charts',
            'movementLog',
            'categoryOptions',
            'barnOptions'
        ));
    }

    public function store(Request $request)
    {
        abort(403, 'Input inventaris utama dilakukan dari aplikasi mobile/API Node.js. Laravel hanya membaca stok dan membantu restock supplier.');
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
        abort(403, 'Perubahan stok utama dilakukan dari aplikasi mobile/API Node.js. Laravel hanya membaca stok dan membantu restock supplier.');
    }

    public function storeSupplierLink(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'supplier_product_id' => 'required|string|exists:produk,id',
            'conversion_qty' => 'required|numeric|min:0.0001',
            'conversion_unit' => 'nullable|string|max:30',
            'notes' => 'nullable|string|max:500',
        ]);

        SupplierProduct::query()
            ->where('id', $validated['supplier_product_id'])
            ->where('isDeleted', false)
            ->firstOrFail();

        InventorySupplierProductLink::query()
            ->where('inventory_item_id', $item->id)
            ->update(['is_preferred' => false]);

        InventorySupplierProductLink::query()->updateOrCreate(
            [
                'inventory_item_id' => $item->id,
                'supplier_product_id' => $validated['supplier_product_id'],
            ],
            [
                'conversion_qty' => (float) $validated['conversion_qty'],
                'conversion_unit' => $validated['conversion_unit'] ?: $item->unit,
                'is_preferred' => true,
                'notes' => $validated['notes'] ?? null,
            ]
        );

        return redirect()
            ->route('inventory')
            ->with('success', 'Barang inventaris berhasil dihubungkan ke produk supplier.');
    }

    public function orderRestock(Request $request, InventoryItem $item): RedirectResponse
    {
        $link = $item->preferredSupplierProductLink()
            ->with('product.store')
            ->first();

        if (! $link || ! $link->product || $link->product->isDeleted) {
            return redirect()
                ->route('spk.suppliers.products', ['search' => $item->name])
                ->with('error', 'Item belum terhubung ke produk supplier. Pilih produk yang sesuai terlebih dahulu.');
        }

        $product = $link->product;
        if ((int) $product->stok <= 0) {
            return redirect()
                ->route('spk.suppliers.products', ['search' => $product->nama])
                ->with('error', 'Produk supplier yang terhubung sedang kosong. Pilih produk supplier lain.');
        }

        $quantity = min($this->recommendedSupplierQuantity($item, $link), (int) $product->stok);
        $cart = $request->session()->get('supplier_cart', []);
        $cart[$product->id] = min((int) ($cart[$product->id] ?? 0) + $quantity, (int) $product->stok);
        $request->session()->put('supplier_cart', $cart);

        return redirect()
            ->route('spk.suppliers.products', ['search' => $product->nama])
            ->with('success', "{$product->nama} ditambahkan ke keranjang restock sebanyak {$quantity} {$product->satuan}.");
    }

    public function purchaseOrder()
    {
        $this->mobileInventorySync->sync();

        $items = InventoryItem::with([
            'supplier',
            'preferredSupplierProductLink.product.store',
        ])
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
                $linked = $item['linked_product'] ?? null;

                return [
                    'sku' => $item['id'],
                    'name' => $item['name'],
                    'supplier' => $linked['store'] ?? 'Belum terhubung ke produk supplier',
                    'qty' => $linked['recommended_quantity'] ?? $qty,
                    'unit' => $linked['product_unit'] ?? $item['unit'],
                    'priority' => $item['priority'],
                    'days_left' => $item['days_left_label'],
                    'can_order' => (bool) $linked,
                ];
            });

        return response()->json([
            'po_number' => 'PO-'.now()->format('Ymd-His'),
            'generated_at' => now()->format('d M Y H:i'),
            'items' => $items,
            'message' => $items->isEmpty()
                ? 'Tidak ada item yang membutuhkan restock saat ini.'
                : 'Draft PO dibuat dari item critical/warning. Item tanpa mapping perlu dihubungkan ke produk supplier terlebih dahulu.',
        ]);
    }

    public function analysis()
    {
        $this->mobileInventorySync->sync();

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
                'linked_product' => $item['linked_product'],
                'supplier_candidates' => $item['supplier_candidates'],
                'order_url' => route('inventory.items.restock-order', $item['raw_id']),
                'link_url' => route('inventory.items.supplier-links.store', $item['raw_id']),
                'needs_mapping' => empty($item['linked_product']),
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
            $estimatedFeed = round((float) $items->where('category', 'Pakan')->sum('daily_usage'), 1);
            $feed = array_fill(0, count($dateKeys), $estimatedFeed);
        }
        if (array_sum($vitamin) <= 0) {
            $estimatedVitamin = round((float) $items->where('category', 'Vitamin')->sum('daily_usage'), 1);
            $vitamin = array_fill(0, count($dateKeys), $estimatedVitamin);
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

        $linkedProduct = $this->formatLinkedProduct($item);

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
            'linked_product' => $linkedProduct,
            'supplier_candidates' => $linkedProduct ? [] : $this->supplierCandidatesFor($item),
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

    private function formatLinkedProduct(InventoryItem $item): ?array
    {
        $link = $item->preferredSupplierProductLink;
        $product = $link?->product;

        if (! $link || ! $product || $product->isDeleted) {
            return null;
        }

        return [
            'id' => $product->id,
            'name' => $product->nama,
            'store' => $product->store?->nama ?? 'Toko supplier',
            'price' => (int) $product->harga,
            'stock' => (int) $product->stok,
            'product_unit' => $product->satuan,
            'conversion_qty' => (float) $link->conversion_qty,
            'conversion_unit' => $link->conversion_unit ?: $item->unit,
            'recommended_quantity' => $this->recommendedSupplierQuantity($item, $link),
            'recommended_label' => $this->recommendedSupplierQuantity($item, $link).' '.$product->satuan,
        ];
    }

    private function supplierCandidatesFor(InventoryItem $item): array
    {
        $terms = collect(preg_split('/\s+/', strtolower($item->name.' '.$item->category)))
            ->map(fn ($term) => trim($term))
            ->filter(fn ($term) => strlen($term) >= 3)
            ->take(5)
            ->values();

        $query = SupplierProduct::query()
            ->with('store')
            ->where('isDeleted', false)
            ->where('stok', '>', 0)
            ->whereHas('store', fn ($store) => $store->where('isDeleted', false));

        $query->where(function ($inner) use ($item, $terms) {
            $inner->where('kategori', 'like', '%'.$item->category.'%');

            foreach ($terms as $term) {
                $inner->orWhere('nama', 'like', '%'.$term.'%')
                    ->orWhere('deskripsi', 'like', '%'.$term.'%')
                    ->orWhere('kategori', 'like', '%'.$term.'%');
            }
        });

        return $query
            ->orderByDesc('stok')
            ->limit(4)
            ->get()
            ->map(fn (SupplierProduct $product) => [
                'id' => $product->id,
                'name' => $product->nama,
                'store' => $product->store?->nama ?? 'Toko supplier',
                'stock' => (int) $product->stok,
                'unit' => $product->satuan,
                'price' => (int) $product->harga,
                'category' => $product->kategori ?: 'Umum',
                'default_conversion' => $this->guessConversionQty($item, $product),
            ])
            ->values()
            ->all();
    }

    private function recommendedSupplierQuantity(InventoryItem $item, InventorySupplierProductLink $link): int
    {
        $targetStock = max(
            (float) $item->reorder_point,
            (float) $item->minimum_stock * 2,
            (float) $item->daily_usage * max(7, (int) $item->lead_time_days)
        );

        $neededInventoryQty = max(0.0, $targetStock - (float) $item->stock);
        if ($neededInventoryQty <= 0) {
            $neededInventoryQty = max((float) $item->minimum_stock, (float) $item->daily_usage, 1.0);
        }

        return max(1, (int) ceil($neededInventoryQty / max((float) $link->conversion_qty, 0.0001)));
    }

    private function guessConversionQty(InventoryItem $item, SupplierProduct $product): float
    {
        if (strcasecmp($item->unit, $product->satuan) === 0) {
            return 1.0;
        }

        $text = strtolower($product->nama.' '.$product->deskripsi.' '.$product->satuan);
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(kg|kilogram)/', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(liter|ltr|l)\b/', $text, $match)) {
            return (float) str_replace(',', '.', $match[1]);
        }

        return 1.0;
    }

    private function barnMap(): array
    {
        return DB::table('unitBudidaya')->pluck('nama', 'id')->toArray();
    }

    private function numberLabel(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
