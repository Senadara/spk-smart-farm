<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkRanking;
use App\Models\SpkSupplierParameterValue;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderDetail;
use App\Models\SupplierOrderRating;
use App\Models\SupplierProduct;
use App\Models\SupplierProductCategory;
use App\Models\SupplierStore;
use App\Services\SupplierDistanceService;
use App\Support\SpkDssActorId;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierRecommendationController extends Controller
{
    public function __construct(
        private SupplierDistanceService $distanceService,
    ) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $category = $request->input('category', 'all');
        $dssActorId = SpkDssActorId::resolve($request);

        $query = MasterSupplier::query();

        if ($category !== 'all') {
            $query->where('kategori', 'like', '%'.$category.'%');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderBy('nama')->get()
            ->map(fn ($s) => $this->formatSupplierCard($s, $dssActorId))
            ->filter(fn (array $supplier) => $supplier['store_status'] === null || $supplier['store_status'] === 'active')
            ->sortBy(fn (array $supplier) => $supplier['distance_value'] ?? 9999)
            ->values();
        $orderSummary = $this->orderSummary($dssActorId);

        return view('spk.suppliers.index', [
            'suppliers' => $suppliers,
            'category' => $category,
            'search' => $search,
            'orderSummary' => $orderSummary,
        ]);
    }

    public function create(): View
    {
        return view('spk.suppliers.form', [
            'supplier' => null,
            'store' => null,
            'categoryOptions' => SupplierProductCategory::activeOptions(),
            'selectedCategories' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateManagedSupplier($request);
        $phone = $this->normalizeWhatsApp($validated['whatsapp']);
        $categories = $this->normalizeSupplierCategories($validated['kategori'] ?? []);

        $supplier = DB::transaction(function () use ($validated, $phone, $categories) {
            $supplier = MasterSupplier::query()->create([
                'nama' => $validated['nama'],
                'alamat' => $validated['alamat'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'kontak' => $phone,
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $categories,
                'rating' => 0,
                'jarak_km' => null,
            ]);

            $this->syncManagedSupplierStore($supplier, null, $categories);

            return $supplier;
        });

        return redirect()
            ->route('spk.suppliers.show', $supplier->id)
            ->with('success', 'Mitra supplier berhasil ditambahkan. Data lokasi dan WhatsApp sudah siap dipakai untuk pencarian dan estimasi jarak.');
    }

    public function edit(MasterSupplier $supplier): View
    {
        $store = $this->storeForSupplier($supplier);

        return view('spk.suppliers.form', [
            'supplier' => $supplier,
            'store' => $store,
            'categoryOptions' => SupplierProductCategory::activeOptions(),
            'selectedCategories' => $this->supplierCategoryArray($supplier->kategori),
        ]);
    }

    public function update(Request $request, MasterSupplier $supplier): RedirectResponse
    {
        $validated = $this->validateManagedSupplier($request);
        $phone = $this->normalizeWhatsApp($validated['whatsapp']);
        $categories = $this->normalizeSupplierCategories($validated['kategori'] ?? []);
        $store = $this->storeForSupplier($supplier);

        DB::transaction(function () use ($supplier, $store, $validated, $phone, $categories) {
            $supplier->update([
                'nama' => $validated['nama'],
                'alamat' => $validated['alamat'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'kontak' => $phone,
                'deskripsi' => $validated['deskripsi'] ?? null,
                'kategori' => $categories,
            ]);

            $this->syncManagedSupplierStore($supplier, $store, $categories);

            SpkRanking::query()
                ->where('supplier_id', $supplier->id)
                ->update(['is_valid' => false]);
        });

        return redirect()
            ->route('spk.suppliers.show', $supplier->id)
            ->with('success', 'Data mitra supplier berhasil diperbarui.');
    }

    public function products(Request $request)
    {
        $search = $request->input('search');
        $category = $request->input('category', 'all');
        $filterSort = $request->input('sort', 'recommended');
        $filterStock = $request->input('stock', 'all');
        $dssActorId = SpkDssActorId::resolve($request);
        $categoryOptions = SupplierProductCategory::activeOptions();

        if ($category !== 'all' && ! $categoryOptions->contains($category)) {
            $category = 'all';
        }

        $productsQuery = SupplierProduct::query()
            ->with('store')
            ->where('isDeleted', false)
            ->where('stok', '>', 0)
            ->whereHas('store', fn ($query) => $query
                ->where('isDeleted', false)
                ->where('tokoStatus', 'active'));

        if ($search) {
            $productsQuery->where(function ($query) use ($search) {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%")
                    ->orWhere('kategori', 'like', "%{$search}%")
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('nama', 'like', "%{$search}%"));
            });
        }

        if ($category !== 'all') {
            $productsQuery->where(function ($query) use ($category) {
                $query->where('kategori', $category)
                    ->orWhere('nama', 'like', "%{$category}%")
                    ->orWhere('deskripsi', 'like', "%{$category}%");
            });
        }

        if ($filterStock === 'low') {
            $productsQuery->where('stok', '<=', 10);
        }

        $products = $productsQuery->get()
            ->map(fn (SupplierProduct $product) => $this->formatStoreProductCard($product, $dssActorId));

        $products = match ($filterSort) {
            'cheapest' => $products->sortBy('price')->values(),
            'closest' => $products->sortBy('distance_sort')->values(),
            default => $products->sortByDesc('score')->values(),
        };

        return view('spk.suppliers.products', [
            'products' => $products,
            'categoryOptions' => $categoryOptions,
            'category' => $category,
            'search' => $search,
            'filterSort' => $filterSort,
            'filterStock' => $filterStock,
            'cart' => $this->cartSummary($request),
        ]);
    }

    public function addToCart(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string|exists:produk,id',
            'quantity' => 'required|integer|min:1|max:100000',
        ]);

        $product = SupplierProduct::query()
            ->whereHas('store', fn ($query) => $query
                ->where('isDeleted', false)
                ->where('tokoStatus', 'active'))
            ->where('id', $validated['product_id'])
            ->where('isDeleted', false)
            ->where('stok', '>', 0)
            ->firstOrFail();

        $quantity = min((int) $validated['quantity'], (int) $product->stok);
        $cart = $request->session()->get('supplier_cart', []);
        $cart[$product->id] = min((int) ($cart[$product->id] ?? 0) + $quantity, (int) $product->stok);

        $request->session()->put('supplier_cart', $cart);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Barang ditambahkan ke keranjang.',
                'cart' => $this->cartSummary($request),
            ]);
        }

        return back()->with('success', 'Barang ditambahkan ke keranjang.');
    }

    public function removeFromCart(Request $request, SupplierProduct $product): RedirectResponse|JsonResponse
    {
        $cart = $request->session()->get('supplier_cart', []);
        unset($cart[$product->id]);
        $request->session()->put('supplier_cart', $cart);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Barang dihapus dari keranjang.',
                'cart' => $this->cartSummary($request),
            ]);
        }

        return back()->with('success', 'Barang dihapus dari keranjang.');
    }

    public function checkoutCart(Request $request): RedirectResponse
    {
        $userId = SpkDssActorId::resolve($request);
        if (! $userId) {
            return back()->with('error', 'User tidak dapat dipetakan untuk membuat pesanan.');
        }

        $cart = collect($request->session()->get('supplier_cart', []))
            ->map(fn ($qty) => (int) $qty)
            ->filter(fn ($qty) => $qty > 0);

        if ($cart->isEmpty()) {
            return back()->with('error', 'Keranjang masih kosong.');
        }

        $products = SupplierProduct::query()
            ->with('store')
            ->whereIn('id', $cart->keys())
            ->where('isDeleted', false)
            ->whereHas('store', fn ($query) => $query
                ->where('isDeleted', false)
                ->where('tokoStatus', 'active'))
            ->get();

        if ($products->isEmpty()) {
            $request->session()->forget('supplier_cart');

            return back()->with('error', 'Barang di keranjang tidak tersedia.');
        }

        DB::transaction(function () use ($products, $cart, $userId) {
            foreach ($products->groupBy('tokoId') as $storeId => $storeProducts) {
                $total = $storeProducts->sum(fn (SupplierProduct $product) => $product->harga * min($cart[$product->id] ?? 0, $product->stok));

                if ($total <= 0) {
                    continue;
                }

                $order = SupplierOrder::query()->create([
                    'id' => Str::uuid()->toString(),
                    'userId' => $userId,
                    'tokoId' => $storeId,
                    'status' => 'menunggu',
                    'totalHarga' => $total,
                    'isDeleted' => false,
                    'MidtransOrderId' => null,
                ]);

                foreach ($storeProducts as $product) {
                    $quantity = min((int) ($cart[$product->id] ?? 0), (int) $product->stok);
                    if ($quantity <= 0) {
                        continue;
                    }

                    SupplierOrderDetail::query()->create([
                        'id' => Str::uuid()->toString(),
                        'pesananId' => $order->id,
                        'produkId' => $product->id,
                        'jumlah' => $quantity,
                        'isDeleted' => false,
                    ]);
                }
            }
        });

        $request->session()->forget('supplier_cart');

        return redirect()
            ->route('spk.suppliers.orders.index')
            ->with('success', 'Pesanan dari keranjang dibuat. Hubungi supplier untuk konfirmasi pembayaran di luar sistem.');
    }

    public function show(Request $request, $id)
    {
        $supplier = MasterSupplier::with('produks')->findOrFail($id);
        $hargaParam = SpkParameter::where('nama_parameter', 'like', '%Harga%')->first();
        $dssActorId = SpkDssActorId::resolve($request);
        $store = $this->storeForSupplier($supplier);
        abort_if($store && $store->tokoStatus !== 'active', 404);
        $storeProducts = $store
            ? $store->products()
                ->where('isDeleted', false)
                ->where('stok', '>', 0)
                ->orderBy('nama')
                ->get()
            : collect();

        $inventories = [];
        foreach ($supplier->produks as $produk) {
            $price = SpkSupplierParameterValue::where('supplier_id', $supplier->id)
                ->where('produk_id', $produk->id)
                ->when($hargaParam, fn ($q) => $q->where('parameter_id', $hargaParam->id))
                ->value('value');

            $inventories[] = [
                'name' => $produk->nama,
                'price' => $price ?? 0,
                'stock' => 'Tersedia',
                'type' => $produk->deskripsi ?? 'Produk',
            ];
        }

        return view('spk.suppliers.show', [
            'supplier' => $this->formatSupplierCard($supplier, $dssActorId),
            'inventories' => $inventories,
            'store' => $store,
            'storeProducts' => $storeProducts,
        ]);
    }

    public function storeOrder(Request $request, $id): RedirectResponse
    {
        $supplier = MasterSupplier::query()->findOrFail($id);
        $store = $this->storeForSupplier($supplier);
        abort_if($store && $store->tokoStatus !== 'active', 404);
        if (! $store) {
            return back()->with('error', 'Supplier ini belum terhubung ke toko pemesanan sederhana.');
        }

        $validated = $request->validate([
            'product_id' => 'required|string|exists:produk,id',
            'quantity' => 'required|integer|min:1|max:100000',
        ]);

        $userId = SpkDssActorId::resolve($request);
        if (! $userId) {
            return back()->with('error', 'User tidak dapat dipetakan untuk membuat pesanan.');
        }

        $product = SupplierProduct::query()
            ->where('id', $validated['product_id'])
            ->where('tokoId', $store->id)
            ->where('isDeleted', false)
            ->firstOrFail();

        if ($product->stok < $validated['quantity']) {
            return back()->with('error', 'Stok produk tidak mencukupi untuk jumlah pesanan tersebut.');
        }

        DB::transaction(function () use ($product, $store, $userId, $validated) {
            $order = SupplierOrder::query()->create([
                'id' => Str::uuid()->toString(),
                'userId' => $userId,
                'tokoId' => $store->id,
                'status' => 'menunggu',
                'totalHarga' => $product->harga * (int) $validated['quantity'],
                'isDeleted' => false,
                'MidtransOrderId' => null,
            ]);

            SupplierOrderDetail::query()->create([
                'id' => Str::uuid()->toString(),
                'pesananId' => $order->id,
                'produkId' => $product->id,
                'jumlah' => (int) $validated['quantity'],
                'isDeleted' => false,
            ]);
        });

        return redirect()
            ->route('spk.suppliers.orders.index')
            ->with('success', 'Pesanan dibuat. Hubungi supplier untuk konfirmasi pembayaran di luar sistem.');
    }

    public function orders(Request $request)
    {
        $userId = SpkDssActorId::resolve($request);
        if (! $userId) {
            return redirect()
                ->route('spk.suppliers.index')
                ->with('error', 'User tidak dapat dipetakan untuk melihat histori pesanan.');
        }

        $status = $request->input('status', 'all');
        $allowedStatuses = ['all', 'menunggu', 'diterima', 'selesai', 'ditolak', 'dibatalkan', 'expired'];

        $query = SupplierOrder::query()
            ->with(['store', 'details.product', 'rating'])
            ->where('userId', $userId)
            ->where('isDeleted', false);

        if (in_array($status, $allowedStatuses, true) && $status !== 'all') {
            $query->where('status', $status);
        } else {
            $status = 'all';
        }

        $orders = $query->latest('createdAt')->paginate(10)->withQueryString();

        $statusCounts = SupplierOrder::query()
            ->where('userId', $userId)
            ->where('isDeleted', false)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('spk.suppliers.orders', [
            'orders' => $orders,
            'status' => $status,
            'statusCounts' => $statusCounts,
            'statusLabels' => $this->orderStatusLabels(),
        ]);
    }

    public function rateOrder(Request $request, SupplierOrder $order): RedirectResponse
    {
        $userId = SpkDssActorId::resolve($request);
        if (! $userId || $order->userId !== $userId || $order->isDeleted) {
            abort(404);
        }

        if ($order->status !== 'selesai') {
            return back()->with('error', 'Rating hanya dapat diberikan setelah pesanan berstatus selesai.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'note' => 'nullable|string|max:500',
        ]);

        $order->loadMissing(['store', 'details.product']);
        $supplier = $order->store ? $this->supplierForStore($order->store) : null;
        $firstProduct = $order->details->first(fn (SupplierOrderDetail $detail) => $detail->product !== null)?->product;
        $firstMasterProduk = $firstProduct ? $this->resolveOrCreateMasterProdukForSupplierProduct($firstProduct) : null;
        $rating = (int) $validated['rating'];
        $note = trim((string) ($validated['note'] ?? ''));

        DB::transaction(function () use ($order, $userId, $supplier, $firstProduct, $firstMasterProduk, $rating, $note) {
            SupplierOrderRating::query()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'user_id' => $userId,
                    'store_id' => $order->tokoId,
                    'supplier_id' => $supplier?->id,
                    'product_id' => $firstProduct?->id,
                    'master_produk_id' => $firstMasterProduk?->id,
                    'rating' => $rating,
                    'note' => $note !== '' ? $note : null,
                ]
            );

            if (! $supplier) {
                return;
            }

            $produkIds = $this->syncSupplierQualityRating($supplier, $order, $rating);
            if ($produkIds !== []) {
                SpkRanking::query()
                    ->where('supplier_id', $supplier->id)
                    ->whereIn('produk_id', $produkIds)
                    ->delete();
            }

            $averageRating = SupplierOrderRating::query()
                ->where('supplier_id', $supplier->id)
                ->avg('rating');

            $supplier->forceFill([
                'rating' => round((float) ($averageRating ?: $rating), 1),
            ])->save();
        });

        $message = $supplier
            ? 'Rating supplier berhasil disimpan dan nilai kualitas SPK diperbarui.'
            : 'Rating berhasil disimpan. Toko ini belum terhubung ke master supplier, sehingga nilai SPK belum diperbarui.';

        return back()->with('success', $message);
    }

    public function cancelOrder(Request $request, SupplierOrder $order): RedirectResponse
    {
        $userId = SpkDssActorId::resolve($request);
        if (! $userId || $order->userId !== $userId || $order->isDeleted) {
            abort(404);
        }

        if ($order->status !== 'menunggu') {
            return back()->with('error', 'Pesanan hanya bisa dibatalkan saat masih menunggu konfirmasi supplier.');
        }

        $order->update(['status' => 'dibatalkan']);

        return back()->with('success', 'Pesanan berhasil dibatalkan.');
    }

    private function validateManagedSupplier(Request $request): array
    {
        $categoryOptions = SupplierProductCategory::activeOptions()->all();

        return $request->validate([
            'nama' => 'required|string|max:255',
            'whatsapp' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s().]+$/'],
            'alamat' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'kategori' => 'required|array|min:1',
            'kategori.*' => ['required', 'string', Rule::in($categoryOptions)],
            'deskripsi' => 'nullable|string|max:2000',
        ], [
            'whatsapp.regex' => 'Nomor WhatsApp hanya boleh berisi angka, spasi, tanda +, tanda -, titik, atau kurung.',
            'kategori.required' => 'Pilih minimal satu kategori supplier.',
            'kategori.min' => 'Pilih minimal satu kategori supplier.',
            'kategori.*.in' => 'Kategori supplier harus dipilih dari daftar master kategori.',
        ]);
    }

    private function normalizeWhatsApp(string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $value) ?? '';

        if (str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        if (str_starts_with($digits, '8')) {
            return '62'.$digits;
        }

        if (str_starts_with($digits, '620')) {
            return '62'.substr($digits, 3);
        }

        return $digits;
    }

    private function normalizeSupplierCategories(array $categories): string
    {
        $allowed = SupplierProductCategory::activeOptions();

        return collect($categories)
            ->map(fn ($category) => trim((string) $category))
            ->filter(fn ($category) => $category !== '' && $allowed->contains($category))
            ->unique()
            ->values()
            ->implode(',');
    }

    /**
     * @return array<int, string>
     */
    private function supplierCategoryArray(?string $categories): array
    {
        $allowed = SupplierProductCategory::activeOptions();

        return collect(explode(',', (string) $categories))
            ->map(fn ($category) => trim($category))
            ->filter()
            ->map(fn ($category) => $allowed->first(
                fn ($option) => Str::lower($option) === Str::lower($category)
            ))
            ->filter()
            ->values()
            ->all();
    }

    private function syncManagedSupplierStore(MasterSupplier $supplier, ?SupplierStore $store, string $categories): SupplierStore
    {
        $store ??= $this->storeForSupplier($supplier);

        $payload = [
            'nama' => $supplier->nama,
            'phone' => $supplier->kontak,
            'alamat' => $supplier->alamat,
            'latitude' => $supplier->latitude,
            'longitude' => $supplier->longitude,
            'deskripsi' => $supplier->deskripsi,
            'kategori' => $categories,
            'isDeleted' => false,
            'tokoStatus' => $store?->tokoStatus ?: 'active',
            'TypeToko' => $store?->TypeToko ?: 'umkm',
        ];

        if ($store) {
            $store->fill($payload);
            $store->save();

            return $store;
        }

        return SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => null,
            ...$payload,
        ]);
    }

    private function formatSupplierCard(MasterSupplier $s, ?string $userId = null): array
    {
        $categories = $s->kategori ? explode(',', $s->kategori) : [];
        $slugMap = ['pakan' => 'pakan', 'obat' => 'obat', 'alat' => 'alat', 'vaksin' => 'obat'];
        $distance = $this->distanceService->distanceToSupplier($s, $userId);
        $store = $this->storeForSupplier($s);
        $mapsUrl = $s->latitude !== null && $s->longitude !== null
            ? 'https://www.google.com/maps/search/?api=1&query='.$s->latitude.','.$s->longitude
            : null;

        $productCount = $store
            ? $store->products()->where('isDeleted', false)->where('stok', '>', 0)->count()
            : 0;

        return [
            'id' => $s->id,
            'name' => $s->nama,
            'location' => $s->alamat ?? '-',
            'distance' => $this->distanceService->distanceLabel($distance),
            'distance_value' => $distance,
            'delivery_estimate' => $this->distanceService->deliveryEstimateLabel($distance),
            'has_store' => $store !== null,
            'store_status' => $store?->tokoStatus,
            'store_product_count' => $productCount,
            'maps_url' => $mapsUrl,
            'score' => $this->supplierDisplayScore($s, $userId, $distance, $productCount),
            'reviews' => 0,
            'price_tier' => 'Rp',
            'categories' => array_map('ucfirst', $categories),
            'categories_slug' => array_values(array_unique(array_filter(array_map(
                fn ($c) => $slugMap[trim($c)] ?? trim($c),
                $categories
            )))),
            'description' => $s->deskripsi ?? '',
            'phone' => $s->kontak ?? '',
            'logo' => $s->logo_url ?? 'https://ui-avatars.com/api/?name='.urlencode($s->nama).'&background=0D8ABC&color=fff&rounded=true',
        ];
    }

    private function storeForSupplier(MasterSupplier $supplier): ?SupplierStore
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $supplier->kontak);

        return SupplierStore::query()
            ->where('isDeleted', false)
            ->where(function ($query) use ($supplier, $phone) {
                $query->where('nama', $supplier->nama);

                if ($phone !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', '') = ?", [$phone]);
                }
            })
            ->first();
    }

    private function supplierForStore(SupplierStore $store): ?MasterSupplier
    {
        $phone = preg_replace('/[^0-9]/', '', (string) $store->phone);

        return MasterSupplier::query()
            ->where(function ($query) use ($store, $phone) {
                $query->where('nama', $store->nama);

                if ($phone !== '') {
                    $query->orWhereRaw("REPLACE(REPLACE(REPLACE(kontak, '+', ''), '-', ''), ' ', '') = ?", [$phone]);
                }
            })
            ->first();
    }

    private function formatStoreProductCard(SupplierProduct $product, ?string $userId): array
    {
        $store = $product->store;
        $supplier = $store ? $this->supplierForStore($store) : null;
        $distance = $store
            ? $this->distanceService->distanceToCoordinates($store->latitude, $store->longitude, $userId)
            : null;

        $rankingScore = $supplier && $userId
            ? SpkRanking::query()
                ->where('user_id', $userId)
                ->where('supplier_id', $supplier->id)
                ->where('is_valid', true)
                ->orderByDesc('last_calculated_at')
                ->value('final_score')
            : null;

        $score = $rankingScore !== null
            ? (int) round(min(1, max(0, (float) $rankingScore)) * 100)
            : (int) max(55, round(92 - min($distance ?? 80, 80) * 0.25));

        return [
            'id' => $product->id,
            'name' => $product->nama,
            'description' => $product->deskripsi,
            'category' => $product->kategori ?: SupplierProductCategory::inferForProduct($product->nama.' '.$product->deskripsi),
            'icon' => $this->productIcon($product->nama),
            'image' => $this->imageUrl($product->gambar),
            'price' => $product->harga,
            'stock' => $product->stok,
            'unit' => $product->satuan,
            'store_id' => $store?->id,
            'store_name' => $store?->nama ?? 'Toko supplier',
            'store_location' => $store?->alamat ?? 'Alamat belum tersedia',
            'store_logo' => $this->imageUrl($store?->logoToko),
            'store_categories' => array_filter(array_map('trim', explode(',', (string) $store?->kategori))),
            'supplier_id' => $supplier?->id,
            'score' => $score,
            'distance' => $this->distanceService->distanceLabel($distance),
            'distance_sort' => $distance ?? 9999,
            'delivery' => $this->distanceService->deliveryEstimateLabel($distance),
        ];
    }

    private function supplierDisplayScore(MasterSupplier $supplier, ?string $userId, ?float $distance, int $productCount): int
    {
        $rankingScore = $userId
            ? SpkRanking::query()
                ->where('user_id', $userId)
                ->where('supplier_id', $supplier->id)
                ->where('is_valid', true)
                ->orderByDesc('last_calculated_at')
                ->value('final_score')
            : null;

        if ($rankingScore !== null) {
            return (int) round(min(1, max(0, (float) $rankingScore)) * 100);
        }

        $distanceScore = $distance !== null
            ? max(45, 100 - (min($distance, 80) * 0.7))
            : 70;
        $catalogScore = $productCount > 0
            ? min(100, 65 + min($productCount, 25))
            : 45;

        return (int) round(($distanceScore * 0.55) + ($catalogScore * 0.45));
    }

    private function cartSummary(Request $request): array
    {
        $cart = collect($request->session()->get('supplier_cart', []))
            ->map(fn ($quantity) => (int) $quantity)
            ->filter(fn ($quantity) => $quantity > 0);

        if ($cart->isEmpty()) {
            return [
                'items' => [],
                'total_quantity' => 0,
                'subtotal' => 0,
                'store_count' => 0,
            ];
        }

        $products = SupplierProduct::with('store')
            ->whereIn('id', $cart->keys())
            ->where('isDeleted', false)
            ->get();

        $items = $products->map(function (SupplierProduct $product) use ($cart) {
            $quantity = min((int) ($cart[$product->id] ?? 0), (int) $product->stok);

            return [
                'id' => $product->id,
                'name' => $product->nama,
                'store' => $product->store?->nama ?? 'Toko supplier',
                'quantity' => $quantity,
                'unit' => $product->satuan,
                'price' => $product->harga,
                'subtotal' => $product->harga * $quantity,
            ];
        })->filter(fn (array $item) => $item['quantity'] > 0)->values();

        return [
            'items' => $items->all(),
            'total_quantity' => $items->sum('quantity'),
            'subtotal' => $items->sum('subtotal'),
            'store_count' => $items->pluck('store')->unique()->count(),
        ];
    }

    private function orderSummary(?string $userId): array
    {
        if (! $userId) {
            return [
                'total' => 0,
                'active' => 0,
            ];
        }

        $query = SupplierOrder::query()
            ->where('userId', $userId)
            ->where('isDeleted', false);

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->whereIn('status', ['menunggu', 'diterima'])->count(),
        ];
    }

    private function orderStatusLabels(): array
    {
        return [
            'menunggu' => 'Menunggu',
            'diterima' => 'Diproses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            'dibatalkan' => 'Dibatalkan',
            'expired' => 'Kedaluwarsa',
        ];
    }

    /**
     * @return array<int>
     */
    private function syncSupplierQualityRating(MasterSupplier $supplier, SupplierOrder $order, int $rating): array
    {
        $qualityParameter = SpkParameter::query()
            ->where('nama_parameter', 'like', '%Kualitas%')
            ->first();

        if (! $qualityParameter) {
            return [];
        }

        $updatedProdukIds = [];
        foreach ($order->details as $detail) {
            if (! $detail->product) {
                continue;
            }

            $masterProduk = $this->resolveOrCreateMasterProdukForSupplierProduct($detail->product);
            if (! $masterProduk) {
                continue;
            }

            $supplier->produks()->syncWithoutDetaching([$masterProduk->id]);
            SpkSupplierParameterValue::query()->updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'produk_id' => $masterProduk->id,
                    'parameter_id' => $qualityParameter->id,
                ],
                ['value' => $rating]
            );

            $updatedProdukIds[] = $masterProduk->id;
        }

        return array_values(array_unique($updatedProdukIds));
    }

    private function resolveOrCreateMasterProdukForSupplierProduct(SupplierProduct $product): ?MasterProduk
    {
        $productName = trim((string) $product->nama);
        if ($productName === '') {
            return null;
        }

        $normalized = $this->normalizeProductName($productName);
        $masterProduk = MasterProduk::query()
            ->get()
            ->first(function (MasterProduk $produk) use ($normalized) {
                $masterName = $this->normalizeProductName($produk->nama);

                return $masterName === $normalized
                    || str_contains($masterName, $normalized)
                    || str_contains($normalized, $masterName);
            });

        return $masterProduk ?? MasterProduk::query()->firstOrCreate(
            ['nama' => $productName],
            ['deskripsi' => $product->deskripsi]
        );
    }

    private function normalizeProductName(string $name): string
    {
        $name = strtolower($name);
        $name = preg_replace('/(\d+)\s+(kg|g|mg|ml|l|liter|dosis|butir)\b/', '$1$2', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9]+/', ' ', $name) ?? $name;

        return trim($name);
    }

    private function productIcon(string $nama): string
    {
        $lower = strtolower($nama);
        if (str_contains($lower, 'pakan')) {
            return 'PK';
        }
        if (str_contains($lower, 'vaksin')) {
            return 'VX';
        }
        if (str_contains($lower, 'vitamin')) {
            return 'VT';
        }
        if (str_contains($lower, 'jagung')) {
            return 'JG';
        }
        if (str_contains($lower, 'tray') || str_contains($lower, 'telur')) {
            return 'TR';
        }

        return 'PR';
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
}
