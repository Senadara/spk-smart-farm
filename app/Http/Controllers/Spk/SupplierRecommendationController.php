<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SupplierRecommendationController extends Controller
{
    /**
     * Halaman Dashboard Supplier (Katalog)
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $category = $request->input('category', 'all');

        $suppliers = $this->getMockSuppliers();

        // Simple filtering
        if ($category !== 'all') {
            $suppliers = array_filter($suppliers, function($s) use ($category) {
                return in_array($category, $s['categories_slug']);
            });
        }
        if ($search) {
            $suppliers = array_filter($suppliers, function($s) use ($search) {
                $match = stripos($s['name'], $search) !== false || stripos($s['location'], $search) !== false;
                
                // Search descriptions and categories (to match products implicitly)
                if (stripos($s['description'], $search) !== false) $match = true;
                foreach ($s['categories'] as $cat) {
                    if (stripos($cat, $search) !== false) $match = true;
                }
                
                return $match;
            });
        }

        return view('spk.suppliers.index', [
            'suppliers' => $suppliers,
            'category' => $category,
            'search' => $search
        ]);
    }

    /**
     * Halaman Dashboard Komparasi Barang Khusus Supplier
     */
    public function products(Request $request)
    {
        $search = $request->input('search');
        $productId = $request->input('product_id', 'p-1');
        $filterSort = $request->input('sort', 'default'); 
        $filterStock = $request->input('stock', 'all');
        
        $products = $this->getMockProducts();
        
        // Filter products by search
        if ($search) {
            $products = array_filter($products, function($p) use ($search) {
                return stripos($p['name'], $search) !== false || stripos($p['category'], $search) !== false;
            });
            
            // Re-assign active product if current is filtered out
            if (!collect($products)->firstWhere('id', $productId) && count($products) > 0) {
                $first = reset($products);
                $productId = $first['id'];
            }
        }

        $activeProduct = collect($products)->firstWhere('id', $productId);
        
        // Mock Comparison logic (Which supplier sells this?)
        $comparison = [];
        if ($activeProduct) {
            $comparison = $this->getMockComparisonData($activeProduct['id']);

            // Apply Filters & Sorting to Comparison Table
            if ($filterStock === 'instock') {
                $comparison = array_filter($comparison, function($c) {
                    return $c['stock'] > 0;
                });
            }

            if ($filterSort === 'cheapest') {
                usort($comparison, function($a, $b) {
                    return $a['price'] <=> $b['price'];
                });
            } elseif ($filterSort === 'closest') {
                usort($comparison, function($a, $b) {
                    return $a['distance'] <=> $b['distance'];
                });
            }
        }

        return view('spk.suppliers.products', [
            'products' => $products,
            'activeProduct' => $activeProduct,
            'comparison' => $comparison,
            'search' => $search,
            'filterSort' => $filterSort,
            'filterStock' => $filterStock
        ]);
    }

    /**
     * Halaman Detail Supplier
     */
    public function show($id)
    {
        $supplier = collect($this->getMockSuppliers())->firstWhere('id', $id);
        
        if (!$supplier) {
            abort(404);
        }

        $inventories = $this->getMockSupplierInventories($id);

        return view('spk.suppliers.show', [
            'supplier' => $supplier,
            'inventories' => $inventories
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // MOCK DATA SOURCES
    // ═══════════════════════════════════════════════════════════════

    private function getMockSuppliers()
    {
        return [
            [
                'id' => 'S-001',
                'name' => 'PT Agrinusa Jaya',
                'location' => 'Malang, Jawa Timur',
                'distance' => '12 km',
                'score' => 96,
                'rating' => 4.8,
                'reviews' => 124,
                'price_tier' => 'Rp',
                'categories' => ['Pakan', 'Vaksin'],
                'categories_slug' => ['pakan', 'obat'],
                'description' => 'Distributor utama pakan ayam petelur berkualitas tinggi dari produk unggulan Lohmann Brown, lengkap dengan vitamin rutin.',
                'phone' => '+6281234567890',
                'logo' => 'https://ui-avatars.com/api/?name=Agrinusa+Jaya&background=0D8ABC&color=fff&rounded=true'
            ],
            [
                'id' => 'S-002',
                'name' => 'CV Medion Farma Unggas',
                'location' => 'Bandung, Jawa Barat',
                'distance' => '320 km',
                'score' => 92,
                'rating' => 4.9,
                'reviews' => 450,
                'price_tier' => 'RpRp',
                'categories' => ['Obat', 'Vaksin', 'Vitamin'],
                'categories_slug' => ['obat'],
                'description' => 'Spesialis obat-obatan ternak nomor 1 di Indonesia. Menjual vaksin ND, IB, dan Coryza untuk pencegahan kematian skala peternakan.',
                'phone' => '+6289876543210',
                'logo' => 'https://ui-avatars.com/api/?name=Medion&background=E53E3E&color=fff&rounded=true'
            ],
            [
                'id' => 'S-003',
                'name' => 'Makmur Poultry Supply',
                'location' => 'Blitar, Jawa Timur',
                'distance' => '45 km',
                'score' => 88,
                'rating' => 4.5,
                'reviews' => 89,
                'price_tier' => 'RpRpRp',
                'categories' => ['Perlengkapan', 'Otomasi'],
                'categories_slug' => ['alat'],
                'description' => 'Menyediakan rak telur, kipas exhaust, cooling pad, dan peralatan otomatisasi iot untuk kandang sistem closed house.',
                'phone' => '+628111222333',
                'logo' => 'https://ui-avatars.com/api/?name=Makmur&background=F6E05E&color=000&rounded=true'
            ],
            [
                'id' => 'S-004',
                'name' => 'Jaya Pakan Nusantara',
                'location' => 'Surabaya, Jawa Timur',
                'distance' => '80 km',
                'score' => 85,
                'rating' => 4.2,
                'reviews' => 56,
                'price_tier' => 'Rp',
                'categories' => ['Pakan', 'Suplemen'],
                'categories_slug' => ['pakan'],
                'description' => 'Supplier pakan komersil ekonomis dan campuran jagung murni untuk peternak layer fase grower maupun produksi.',
                'phone' => '+628334455667',
                'logo' => 'https://ui-avatars.com/api/?name=Jaya+Pakan&background=48BB78&color=fff&rounded=true'
            ]
        ];
    }

    private function getMockSupplierInventories($supplierId)
    {
        $mocks = [
            'S-001' => [
                ['name' => 'Pakan Layer K-36 (50kg)', 'price' => 380000, 'stock' => 'Tersedia Banyak', 'type' => 'Pakan'],
                ['name' => 'Vaksin ND Clone', 'price' => 125000, 'stock' => 'Terbatas', 'type' => 'Obat'],
                ['name' => 'Amino Egg Plus (1kg)', 'price' => 55000, 'stock' => 'Tersedia', 'type' => 'Suplemen'],
            ],
            'S-002' => [
                ['name' => 'Vaksin ND-IB Live', 'price' => 150000, 'stock' => 'Tersedia Banyak', 'type' => 'Obat'],
                ['name' => 'Vaksin Coryza Inaktif', 'price' => 210000, 'stock' => 'Tersedia Banyak', 'type' => 'Obat'],
                ['name' => 'Vita Stress (1kg)', 'price' => 45000, 'stock' => 'Tersedia Banyak', 'type' => 'Vitamin'],
                ['name' => 'Therapy Obat CRD', 'price' => 60000, 'stock' => 'Sedikit', 'type' => 'Obat'],
            ],
            'S-003' => [
                ['name' => 'Exhaust Fan 50 inch', 'price' => 2500000, 'stock' => 'Pre-order', 'type' => 'Alat'],
                ['name' => 'Cooling Pad Celdek', 'price' => 480000, 'stock' => 'Tersedia', 'type' => 'Alat'],
                ['name' => 'Nipple Drinker Otomatis', 'price' => 8500, 'stock' => 'Tersedia Banyak', 'type' => 'Alat'],
                ['name' => 'Tegg Tray Plastik (Kapasitas 30)', 'price' => 12000, 'stock' => 'Tersedia Banyak', 'type' => 'Alat'],
            ],
            'S-004' => [
                ['name' => 'Pakan Layer K-36 (50kg)', 'price' => 355000, 'stock' => 'Tersedia', 'type' => 'Pakan'],
                ['name' => 'Pakan Grower (50kg)', 'price' => 340000, 'stock' => 'Kosong', 'type' => 'Pakan'],
                ['name' => 'Jagung Pipil Giling (50kg)', 'price' => 250000, 'stock' => 'Tersedia Banyak', 'type' => 'Bahan Baku'],
            ]
        ];

        return $mocks[$supplierId] ?? [];
    }

    private function getMockProducts()
    {
        return [
            ['id' => 'p-1', 'name' => 'Pakan Layer Premium (50kg)', 'category' => 'Pakan Pokok', 'icon' => '🌾'],
            ['id' => 'p-2', 'name' => 'Vaksin ND-IB (1000 dosis)', 'category' => 'Kesehatan', 'icon' => '💉'],
            ['id' => 'p-3', 'name' => 'Vitamin Stress (1kg)', 'category' => 'Suplemen', 'icon' => '🧪'],
            ['id' => 'p-4', 'name' => 'Jagung Giling (50kg)', 'category' => 'Bahan Campuran', 'icon' => '🌽'],
            ['id' => 'p-5', 'name' => 'Egg Tray Plastik (30 Butir)', 'category' => 'Perlengkapan', 'icon' => '🥚'],
        ];
    }

    private function getMockComparisonData($productId)
    {
        $mocks = [
            'p-1' => [
                ['supplierId' => 'S-001', 'supplierName' => 'PT Agrinusa Jaya', 'price' => 380000, 'distance' => 12, 'stock' => 150, 'delivery' => 'Dikirim hari yang sama'],
                ['supplierId' => 'S-004', 'supplierName' => 'Jaya Pakan Nusantara', 'price' => 355000, 'distance' => 80, 'stock' => 50, 'delivery' => 'Estimasi 2 hari'],
            ],
            'p-2' => [
                ['supplierId' => 'S-002', 'supplierName' => 'CV Medion Farma Unggas', 'price' => 150000, 'distance' => 320, 'stock' => 2000, 'delivery' => 'Estimasi 3 hari (Cold Chain)'],
                ['supplierId' => 'S-001', 'supplierName' => 'PT Agrinusa Jaya', 'price' => 125000, 'distance' => 12, 'stock' => 20, 'delivery' => 'Dikirim hari yang sama'],
            ],
            'p-3' => [
                ['supplierId' => 'S-002', 'supplierName' => 'CV Medion Farma Unggas', 'price' => 45000, 'distance' => 320, 'stock' => 500, 'delivery' => 'Estimasi 3 hari'],
                ['supplierId' => 'S-001', 'supplierName' => 'PT Agrinusa Jaya', 'price' => 55000, 'distance' => 12, 'stock' => 100, 'delivery' => 'Dikirim hari yang sama'],
            ],
            'p-4' => [
                ['supplierId' => 'S-004', 'supplierName' => 'Jaya Pakan Nusantara', 'price' => 250000, 'distance' => 80, 'stock' => 400, 'delivery' => 'Estimasi 1-2 hari'],
            ],
            'p-5' => [
                ['supplierId' => 'S-003', 'supplierName' => 'Makmur Poultry Supply', 'price' => 12000, 'distance' => 45, 'stock' => 10000, 'delivery' => 'Estimasi 1 hari'],
            ]
        ];

        return $mocks[$productId] ?? [];
    }
}
