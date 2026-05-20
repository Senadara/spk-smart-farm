<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use App\Services\SAWRecommenderService;
use App\Support\SpkDssActorId;
use Illuminate\Http\Request;

class SupplierRecommendationController extends Controller
{
    public function __construct(private SAWRecommenderService $sawService) {}

    public function index(Request $request)
    {
        $search = $request->input('search');
        $category = $request->input('category', 'all');

        $query = MasterSupplier::query();

        if ($category !== 'all') {
            $query->where('kategori', 'like', '%' . $category . '%');
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('alamat', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        $suppliers = $query->orderByDesc('rating')->get()->map(fn ($s) => $this->formatSupplierCard($s));

        return view('spk.suppliers.index', [
            'suppliers' => $suppliers,
            'category' => $category,
            'search' => $search,
        ]);
    }

    public function products(Request $request)
    {
        $search = $request->input('search');
        $productId = (int) $request->input('product_id', 0);
        $filterSort = $request->input('sort', 'saw');
        $filterStock = $request->input('stock', 'all');

        $productsQuery = MasterProduk::withCount('suppliers')->orderBy('nama');
        if ($search) {
            $productsQuery->where('nama', 'like', "%{$search}%");
        }
        $products = $productsQuery->get();

        if ($productId === 0 && $products->isNotEmpty()) {
            $productId = $products->first()->id;
        }

        if ($search && !$products->contains('id', $productId) && $products->isNotEmpty()) {
            $productId = $products->first()->id;
        }

        $activeProduct = $products->firstWhere('id', $productId);
        $comparison = [];
        $sawRankings = collect();
        $ahpReady = false;

        $dssActorId = SpkDssActorId::resolve($request);

        if ($activeProduct) {
            $hargaParam = SpkParameter::where('nama_parameter', 'like', '%Harga%')->first();
            $kecepatanParam = SpkParameter::where('nama_parameter', 'like', '%Kecepatan%')->first();
            $kualitasParam = SpkParameter::where('nama_parameter', 'like', '%Kualitas%')->first();

            $supplierIds = $activeProduct->suppliers()->pluck('master_suppliers.id');
            $paramValues = SpkSupplierParameterValue::where('produk_id', $activeProduct->id)
                ->whereIn('supplier_id', $supplierIds)
                ->with('supplier')
                ->get();

            foreach ($paramValues->groupBy('supplier_id') as $vals) {
                $supplier = $vals->first()->supplier;
                $price = $vals->firstWhere('parameter_id', $hargaParam?->id)?->value ?? 0;
                $quality = $vals->firstWhere('parameter_id', $kualitasParam?->id)?->value ?? 0;
                $speedScore = $vals->firstWhere('parameter_id', $kecepatanParam?->id)?->value ?? 0;
                $days = $speedScore > 0 ? round(100 / $speedScore) : 0;

                $comparison[] = [
                    'supplierId' => $supplier->id,
                    'supplierName' => $supplier->nama,
                    'price' => $price,
                    'quality' => $quality,
                    'distance' => $supplier->jarak_km ?? 0,
                    'stock' => (int) round($quality),
                    'delivery' => $days <= 1 ? 'Dikirim hari yang sama' : "Estimasi {$days} hari",
                ];
            }

            if ($filterStock === 'instock') {
                $comparison = array_values(array_filter($comparison, fn ($c) => $c['stock'] > 0));
            }

            if ($filterSort === 'cheapest') {
                usort($comparison, fn ($a, $b) => $a['price'] <=> $b['price']);
            } elseif ($filterSort === 'closest') {
                usort($comparison, fn ($a, $b) => $a['distance'] <=> $b['distance']);
            }

            if ($dssActorId !== null) {
                $sawRankings = $this->sawService->getRecommendations($dssActorId, $activeProduct->id);
                $sawRankings->load('supplier');
                $ahpReady = $sawRankings->isNotEmpty();

                if ($filterSort === 'saw' && $ahpReady) {
                    $order = $sawRankings->pluck('supplier_id')->flip();
                    usort($comparison, function ($a, $b) use ($order) {
                        return ($order[$a['supplierId']] ?? 99) <=> ($order[$b['supplierId']] ?? 99);
                    });
                }
            }
        }

        $productsForView = $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->nama,
            'category' => $p->deskripsi ?? 'Umum',
            'icon' => $this->productIcon($p->nama),
        ]);

        $activeForView = $activeProduct ? [
            'id' => $activeProduct->id,
            'name' => $activeProduct->nama,
            'category' => $activeProduct->deskripsi ?? '',
            'icon' => $this->productIcon($activeProduct->nama),
        ] : null;

        return view('spk.suppliers.products', [
            'products' => $productsForView,
            'activeProduct' => $activeForView,
            'comparison' => $comparison,
            'sawRankings' => $sawRankings,
            'ahpReady' => $ahpReady,
            'search' => $search,
            'filterSort' => $filterSort,
            'filterStock' => $filterStock,
            'dssActorResolved' => $dssActorId !== null,
        ]);
    }

    public function show($id)
    {
        $supplier = MasterSupplier::with('produks')->findOrFail($id);
        $hargaParam = SpkParameter::where('nama_parameter', 'like', '%Harga%')->first();

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
            'supplier' => $this->formatSupplierCard($supplier),
            'inventories' => $inventories,
        ]);
    }

    private function formatSupplierCard(MasterSupplier $s): array
    {
        $categories = $s->kategori ? explode(',', $s->kategori) : [];
        $slugMap = ['pakan' => 'pakan', 'obat' => 'obat', 'alat' => 'alat', 'vaksin' => 'obat'];

        return [
            'id' => $s->id,
            'name' => $s->nama,
            'location' => $s->alamat ?? '-',
            'distance' => ($s->jarak_km ?? 0) . ' km',
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
            'logo' => $s->logo_url ?? 'https://ui-avatars.com/api/?name=' . urlencode($s->nama) . '&background=0D8ABC&color=fff&rounded=true',
        ];
    }

    private function productIcon(string $nama): string
    {
        $lower = strtolower($nama);
        if (str_contains($lower, 'pakan')) {
            return '🌾';
        }
        if (str_contains($lower, 'vaksin')) {
            return '💉';
        }
        if (str_contains($lower, 'vitamin')) {
            return '🧪';
        }
        if (str_contains($lower, 'jagung')) {
            return '🌽';
        }
        if (str_contains($lower, 'tray') || str_contains($lower, 'telur')) {
            return '🥚';
        }

        return '📦';
    }
}
