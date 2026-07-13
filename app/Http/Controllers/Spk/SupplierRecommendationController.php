<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderDetail;
use App\Models\SupplierProduct;
use App\Models\SupplierProductCategory;
use App\Models\SupplierStore;
use App\Services\SupplierDistanceService;
use App\Support\SpkDssActorId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

        $suppliers = $query->orderByDesc('rating')->get()->map(fn ($s) => $this->formatSupplierCard($s, $dssActorId));
        $orderSummary = $this->orderSummary($dssActorId);

        return view('spk.suppliers.index', [
            'suppliers' => $suppliers,
            'category' => $category,
            'search' => $search,
            'orderSummary' => $orderSummary,
        ]);
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
            ->whereHas('store', fn ($query) => $query->where('isDeleted', false));

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

    public function addToCart(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|string|exists:produk,id',
            'quantity' => 'required|integer|min:1|max:100000',
        ]);

        $product = SupplierProduct::query()
            ->where('id', $validated['product_id'])
            ->where('isDeleted', false)
            ->where('stok', '>', 0)
            ->firstOrFail();

        $quantity = min((int) $validated['quantity'], (int) $product->stok);
        $cart = $request->session()->get('supplier_cart', []);
        $cart[$product->id] = min((int) ($cart[$product->id] ?? 0) + $quantity, (int) $product->stok);

        $request->session()->put('supplier_cart', $cart);

        return back()->with('success', 'Barang ditambahkan ke keranjang.');
    }

    public function removeFromCart(Request $request, SupplierProduct $product): RedirectResponse
    {
        $cart = $request->session()->get('supplier_cart', []);
        unset($cart[$product->id]);
        $request->session()->put('supplier_cart', $cart);

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
            ->with(['store', 'details.product'])
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

    private function formatSupplierCard(MasterSupplier $s, ?string $userId = null): array
    {
        $categories = $s->kategori ? explode(',', $s->kategori) : [];
        $slugMap = ['pakan' => 'pakan', 'obat' => 'obat', 'alat' => 'alat', 'vaksin' => 'obat'];
        $distance = $this->distanceService->distanceToSupplier($s, $userId);
        $store = $this->storeForSupplier($s);
        $mapsUrl = $s->latitude !== null && $s->longitude !== null
            ? 'https://www.google.com/maps/search/?api=1&query='.$s->latitude.','.$s->longitude
            : null;

        return [
            'id' => $s->id,
            'name' => $s->nama,
            'location' => $s->alamat ?? '-',
            'distance' => $this->distanceService->distanceLabel($distance),
            'distance_value' => $distance,
            'delivery_estimate' => $this->distanceService->deliveryEstimateLabel($distance),
            'has_store' => $store !== null,
            'store_product_count' => $store
                ? $store->products()->where('isDeleted', false)->where('stok', '>', 0)->count()
                : 0,
            'maps_url' => $mapsUrl,
            'score' => (int) round(($s->rating ?? 0) * 20),
            'rating' => $s->rating ?? 0,
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

        $score = $supplier
            ? (int) round(($supplier->rating ?? 0) * 20)
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
