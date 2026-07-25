<?php

namespace App\Http\Controllers\Supplier;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\MasterSupplier;
use App\Models\ProductUnit;
use App\Models\SpkRanking;
use App\Models\SupplierOrder;
use App\Models\SupplierProduct;
use App\Models\SupplierProductCategory;
use App\Models\SupplierProductStockMovement;
use App\Models\SupplierStore;
use App\Services\ApiService;
use App\Services\Notifications\SupplierNotificationService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierPanelController extends Controller
{
    public function __construct(
        private readonly ApiService $api,
        private readonly SupplierNotificationService $supplierNotificationService,
    ) {}

    public function dashboard(): View
    {
        $store = $this->store();
        $products = collect();
        $recentOrders = collect();
        $metrics = [
            'products' => 0,
            'low_stock' => 0,
            'pending_orders' => 0,
            'monthly_revenue' => 0,
        ];
        $salesChart = $this->emptySalesChart();

        if ($store) {
            $products = $store->products()
                ->where('isDeleted', false)
                ->orderBy('stok')
                ->limit(5)
                ->get();

            $recentOrders = $store->orders()
                ->with(['customer:id,name,email', 'details.product'])
                ->where('isDeleted', false)
                ->latest('createdAt')
                ->limit(5)
                ->get();

            $metrics = [
                'products' => $store->products()->where('isDeleted', false)->count(),
                'low_stock' => $store->products()
                    ->where('isDeleted', false)
                    ->whereRaw('stok <= COALESCE(minimum_stock, 10)')
                    ->count(),
                'pending_orders' => $store->orders()
                    ->where('isDeleted', false)
                    ->where('status', 'menunggu')
                    ->count(),
                'monthly_revenue' => $store->orders()
                    ->where('isDeleted', false)
                    ->where('status', 'selesai')
                    ->whereBetween('createdAt', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('totalHarga'),
            ];

            $salesChart = $this->salesChart($store);
        }

        return view('supplier.dashboard', compact(
            'store',
            'products',
            'recentOrders',
            'metrics',
            'salesChart'
        ));
    }

    public function editStore(): View
    {
        return view('supplier.store', ['store' => $this->store()]);
    }

    public function updateStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'alamat' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'deskripsi' => 'nullable|string|max:2000',
            'kategori' => 'nullable|string|max:120',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $store = $this->store();
        $logoPath = $store?->logoToko;

        if ($request->hasFile('logo')) {
            if ($logoPath && ! Str::startsWith($logoPath, ['http://', 'https://'])) {
                Storage::disk('public')->delete($logoPath);
            }

            $logoPath = $request->file('logo')
                ->store("supplier-stores/{$this->userId()}", 'public');
        }

        SupplierStore::query()->updateOrCreate(
            ['userId' => $this->userId(), 'isDeleted' => false],
            [
                'id' => $store?->id ?? Str::uuid()->toString(),
                'nama' => $validated['nama'],
                'phone' => $validated['phone'],
                'alamat' => $validated['alamat'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $validated['kategori'] ?? null,
                'logoToko' => $logoPath,
                'tokoStatus' => $store?->tokoStatus ?? 'request',
                'TypeToko' => $store?->TypeToko ?? 'umkm',
            ]
        );

        $supplier = MasterSupplier::query()->where('nama', $validated['nama'])->first();
        if ($supplier) {
            $supplier->update([
                'alamat' => $validated['alamat'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'kontak' => $validated['phone'],
                'deskripsi' => $validated['deskripsi'] ?? $supplier->deskripsi,
                'kategori' => $validated['kategori'] ?? $supplier->kategori,
            ]);

            SpkRanking::query()->where('supplier_id', $supplier->id)->update(['is_valid' => false]);
        }

        return redirect()->route('supplier.store.edit')
            ->with('success', $store
                ? 'Profil toko berhasil diperbarui.'
                : 'Toko berhasil diajukan dan menunggu aktivasi admin.');
    }

    public function products(Request $request): View|RedirectResponse
    {
        $store = $this->store();
        if (! $store) {
            return $this->missingStoreRedirect();
        }

        $categoryOptions = SupplierProductCategory::activeOptions();
        $unitOptions = ProductUnit::activeOptions();
        $query = $store->products()->where('isDeleted', false);
        if ($request->filled('search')) {
            $query->where('nama', 'like', '%'.$request->string('search').'%');
        }
        if ($request->get('stock') === 'low') {
            $query->whereRaw('stok <= COALESCE(minimum_stock, 10)');
        } elseif ($request->get('stock') === 'available') {
            $query->whereRaw('stok > COALESCE(minimum_stock, 10)');
        }
        $selectedCategory = trim((string) $request->string('category'));
        if ($selectedCategory !== '' && $categoryOptions->contains($selectedCategory)) {
            $query->where('kategori', $selectedCategory);
        }

        return view('supplier.products', [
            'store' => $store,
            'products' => $query->latest('createdAt')->paginate(12)->withQueryString(),
            'categoryOptions' => $categoryOptions,
            'unitOptions' => $unitOptions,
        ]);
    }

    public function createProduct(): View|RedirectResponse
    {
        $store = $this->store();
        if (! $store) {
            return $this->missingStoreRedirect();
        }

        return view('supplier.product-form', [
            'store' => $store,
            'product' => null,
            'categoryOptions' => SupplierProductCategory::activeOptions(),
            'unitOptions' => ProductUnit::activeOptions(),
        ]);
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $store = $this->requiredStore();
        $validated = $this->validateProduct($request, true);

        $imagePath = $request->hasFile('gambar')
            ? $request->file('gambar')->store("supplier-products/{$store->id}", 'public')
            : null;

        $store->products()->create([
            'id' => Str::uuid()->toString(),
            'nama' => $validated['nama'],
            'deskripsi' => $validated['deskripsi'],
            'kategori' => $validated['kategori'] ?? null,
            'gambar' => $imagePath,
            'stok' => $validated['stok'],
            'minimum_stock' => $validated['minimum_stock'],
            'restock_quantity' => $validated['restock_quantity'],
            'satuan' => $validated['satuan'],
            'harga' => $validated['harga'],
            'isDeleted' => false,
        ]);

        return redirect()->route('supplier.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function editProduct(SupplierProduct $product): View
    {
        $this->ensureProductOwnership($product);

        return view('supplier.product-form', [
            'store' => $this->requiredStore(),
            'product' => $product,
            'categoryOptions' => SupplierProductCategory::activeOptions(),
            'unitOptions' => ProductUnit::activeOptions(),
        ]);
    }

    public function updateProduct(Request $request, SupplierProduct $product): RedirectResponse
    {
        $this->ensureProductOwnership($product);
        $validated = $this->validateProduct($request, false);
        $imagePath = $product->gambar;

        if ($request->hasFile('gambar')) {
            if ($imagePath && ! Str::startsWith($imagePath, ['http://', 'https://'])) {
                Storage::disk('public')->delete($imagePath);
            }

            $imagePath = $request->file('gambar')
                ->store("supplier-products/{$product->tokoId}", 'public');
        }

        $product->update([
            'nama' => $validated['nama'],
            'deskripsi' => $validated['deskripsi'],
            'kategori' => $validated['kategori'] ?? null,
            'gambar' => $imagePath,
            'minimum_stock' => $validated['minimum_stock'],
            'restock_quantity' => $validated['restock_quantity'],
            'satuan' => $validated['satuan'],
            'harga' => $validated['harga'],
        ]);

        return redirect()->route('supplier.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function editProductStock(SupplierProduct $product): View
    {
        $this->ensureProductOwnership($product);

        return view('supplier.product-stock', [
            'store' => $this->requiredStore(),
            'product' => $product,
            'movements' => $product->stockMovements()
                ->latest('createdAt')
                ->limit(15)
                ->get(),
        ]);
    }

    public function adjustProductStock(Request $request, SupplierProduct $product): RedirectResponse
    {
        $this->ensureProductOwnership($product);
        $validated = $request->validate([
            'type' => 'required|in:restock,correction_in,correction_out',
            'quantity' => 'required|integer|min:1|max:100000000',
            'note' => 'nullable|string|max:500',
        ], [
            'type.in' => 'Pilih jenis pergerakan stok yang valid.',
            'quantity.min' => 'Jumlah stok harus minimal 1.',
        ]);

        $label = [
            'restock' => 'Restock',
            'correction_in' => 'Koreksi tambah',
            'correction_out' => 'Koreksi kurang',
        ][$validated['type']];

        try {
            DB::transaction(function () use ($product, $validated) {
                $locked = SupplierProduct::query()
                    ->whereKey($product->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $before = (int) $locked->stok;
                $delta = in_array($validated['type'], ['restock', 'correction_in'], true)
                    ? (int) $validated['quantity']
                    : -1 * (int) $validated['quantity'];
                $after = $before + $delta;

                if ($after < 0) {
                    throw new \RuntimeException('Stok tidak boleh menjadi minus.');
                }

                $locked->update(['stok' => $after]);

                SupplierProductStockMovement::query()->create([
                    'supplier_product_id' => $locked->id,
                    'supplier_store_id' => $locked->tokoId,
                    'type' => $validated['type'],
                    'quantity' => $delta,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'actor_id' => $this->userId(),
                    'note' => $validated['note'] ?? null,
                ]);
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', "{$label} stok berhasil dicatat.");
    }

    public function destroyProduct(SupplierProduct $product): RedirectResponse
    {
        $this->ensureProductOwnership($product);
        $product->update(['isDeleted' => true]);

        return redirect()->route('supplier.products.index')
            ->with('success', 'Produk dinonaktifkan dari toko.');
    }

    public function orders(Request $request): View|RedirectResponse
    {
        $store = $this->store();
        if (! $store) {
            return $this->missingStoreRedirect();
        }

        $query = $store->orders()
            ->with(['customer:id,name,email', 'details.product'])
            ->where('isDeleted', false);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($orderQuery) use ($search) {
                $orderQuery->where('id', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            });
        }

        return view('supplier.orders', [
            'store' => $store,
            'orders' => $query->latest('createdAt')->paginate(15)->withQueryString(),
            'statusCounts' => $store->orders()
                ->where('isDeleted', false)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }

    public function updateOrderStatus(Request $request, SupplierOrder $order): RedirectResponse
    {
        $store = $this->requiredStore();
        abort_unless($order->tokoId === $store->id && ! $order->isDeleted, 404);

        $validated = $request->validate([
            'status' => 'required|in:diterima,selesai,ditolak',
            'reason' => [
                Rule::requiredIf(fn () => $request->input('status') === 'ditolak'),
                'nullable',
                'string',
                'max:500',
            ],
        ], [
            'reason.required' => 'Alasan penolakan pesanan wajib diisi.',
        ]);

        $transitions = [
            'menunggu' => ['diterima', 'ditolak'],
            'diterima' => ['selesai'],
            'selesai' => [],
            'ditolak' => [],
            'dibatalkan' => [],
            'expired' => [],
        ];

        if (! in_array($validated['status'], $transitions[$order->status] ?? [], true)) {
            return back()->with('error', 'Perubahan status pesanan tidak valid.');
        }

        if ($validated['status'] === 'diterima' && $this->shouldDeductStockOnAcceptance($order)) {
            $stockError = $this->orderStockValidationError($order);
            if ($stockError) {
                return back()->with('error', $stockError);
            }
        }

        try {
            $this->api->put('/store/pesanan/status', [
                'pesananId' => $order->id,
                'status' => $validated['status'],
            ]);
        } catch (ApiException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $reason = trim((string) ($validated['reason'] ?? ''));

        try {
            DB::transaction(function () use ($order, $validated, $reason) {
                $lockedOrder = SupplierOrder::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($validated['status'] === 'diterima' && $this->shouldDeductStockOnAcceptance($lockedOrder)) {
                    $this->deductStockForAcceptedOrder($lockedOrder);
                }

                $lockedOrder->forceFill([
                    'status' => $validated['status'],
                    'statusReason' => $validated['status'] === 'ditolak' && $reason !== '' ? $reason : null,
                    'statusChangedAt' => now(),
                ])->save();
            });
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        if ($validated['status'] === 'ditolak') {
            $this->supplierNotificationService->orderRejected($order->fresh(['customer', 'store']), $reason);
        }

        return back()->with('success', 'Status pesanan berhasil diperbarui.');
    }

    public function finance(Request $request): View|RedirectResponse
    {
        $store = $this->store();
        if (! $store) {
            return $this->missingStoreRedirect();
        }

        $year = (int) $request->integer('year', now()->year);
        $completedOrders = $store->orders()
            ->where('isDeleted', false)
            ->where('status', 'selesai')
            ->whereYear('createdAt', $year)
            ->orderByDesc('createdAt')
            ->get();

        $monthly = collect(range(1, 12))->map(function (int $month) use ($completedOrders) {
            $orders = $completedOrders->filter(
                fn (SupplierOrder $order) => $order->createdAt->month === $month
            );

            return [
                'month' => Carbon::create()->month($month)->translatedFormat('M'),
                'orders' => $orders->count(),
                'revenue' => $orders->sum('totalHarga'),
            ];
        });

        return view('supplier.finance', [
            'store' => $store,
            'year' => $year,
            'monthly' => $monthly,
            'completedOrders' => $completedOrders->take(20),
            'totalRevenue' => $completedOrders->sum('totalHarga'),
            'averageOrder' => $completedOrders->count() > 0
                ? (int) round($completedOrders->avg('totalHarga'))
                : 0,
        ]);
    }

    private function validateProduct(Request $request, bool $includeStock): array
    {
        $rules = [
            'nama' => 'required|string|max:255',
            'deskripsi' => 'required|string|max:2000',
            'kategori' => ['required', 'string', 'max:80', Rule::in(SupplierProductCategory::activeOptions()->all())],
            'minimum_stock' => 'required|integer|min:0|max:100000000',
            'restock_quantity' => 'nullable|integer|min:0|max:100000000',
            'satuan' => ['required', 'string', 'max:50', Rule::in(ProductUnit::activeOptions()->all())],
            'harga' => 'required|integer|min:0|max:2000000000',
            'gambar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ];

        if ($includeStock) {
            $rules['stok'] = 'required|integer|min:0|max:100000000';
        }

        $validated = $request->validate($rules);

        $validated['restock_quantity'] = (int) ($validated['restock_quantity'] ?? 0);

        return $validated;
    }

    private function shouldDeductStockOnAcceptance(SupplierOrder $order): bool
    {
        return blank($order->MidtransOrderId);
    }

    private function orderStockValidationError(SupplierOrder $order): ?string
    {
        $details = $order->details()
            ->with('product')
            ->where('isDeleted', false)
            ->get();

        if ($details->isEmpty()) {
            return 'Rincian pesanan tidak tersedia, stok tidak dapat diproses.';
        }

        foreach ($details as $detail) {
            $product = $detail->product;
            $quantity = (int) $detail->jumlah;

            if (! $product || $product->isDeleted || $product->tokoId !== $order->tokoId) {
                return 'Ada produk pesanan yang tidak tersedia di toko ini.';
            }

            if ($quantity <= 0) {
                return 'Jumlah produk pada pesanan tidak valid.';
            }

            if ((int) $product->stok < $quantity) {
                return 'Stok '.$product->nama.' tidak mencukupi. Tersisa '
                    .number_format((int) $product->stok, 0, ',', '.').' '.$product->satuan
                    .', pesanan '.number_format($quantity, 0, ',', '.').'.';
            }
        }

        return null;
    }

    private function deductStockForAcceptedOrder(SupplierOrder $order): void
    {
        $details = $order->details()
            ->where('isDeleted', false)
            ->get();

        if ($details->isEmpty()) {
            throw new \RuntimeException('Rincian pesanan tidak tersedia, stok tidak dapat diproses.');
        }

        foreach ($details as $detail) {
            $quantity = (int) $detail->jumlah;
            if ($quantity <= 0) {
                throw new \RuntimeException('Jumlah produk pada pesanan tidak valid.');
            }

            $product = SupplierProduct::query()
                ->whereKey($detail->produkId)
                ->where('tokoId', $order->tokoId)
                ->where('isDeleted', false)
                ->lockForUpdate()
                ->first();

            if (! $product) {
                throw new \RuntimeException('Ada produk pesanan yang tidak tersedia di toko ini.');
            }

            if ($this->acceptedOrderStockMovementExists($order, $product)) {
                continue;
            }

            $before = (int) $product->stok;
            if ($before < $quantity) {
                throw new \RuntimeException('Stok '.$product->nama.' tidak mencukupi. Tersisa '
                    .number_format($before, 0, ',', '.').' '.$product->satuan
                    .', pesanan '.number_format($quantity, 0, ',', '.').'.');
            }

            $after = $before - $quantity;
            $product->update(['stok' => $after]);

            SupplierProductStockMovement::query()->create([
                'supplier_product_id' => $product->id,
                'supplier_store_id' => $order->tokoId,
                'type' => 'order_accepted',
                'quantity' => -$quantity,
                'stock_before' => $before,
                'stock_after' => $after,
                'actor_id' => $this->userId(),
                'note' => 'Pesanan diterima '.$order->id,
            ]);
        }
    }

    private function acceptedOrderStockMovementExists(SupplierOrder $order, SupplierProduct $product): bool
    {
        return SupplierProductStockMovement::query()
            ->where('supplier_product_id', $product->id)
            ->where('supplier_store_id', $order->tokoId)
            ->where('type', 'order_accepted')
            ->where('note', 'like', '%'.$order->id.'%')
            ->exists();
    }

    private function store(): ?SupplierStore
    {
        return SupplierStore::query()
            ->where('userId', $this->userId())
            ->where('isDeleted', false)
            ->first();
    }

    private function requiredStore(): SupplierStore
    {
        return $this->store() ?? abort(404, 'Toko supplier belum dibuat.');
    }

    private function ensureProductOwnership(SupplierProduct $product): void
    {
        abort_unless(
            $product->tokoId === $this->requiredStore()->id && ! $product->isDeleted,
            404
        );
    }

    private function userId(): string
    {
        return (string) session('user.id');
    }

    private function missingStoreRedirect(): RedirectResponse
    {
        return redirect()->route('supplier.store.edit')
            ->with('warning', 'Lengkapi profil toko sebelum mengelola data.');
    }

    private function emptySalesChart(): array
    {
        return [
            'labels' => collect(range(5, 0))
                ->map(fn (int $offset) => now()->subMonths($offset)->translatedFormat('M'))
                ->all(),
            'values' => array_fill(0, 6, 0),
        ];
    }

    private function salesChart(SupplierStore $store): array
    {
        $start = now()->subMonths(5)->startOfMonth();
        $orders = $store->orders()
            ->where('isDeleted', false)
            ->where('status', 'selesai')
            ->where('createdAt', '>=', $start)
            ->get(['totalHarga', 'createdAt']);

        $months = collect(range(5, 0))->map(fn (int $offset) => now()->subMonths($offset));

        return [
            'labels' => $months->map(fn (Carbon $month) => $month->translatedFormat('M'))->all(),
            'values' => $months->map(fn (Carbon $month) => $orders
                ->filter(fn (SupplierOrder $order) => $order->createdAt->isSameMonth($month))
                ->sum('totalHarga'))->all(),
        ];
    }
}
