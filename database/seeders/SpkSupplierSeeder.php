<?php

namespace Database\Seeders;

use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkRanking;
use App\Models\SpkSupplierParameterValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SpkSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $legacyDelivery = SpkParameter::query()->where('nama_parameter', 'Kecepatan Pengiriman')->first();
        if ($legacyDelivery) {
            $legacyDelivery->update(['nama_parameter' => 'Waktu Pengiriman']);
        }

        $parameters = [
            [
                'nama_parameter' => 'Harga',
                'tipe' => 'cost',
                'deskripsi' => 'Harga satuan produk (semakin rendah semakin baik)',
            ],
            [
                'nama_parameter' => 'Kualitas',
                'tipe' => 'benefit',
                'deskripsi' => 'Rating kualitas produk 1-5 dari pembelian selesai. Nilai 3 digunakan sebagai netral jika belum ada rating.',
            ],
            [
                'nama_parameter' => 'Waktu Pengiriman',
                'tipe' => 'cost',
                'deskripsi' => 'Estimasi waktu pengiriman dari seller ke peternakan dalam hari. Nilai 0.5 berarti same day; semakin kecil semakin baik.',
            ],
        ];

        $paramIds = [];
        foreach ($parameters as $p) {
            $param = SpkParameter::updateOrCreate(
                ['nama_parameter' => $p['nama_parameter']],
                ['tipe' => $p['tipe'], 'deskripsi' => $p['deskripsi']]
            );
            $paramIds[$p['nama_parameter']] = $param->id;
        }

        // ──────────────────────────────────────────────────────────────
        // 7 Supplier realistis wilayah Jawa Timur & sekitarnya
        // ──────────────────────────────────────────────────────────────
        $suppliers = [
            [
                'nama' => 'PT Agrinusa Jaya',
                'alamat' => 'Malang, Jawa Timur',
                'latitude' => -7.9666204,
                'longitude' => 112.6326321,
                'kontak' => '+6281234567890',
                'kategori' => 'pakan,obat',
                'rating' => 4.8,
                'jarak_km' => 12,
                'deskripsi' => 'Distributor utama pakan ayam petelur berkualitas tinggi.',
            ],
            [
                'nama' => 'CV Medion Farma Unggas',
                'alamat' => 'Bandung, Jawa Barat',
                'latitude' => -6.9174639,
                'longitude' => 107.6191228,
                'kontak' => '+6289876543210',
                'kategori' => 'obat,suplemen',
                'rating' => 4.9,
                'jarak_km' => 320,
                'deskripsi' => 'Spesialis obat-obatan dan vaksin ternak skala nasional.',
            ],
            [
                'nama' => 'Makmur Poultry Supply',
                'alamat' => 'Blitar, Jawa Timur',
                'latitude' => -8.0983000,
                'longitude' => 112.1681000,
                'kontak' => '+628111222333',
                'kategori' => 'alat,perlengkapan',
                'rating' => 4.5,
                'jarak_km' => 45,
                'deskripsi' => 'Perlengkapan kandang dan otomasi peternakan.',
            ],
            [
                'nama' => 'Jaya Pakan Nusantara',
                'alamat' => 'Surabaya, Jawa Timur',
                'latitude' => -7.2574719,
                'longitude' => 112.7520883,
                'kontak' => '+628334455667',
                'kategori' => 'pakan',
                'rating' => 4.2,
                'jarak_km' => 80,
                'deskripsi' => 'Supplier pakan komersil ekonomis.',
            ],
            [
                'nama' => 'UD Sejahtera Farm',
                'alamat' => 'Kediri, Jawa Timur',
                'latitude' => -7.8167000,
                'longitude' => 112.0120000,
                'kontak' => '+628556677889',
                'kategori' => 'pakan,suplemen',
                'rating' => 4.3,
                'jarak_km' => 55,
                'deskripsi' => 'Toko pakan dan suplemen ternak lokal dengan harga bersaing.',
            ],
            [
                'nama' => 'PT Nutri Ternak Indonesia',
                'alamat' => 'Mojokerto, Jawa Timur',
                'latitude' => -7.4725000,
                'longitude' => 112.4341000,
                'kontak' => '+628778899001',
                'kategori' => 'pakan,obat,alat',
                'rating' => 4.6,
                'jarak_km' => 65,
                'deskripsi' => 'Supplier lengkap pakan, obat, dan peralatan peternakan.',
            ],
            [
                'nama' => 'Sumber Unggas Mandiri',
                'alamat' => 'Pasuruan, Jawa Timur',
                'latitude' => -7.6454000,
                'longitude' => 112.9075000,
                'kontak' => '+628990011223',
                'kategori' => 'pakan,perlengkapan',
                'rating' => 4.1,
                'jarak_km' => 35,
                'deskripsi' => 'Distributor pakan dan perlengkapan peternakan area Pasuruan-Probolinggo.',
            ],
        ];

        $supplierModels = [];
        foreach ($suppliers as $s) {
            $supplierModels[$s['nama']] = MasterSupplier::updateOrCreate(
                ['nama' => $s['nama']],
                $s
            );
        }

        // ──────────────────────────────────────────────────────────────
        // 8 Produk kebutuhan peternakan ayam petelur
        // ──────────────────────────────────────────────────────────────
        $produks = [
            ['nama' => 'Pakan Layer Premium (50kg)', 'deskripsi' => 'Pakan pokok layer fase produksi'],
            ['nama' => 'Pakan Layer Ekonomi (50kg)', 'deskripsi' => 'Pakan layer harga terjangkau'],
            ['nama' => 'Vaksin ND-IB (1000 dosis)', 'deskripsi' => 'Vaksin Newcastle Disease & Infectious Bronchitis'],
            ['nama' => 'Vitamin Stress (1kg)', 'deskripsi' => 'Suplemen multivitamin anti-stress ternak'],
            ['nama' => 'Jagung Giling (50kg)', 'deskripsi' => 'Bahan baku campuran pakan'],
            ['nama' => 'Egg Tray Plastik (30 Butir)', 'deskripsi' => 'Tray plastik reusable untuk packing telur'],
            ['nama' => 'Desinfektan Kandang (5L)', 'deskripsi' => 'Cairan desinfektan untuk sanitasi kandang'],
            ['nama' => 'Premix Mineral (25kg)', 'deskripsi' => 'Campuran mineral dan trace element untuk layer'],
        ];

        $produkModels = [];
        foreach ($produks as $p) {
            $produkModels[$p['nama']] = MasterProduk::updateOrCreate(
                ['nama' => $p['nama']],
                ['deskripsi' => $p['deskripsi']]
            );
        }

        // ──────────────────────────────────────────────────────────────
        // Hubungan supplier ↔ produk
        // Setiap produk ditawarkan oleh minimal 3 supplier agar
        // perbandingan AHP-SAW menghasilkan ranking yang bermakna.
        // ──────────────────────────────────────────────────────────────
        $links = [
            'PT Agrinusa Jaya'        => ['Pakan Layer Premium (50kg)', 'Pakan Layer Ekonomi (50kg)', 'Vaksin ND-IB (1000 dosis)', 'Vitamin Stress (1kg)', 'Premix Mineral (25kg)'],
            'CV Medion Farma Unggas'   => ['Vaksin ND-IB (1000 dosis)', 'Vitamin Stress (1kg)', 'Desinfektan Kandang (5L)', 'Premix Mineral (25kg)'],
            'Makmur Poultry Supply'    => ['Egg Tray Plastik (30 Butir)', 'Desinfektan Kandang (5L)', 'Pakan Layer Ekonomi (50kg)'],
            'Jaya Pakan Nusantara'     => ['Pakan Layer Premium (50kg)', 'Pakan Layer Ekonomi (50kg)', 'Jagung Giling (50kg)', 'Premix Mineral (25kg)'],
            'UD Sejahtera Farm'        => ['Pakan Layer Premium (50kg)', 'Pakan Layer Ekonomi (50kg)', 'Jagung Giling (50kg)', 'Vitamin Stress (1kg)', 'Egg Tray Plastik (30 Butir)'],
            'PT Nutri Ternak Indonesia'=> ['Pakan Layer Premium (50kg)', 'Vaksin ND-IB (1000 dosis)', 'Desinfektan Kandang (5L)', 'Jagung Giling (50kg)', 'Egg Tray Plastik (30 Butir)', 'Premix Mineral (25kg)'],
            'Sumber Unggas Mandiri'    => ['Pakan Layer Premium (50kg)', 'Pakan Layer Ekonomi (50kg)', 'Jagung Giling (50kg)', 'Egg Tray Plastik (30 Butir)', 'Desinfektan Kandang (5L)'],
        ];

        foreach ($links as $supplierName => $produkNames) {
            $ids = collect($produkNames)->map(fn ($n) => $produkModels[$n]->id)->all();
            $supplierModels[$supplierName]->produks()->syncWithoutDetaching($ids);
        }

        // ──────────────────────────────────────────────────────────────
        // Nilai parameter per supplier per produk
        // Format: [Harga (Rp), Kualitas (1-5), Waktu Pengiriman (hari)]
        //   - Harga       → cost  (semakin rendah semakin baik)
        //   - Kualitas     → benefit (semakin tinggi semakin baik)
        //   - Waktu        → cost  (semakin cepat semakin baik, 0.5 = same day)
        // ──────────────────────────────────────────────────────────────
        $values = [
            // --- Pakan Layer Premium: 5 supplier bersaing ---
            'Pakan Layer Premium (50kg)' => [
                'PT Agrinusa Jaya'         => [380000, 4.6, 0.5],
                'Jaya Pakan Nusantara'     => [355000, 4.2, 1],
                'UD Sejahtera Farm'        => [365000, 4.0, 1],
                'PT Nutri Ternak Indonesia'=> [370000, 4.5, 1.5],
                'Sumber Unggas Mandiri'    => [340000, 3.8, 0.5],
            ],

            // --- Pakan Layer Ekonomi: 4 supplier bersaing ---
            'Pakan Layer Ekonomi (50kg)' => [
                'PT Agrinusa Jaya'         => [295000, 4.3, 0.5],
                'Jaya Pakan Nusantara'     => [275000, 4.0, 1],
                'UD Sejahtera Farm'        => [280000, 3.9, 1],
                'Makmur Poultry Supply'    => [290000, 3.7, 1.5],
                'Sumber Unggas Mandiri'    => [268000, 3.6, 0.5],
            ],

            // --- Vaksin ND-IB: 3 supplier bersaing ---
            'Vaksin ND-IB (1000 dosis)' => [
                'CV Medion Farma Unggas'    => [150000, 4.8, 2],
                'PT Agrinusa Jaya'         => [125000, 4.4, 0.5],
                'PT Nutri Ternak Indonesia' => [140000, 4.6, 1.5],
            ],

            // --- Vitamin Stress: 3 supplier bersaing ---
            'Vitamin Stress (1kg)' => [
                'CV Medion Farma Unggas'    => [45000, 4.7, 2],
                'PT Agrinusa Jaya'         => [55000, 4.5, 0.5],
                'UD Sejahtera Farm'        => [48000, 4.2, 1],
            ],

            // --- Jagung Giling: 4 supplier bersaing ---
            'Jagung Giling (50kg)' => [
                'Jaya Pakan Nusantara'      => [250000, 4.1, 1],
                'UD Sejahtera Farm'         => [245000, 4.0, 1],
                'PT Nutri Ternak Indonesia' => [260000, 4.3, 1.5],
                'Sumber Unggas Mandiri'     => [235000, 3.8, 0.5],
            ],

            // --- Egg Tray Plastik: 4 supplier bersaing ---
            'Egg Tray Plastik (30 Butir)' => [
                'Makmur Poultry Supply'     => [12000, 4.3, 1],
                'UD Sejahtera Farm'         => [13500, 4.0, 1],
                'PT Nutri Ternak Indonesia' => [11500, 4.4, 2],
                'Sumber Unggas Mandiri'     => [12500, 3.9, 0.5],
            ],

            // --- Desinfektan Kandang: 4 supplier bersaing ---
            'Desinfektan Kandang (5L)' => [
                'CV Medion Farma Unggas'    => [85000, 4.8, 2],
                'Makmur Poultry Supply'     => [78000, 4.2, 1],
                'PT Nutri Ternak Indonesia' => [82000, 4.5, 1.5],
                'Sumber Unggas Mandiri'     => [75000, 4.0, 0.5],
            ],

            // --- Premix Mineral: 4 supplier bersaing ---
            'Premix Mineral (25kg)' => [
                'PT Agrinusa Jaya'          => [320000, 4.7, 0.5],
                'CV Medion Farma Unggas'    => [345000, 4.9, 2],
                'Jaya Pakan Nusantara'      => [298000, 4.1, 1],
                'PT Nutri Ternak Indonesia' => [310000, 4.4, 1.5],
            ],
        ];

        foreach ($values as $produkName => $supplierData) {
            $produkId = $produkModels[$produkName]->id;
            foreach ($supplierData as $supplierName => $nums) {
                $supplierId = $supplierModels[$supplierName]->id;
                $map = ['Harga', 'Kualitas', 'Waktu Pengiriman'];
                foreach ($map as $i => $paramName) {
                    SpkSupplierParameterValue::updateOrCreate(
                        [
                            'supplier_id' => $supplierId,
                            'produk_id' => $produkId,
                            'parameter_id' => $paramIds[$paramName],
                        ],
                        ['value' => $nums[$i]]
                    );
                }
            }
        }

        $distanceParam = SpkParameter::query()->where('nama_parameter', 'Jarak')->first();
        if ($distanceParam) {
            $distanceParam->delete();
        }

        DB::table('spk_ahp_configurations')->delete();
        SpkRanking::query()->delete();

        $this->command?->info('SpkSupplierSeeder: kriteria, supplier, produk, dan nilai evaluasi berhasil di-seed.');
    }
}
