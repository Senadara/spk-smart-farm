<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventorySupplierProductLink;
use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkAhpBobot;
use App\Models\SpkParameter;
use App\Models\SpkRanking;
use App\Models\SpkSupplierParameterValue;
use App\Models\SupplierProduct;
use App\Models\SupplierStore;
use App\Services\Inventory\MobileInventorySyncService;
use App\Services\SAWRecommenderService;
use App\Services\SupplierDistanceService;
use App\Support\SpkDssActorId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function __construct(
        private MobileInventorySyncService $mobileInventorySync,
        private SAWRecommenderService $sawRecommender,
        private SupplierDistanceService $distanceService,
    ) {}

    public function index(Request $request)
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
        $restockView = $request->query('restock_view') === 'all' ? 'all' : 'needs';
        $recommendedRestocks = $this->getSpkRestockRanking($inventoryItems, $restockView === 'all');
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
            'barnOptions',
            'restockView'
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

    public function updateRestockConfig(Request $request, InventoryItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'lead_time_days' => 'required|integer|min:1|max:60',
            'safety_stock_days' => 'required|integer|min:0|max:60',
            'reorder_point_override' => 'nullable|numeric|min:0|max:999999999',
        ], [
            'lead_time_days.required' => 'Lead time wajib diisi.',
            'safety_stock_days.required' => 'Safety stock wajib diisi.',
        ]);

        $leadTime = (int) $validated['lead_time_days'];
        $safetyDays = (int) $validated['safety_stock_days'];
        $override = $validated['reorder_point_override'] ?? null;
        $reorderPoint = $override !== null
            ? (float) $override
            : $this->autoReorderPoint($item, $leadTime, $safetyDays);

        $item->update([
            'lead_time_days' => $leadTime,
            'safety_stock_days' => $safetyDays,
            'reorder_point_override' => $override,
            'reorder_point' => round($reorderPoint, 2),
        ]);

        return redirect()
            ->route('inventory')
            ->with('success', 'Konfigurasi restock berhasil diperbarui.');
    }

    public function supplierRecommendations(Request $request, InventoryItem $item): View
    {
        $this->mobileInventorySync->sync();

        $item->refresh()->load([
            'supplier',
            'preferredSupplierProductLink.product.store',
        ]);

        $barnMap = $this->barnMap();
        $formattedItem = $this->formatItem($item, $barnMap);
        $restockNeed = $this->restockNeedFor($item);
        $userId = SpkDssActorId::resolve($request);
        $rankingResult = $this->rankSupplierProductsForRestock($item, $userId, $restockNeed);

        return view('inventory.restock-recommendations', [
            'item' => $formattedItem,
            'restockNeed' => $restockNeed,
            'recommendations' => $rankingResult['recommendations'],
            'spkSummary' => $rankingResult['summary'],
            'cart' => $this->supplierCartSummary($request),
            'fallbackSearchUrl' => route('spk.suppliers.products', ['search' => $item->name]),
        ]);
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
                $target = max(
                    (float) $item['reorder_point'],
                    (float) $item['minimum_stock'] * 2,
                    (float) $item['daily_usage'] * max(7, (int) $item['lead_time'] + (int) $item['safety_stock_days'])
                );
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

    private function getSpkRestockRanking(array $inventoryItems, bool $showAll = false): array
    {
        $items = collect($inventoryItems)
            ->when(! $showAll, fn ($collection) => $collection->filter(
                fn (array $item) => in_array($item['status'], ['critical', 'warning'], true)
            ))
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
                'safety_stock_days' => $item['safety_stock_days'],
                'minimum_stock' => $item['minimum_stock'],
                'reorder_point' => $item['reorder_point'],
                'reorder_point_override' => $item['reorder_point_override'],
                'reorder_point_source' => $item['reorder_point_source'],
                'coverage_percent' => $item['coverage_percent'],
                'linked_product' => $item['linked_product'],
                'supplier_candidates' => $item['supplier_candidates'],
                'order_url' => route('inventory.items.restock-order', $item['raw_id']),
                'recommend_url' => route('inventory.items.supplier-recommendations', $item['raw_id']),
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
        $stockDuration = $items
            ->map(function (InventoryItem $item) {
                $daysLeft = $item->daily_usage > 0 ? (int) floor($item->stock / $item->daily_usage) : null;
                $status = $this->statusFor($item, $daysLeft);

                return [
                    'label' => $item->name,
                    'days' => $daysLeft,
                    'status' => $status,
                    'stock' => $this->numberLabel((float) $item->stock).' '.$item->unit,
                ];
            })
            ->sortBy(fn (array $item) => $item['days'] ?? 9999)
            ->take(8)
            ->values();

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
            'stockDuration' => [
                'labels' => $stockDuration->pluck('label')->all(),
                'days' => $stockDuration->pluck('days')->map(fn ($days) => $days ?? 0)->all(),
                'statuses' => $stockDuration->pluck('status')->all(),
                'stocks' => $stockDuration->pluck('stock')->all(),
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
            'safety_stock_days' => (int) ($item->safety_stock_days ?? 0),
            'reorder_point_override' => $item->reorder_point_override !== null ? round((float) $item->reorder_point_override, 2) : null,
            'reorder_point_source' => $item->reorder_point_override !== null ? 'Manual' : 'Auto',
            'coverage_percent' => $this->coveragePercent($daysLeft, (int) $item->lead_time_days, (int) ($item->safety_stock_days ?? 0), $status),
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

        $warningDays = $item->lead_time_days + max(1, (int) ($item->safety_stock_days ?? 5));
        if ($item->stock <= $item->reorder_point || ($daysLeft !== null && $daysLeft <= $warningDays)) {
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
        return $this->candidateProductQuery($item)
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

    private function candidateProductQuery(InventoryItem $item)
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
            ->whereHas('store', fn ($store) => $store
                ->where('isDeleted', false)
                ->where('tokoStatus', 'active'));

        $query->where(function ($inner) use ($item, $terms) {
            $inner->where('kategori', 'like', '%'.$item->category.'%');

            foreach ($terms as $term) {
                $inner->orWhere('nama', 'like', '%'.$term.'%')
                    ->orWhere('deskripsi', 'like', '%'.$term.'%')
                    ->orWhere('kategori', 'like', '%'.$term.'%');
            }
        });

        return $query;
    }

    private function recommendedSupplierQuantity(InventoryItem $item, InventorySupplierProductLink $link): int
    {
        $targetStock = max(
            (float) $item->reorder_point,
            (float) $item->minimum_stock * 2,
            (float) $item->daily_usage * max(7, (int) $item->lead_time_days + (int) ($item->safety_stock_days ?? 0))
        );

        $neededInventoryQty = max(0.0, $targetStock - (float) $item->stock);
        if ($neededInventoryQty <= 0) {
            $neededInventoryQty = max((float) $item->minimum_stock, (float) $item->daily_usage, 1.0);
        }

        return max(1, (int) ceil($neededInventoryQty / max((float) $link->conversion_qty, 0.0001)));
    }

    private function autoReorderPoint(InventoryItem $item, int $leadTime, int $safetyDays): float
    {
        $usageBasedPoint = $item->daily_usage > 0
            ? (float) $item->daily_usage * ($leadTime + $safetyDays)
            : (float) $item->minimum_stock * 1.25;

        return round(max((float) $item->minimum_stock, $usageBasedPoint), 2);
    }

    private function restockNeedFor(InventoryItem $item): array
    {
        $targetStock = $this->targetStockFor($item);
        $neededInventoryQty = max(0.0, $targetStock - (float) $item->stock);
        if ($neededInventoryQty <= 0) {
            $neededInventoryQty = max((float) $item->minimum_stock, (float) $item->daily_usage, 1.0);
        }

        $daysLeft = $item->daily_usage > 0 ? (int) floor($item->stock / $item->daily_usage) : null;
        $status = $this->statusFor($item, $daysLeft);
        $leadTime = (int) $item->lead_time_days;
        $safetyDays = (int) ($item->safety_stock_days ?? 0);

        return [
            'status' => $status,
            'priority' => match ($status) {
                'critical' => 'Segera',
                'warning' => 'Perlu Dijadwalkan',
                default => 'Cadangan',
            },
            'current_stock' => round((float) $item->stock, 2),
            'current_label' => $this->numberLabel((float) $item->stock).' '.$item->unit,
            'minimum_label' => $this->numberLabel((float) $item->minimum_stock).' '.$item->unit,
            'target_stock' => round($targetStock, 2),
            'target_label' => $this->numberLabel($targetStock).' '.$item->unit,
            'needed_inventory_qty' => round($neededInventoryQty, 2),
            'needed_label' => $this->numberLabel($neededInventoryQty).' '.$item->unit,
            'daily_usage_label' => $this->numberLabel((float) $item->daily_usage).' '.$item->unit.'/hari',
            'days_left' => $daysLeft,
            'days_label' => $daysLeft === null ? 'Belum ada estimasi' : $daysLeft.' hari',
            'lead_time_days' => $leadTime,
            'safety_stock_days' => $safetyDays,
            'reorder_point_label' => $this->numberLabel((float) $item->reorder_point).' '.$item->unit,
            'message' => $this->restockMessage($status, $daysLeft, $leadTime, $safetyDays),
        ];
    }

    private function targetStockFor(InventoryItem $item): float
    {
        return max(
            (float) $item->reorder_point,
            (float) $item->minimum_stock * 2,
            (float) $item->daily_usage * max(7, (int) $item->lead_time_days + (int) ($item->safety_stock_days ?? 0))
        );
    }

    private function restockMessage(string $status, ?int $daysLeft, int $leadTime, int $safetyDays): string
    {
        if ($status === 'critical') {
            return $daysLeft === null
                ? 'Stok sudah masuk zona kritis. Segera pilih supplier untuk restock.'
                : "Sisa stok sekitar {$daysLeft} hari, lebih dekat dari lead time {$leadTime} hari.";
        }

        if ($status === 'warning') {
            $buffer = $leadTime + max(1, $safetyDays);

            return "Stok mendekati batas aman. Idealnya pesan sebelum sisa stok kurang dari {$buffer} hari.";
        }

        return 'Stok masih aman, tetapi rekomendasi ini bisa dipakai untuk menyiapkan cadangan pembelian.';
    }

    private function rankSupplierProductsForRestock(InventoryItem $item, ?string $userId, array $restockNeed): array
    {
        $products = $this->candidateProductQuery($item)
            ->orderBy('harga')
            ->limit(16)
            ->get()
            ->filter(fn (SupplierProduct $product) => $product->store !== null)
            ->values();

        if ($products->isEmpty()) {
            return [
                'recommendations' => [],
                'summary' => [
                    'mode' => 'empty',
                    'title' => 'Belum ada produk supplier yang cocok',
                    'description' => 'Sistem belum menemukan produk berdasarkan nama atau kategori item. Coba cari manual di katalog supplier.',
                    'master_product' => null,
                    'updated_at' => now()->format('d M Y H:i'),
                ],
            ];
        }

        $masterProduk = $this->resolveOrCreateMasterProdukForInventoryItem($item);
        $rankingBySupplier = collect();
        $usedSpk = false;

        if ($masterProduk && $userId) {
            $this->prepareRestockDssData($masterProduk, $products, $userId);
            $rankings = $this->sawRecommender->getRecommendations($userId, $masterProduk->id, true);
            $rankingBySupplier = $rankings->keyBy('supplier_id');
            $usedSpk = $rankingBySupplier->isNotEmpty();
        }

        $minPrice = max(1, (int) $products->min('harga'));
        $maxPrice = max($minPrice, (int) $products->max('harga'));

        $recommendations = $products
            ->map(function (SupplierProduct $product) use ($item, $userId, $restockNeed, $rankingBySupplier, $minPrice, $maxPrice) {
                $store = $product->store;
                $supplier = $store ? $this->supplierForStore($store) : null;
                $distance = $store ? $this->distanceService->distanceToCoordinates($store->latitude, $store->longitude, $userId) : null;
                $ranking = $supplier ? $rankingBySupplier->get($supplier->id) : null;
                $score = $ranking
                    ? (float) $ranking->final_score
                    : $this->fallbackProductScore($product, $distance, $minPrice, $maxPrice);
                $conversionQty = $this->guessConversionQty($item, $product);
                $quantity = $this->recommendedProductQuantity($product, (float) $restockNeed['needed_inventory_qty'], $conversionQty);

                return [
                    'rank' => null,
                    'product_id' => $product->id,
                    'product_name' => $product->nama,
                    'description' => $product->deskripsi,
                    'category' => $product->kategori ?: 'Umum',
                    'image' => $this->imageUrl($product->gambar),
                    'store_name' => $store?->nama ?? 'Toko supplier',
                    'store_location' => $store?->alamat ?? 'Alamat belum tersedia',
                    'store_phone' => $store?->phone,
                    'whatsapp_url' => $this->whatsappUrl($store?->phone, $item, $quantity, $product),
                    'supplier_id' => $supplier?->id,
                    'supplier_rating' => $supplier?->rating ? number_format((float) $supplier->rating, 1) : null,
                    'price' => (int) $product->harga,
                    'price_label' => 'Rp '.number_format((int) $product->harga, 0, ',', '.'),
                    'stock' => (int) $product->stok,
                    'unit' => $product->satuan,
                    'quantity' => $quantity,
                    'quantity_label' => $quantity.' '.$product->satuan,
                    'subtotal' => $quantity * (int) $product->harga,
                    'subtotal_label' => 'Rp '.number_format($quantity * (int) $product->harga, 0, ',', '.'),
                    'conversion_label' => '1 '.$product->satuan.' ~ '.$this->numberLabel($conversionQty).' '.$item->unit,
                    'distance_label' => $this->distanceService->distanceLabel($distance),
                    'distance_value' => $distance,
                    'delivery_label' => $this->distanceService->deliveryEstimateLabel($distance),
                    'score' => round($score, 4),
                    'score_percent' => (int) round(min(1, max(0, $score)) * 100),
                    'score_source' => $ranking ? 'AHP-SAW' : 'Estimasi',
                    'ranking_position' => $ranking?->ranking,
                    'search_url' => route('spk.suppliers.products', ['search' => $product->nama]),
                ];
            })
            ->sortBy([
                ['score', 'desc'],
                ['price', 'asc'],
            ])
            ->values()
            ->map(function (array $recommendation, int $index) {
                $recommendation['rank'] = $index + 1;

                return $recommendation;
            })
            ->take(8)
            ->all();

        return [
            'recommendations' => $recommendations,
            'summary' => [
                'mode' => $usedSpk ? 'spk' : 'fallback',
                'title' => $usedSpk ? 'Diranking dengan AHP-SAW' : 'Diranking dengan estimasi awal',
                'description' => $usedSpk
                    ? 'Sistem memakai bobot AHP aktif dan metode SAW untuk memilih supplier sesuai kebutuhan restock.'
                    : 'Sistem belum menemukan bobot AHP valid untuk user ini, jadi urutan memakai estimasi harga, jarak, dan ketersediaan stok.',
                'master_product' => $masterProduk?->nama,
                'updated_at' => now()->format('d M Y H:i'),
            ],
        ];
    }

    private function prepareRestockDssData(MasterProduk $masterProduk, $products, string $userId): void
    {
        $priceParameter = $this->parameterByKeyword('harga');
        $qualityParameter = $this->parameterByKeyword('kualitas');
        $supplierProducts = collect($products)
            ->map(function (SupplierProduct $product) {
                $store = $product->store;
                $supplier = $store ? $this->supplierForStore($store) : null;

                return $supplier ? ['supplier' => $supplier, 'product' => $product] : null;
            })
            ->filter()
            ->groupBy(fn (array $row) => $row['supplier']->id)
            ->map(fn ($rows) => collect($rows)->sortBy(fn (array $row) => (int) $row['product']->harga)->first())
            ->values();

        $supplierIds = $supplierProducts->pluck('supplier.id')->filter()->values()->all();
        if (empty($supplierIds)) {
            return;
        }

        DB::transaction(function () use ($masterProduk, $supplierProducts, $supplierIds, $priceParameter, $qualityParameter, $userId) {
            $masterProduk->suppliers()->syncWithoutDetaching($supplierIds);

            foreach ($supplierProducts as $row) {
                /** @var MasterSupplier $supplier */
                $supplier = $row['supplier'];
                /** @var SupplierProduct $product */
                $product = $row['product'];

                if ($priceParameter) {
                    SpkSupplierParameterValue::query()->updateOrCreate(
                        [
                            'supplier_id' => $supplier->id,
                            'produk_id' => $masterProduk->id,
                            'parameter_id' => $priceParameter->id,
                        ],
                        ['value' => max(1, (float) $product->harga)]
                    );
                }

                if ($qualityParameter) {
                    SpkSupplierParameterValue::query()->updateOrCreate(
                        [
                            'supplier_id' => $supplier->id,
                            'produk_id' => $masterProduk->id,
                            'parameter_id' => $qualityParameter->id,
                        ],
                        ['value' => $this->supplierQualityValue($supplier)]
                    );
                }
            }

            $this->ensureDefaultAhpWeights($userId);
            SpkRanking::query()
                ->where('user_id', $userId)
                ->where('produk_id', $masterProduk->id)
                ->update(['is_valid' => false]);
        });
    }

    private function ensureDefaultAhpWeights(string $userId): void
    {
        $existingCount = SpkAhpBobot::query()
            ->where('user_id', $userId)
            ->count();

        if ($existingCount > 0) {
            return;
        }

        $parameters = SpkParameter::query()->get();
        if ($parameters->isEmpty()) {
            return;
        }

        $rawWeights = $parameters->mapWithKeys(function (SpkParameter $parameter) {
            $name = strtolower($parameter->nama_parameter);
            $weight = 0.1;
            if (str_contains($name, 'harga')) {
                $weight = 0.35;
            } elseif (str_contains($name, 'jarak')) {
                $weight = 0.30;
            } elseif (str_contains($name, 'pengiriman') || str_contains($name, 'waktu')) {
                $weight = 0.20;
            } elseif (str_contains($name, 'kualitas')) {
                $weight = 0.15;
            }

            return [$parameter->id => $weight];
        });

        $sum = max(0.0001, (float) $rawWeights->sum());
        foreach ($parameters as $parameter) {
            SpkAhpBobot::query()->updateOrCreate(
                [
                    'user_id' => $userId,
                    'parameter_id' => $parameter->id,
                ],
                [
                    'bobot' => round(((float) $rawWeights[$parameter->id]) / $sum, 6),
                    'is_valid' => true,
                ]
            );
        }
    }

    private function resolveOrCreateMasterProdukForInventoryItem(InventoryItem $item): ?MasterProduk
    {
        $name = trim((string) $item->name);
        if ($name === '') {
            return null;
        }

        $normalized = $this->normalizeProductName($name);
        $masterProduk = MasterProduk::query()
            ->get()
            ->first(function (MasterProduk $produk) use ($normalized) {
                $masterName = $this->normalizeProductName($produk->nama);

                return $masterName === $normalized
                    || str_contains($masterName, $normalized)
                    || str_contains($normalized, $masterName);
            });

        return $masterProduk ?? MasterProduk::query()->firstOrCreate(
            ['nama' => $name],
            ['deskripsi' => 'Produk restock otomatis dari inventaris: '.$item->category]
        );
    }

    private function supplierForStore(SupplierStore $store): ?MasterSupplier
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $store->phone);
        $supplier = MasterSupplier::query()
            ->where(function ($query) use ($store, $phone) {
                $query->where('nama', $store->nama);

                if ($phone !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(kontak, '+', ''), '-', ''), ' ', '') = ?", [$phone]);
                }
            })
            ->first();

        if ($supplier) {
            $supplier->fill([
                'alamat' => $supplier->alamat ?: $store->alamat,
                'latitude' => $supplier->latitude ?? $store->latitude,
                'longitude' => $supplier->longitude ?? $store->longitude,
                'kontak' => $supplier->kontak ?: $store->phone,
                'kategori' => $supplier->kategori ?: $store->kategori,
            ])->save();

            return $supplier;
        }

        return MasterSupplier::query()->create([
            'nama' => $store->nama ?: 'Toko Supplier',
            'alamat' => $store->alamat,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'kontak' => $store->phone,
            'deskripsi' => $store->deskripsi,
            'kategori' => $store->kategori ?: 'Pakan, Vitamin, Obat',
            'rating' => 0,
            'jarak_km' => null,
            'logo_url' => $store->logoToko,
        ]);
    }

    private function parameterByKeyword(string $keyword): ?SpkParameter
    {
        return SpkParameter::query()
            ->where('nama_parameter', 'like', '%'.$keyword.'%')
            ->first();
    }

    private function supplierQualityValue(MasterSupplier $supplier): float
    {
        $rating = (float) ($supplier->rating ?? 0);
        if ($rating <= 0) {
            return 3.0;
        }

        return round(max(1, min(5, $rating)), 2);
    }

    private function fallbackProductScore(SupplierProduct $product, ?float $distance, int $minPrice, int $maxPrice): float
    {
        $priceRange = max(1, $maxPrice - $minPrice);
        $priceScore = 1 - (((int) $product->harga - $minPrice) / $priceRange);
        $distanceScore = $distance !== null ? max(0.35, 1 - (min($distance, 80) / 100)) : 0.7;
        $stockScore = min(1, max(0.35, (int) $product->stok / 50));

        return round(($priceScore * 0.45) + ($distanceScore * 0.35) + ($stockScore * 0.20), 4);
    }

    private function recommendedProductQuantity(SupplierProduct $product, float $neededInventoryQty, float $conversionQty): int
    {
        $quantity = max(1, (int) ceil($neededInventoryQty / max($conversionQty, 0.0001)));

        return max(1, min($quantity, max(1, (int) $product->stok)));
    }

    private function supplierCartSummary(Request $request): array
    {
        $cart = collect($request->session()->get('supplier_cart', []))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0);

        if ($cart->isEmpty()) {
            return [
                'total_quantity' => 0,
                'subtotal' => 0,
            ];
        }

        $products = SupplierProduct::query()
            ->whereIn('id', $cart->keys())
            ->where('isDeleted', false)
            ->get();

        return [
            'total_quantity' => (int) $cart->sum(),
            'subtotal' => (int) $products->sum(fn (SupplierProduct $product) => (int) $product->harga * (int) ($cart[$product->id] ?? 0)),
        ];
    }

    private function whatsappUrl(?string $phone, InventoryItem $item, int $quantity, SupplierProduct $product): ?string
    {
        $normalized = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($normalized === '') {
            return null;
        }

        if (Str::startsWith($normalized, '0')) {
            $normalized = '62'.substr($normalized, 1);
        }

        $message = "Halo, saya ingin memesan {$quantity} {$product->satuan} {$product->nama} untuk restock {$item->name}.";

        return 'https://wa.me/'.$normalized.'?text='.rawurlencode($message);
    }

    private function imageUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        return Str::startsWith($path, ['http://', 'https://'])
            ? $path
            : asset('storage/'.$path);
    }

    private function normalizeProductName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/(\d+)\s+(kg|g|mg|ml|l|liter|dosis|butir)\b/', '$1$2', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;

        return trim($name);
    }

    private function coveragePercent(?int $daysLeft, int $leadTime, int $safetyDays, string $status): int
    {
        if ($daysLeft === null) {
            return $status === 'optimal' ? 100 : 40;
        }

        $targetDays = max(1, $leadTime + max(1, $safetyDays));

        return (int) max(3, min(100, round(($daysLeft / $targetDays) * 100)));
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
