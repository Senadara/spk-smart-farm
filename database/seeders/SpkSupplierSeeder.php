<?php

namespace Database\Seeders;

use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use Illuminate\Database\Seeder;

class SpkSupplierSeeder extends Seeder
{
    public function run(): void
    {
        $parameters = [
            [
                'nama_parameter' => 'Harga',
                'tipe' => 'cost',
                'deskripsi' => 'Harga satuan produk (semakin rendah semakin baik)',
            ],
            [
                'nama_parameter' => 'Kualitas',
                'tipe' => 'benefit',
                'deskripsi' => 'Skor kualitas produk 0-100',
            ],
            [
                'nama_parameter' => 'Kecepatan Pengiriman',
                'tipe' => 'benefit',
                'deskripsi' => 'Kecepatan pengiriman (hari, semakin cepat semakin baik - nilai = 1/estimasi hari)',
            ],
            [
                'nama_parameter' => 'Jarak',
                'tipe' => 'cost',
                'deskripsi' => 'Jarak dari lokasi operasional peternakan owner ke lokasi supplier. Nilai dihitung otomatis per user.',
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
                'kategori' => 'obat',
                'rating' => 4.9,
                'jarak_km' => 320,
                'deskripsi' => 'Spesialis obat-obatan dan vaksin ternak.',
            ],
            [
                'nama' => 'Makmur Poultry Supply',
                'alamat' => 'Blitar, Jawa Timur',
                'latitude' => -8.0983000,
                'longitude' => 112.1681000,
                'kontak' => '+628111222333',
                'kategori' => 'alat',
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
        ];

        $supplierModels = [];
        foreach ($suppliers as $s) {
            $supplierModels[$s['nama']] = MasterSupplier::updateOrCreate(
                ['nama' => $s['nama']],
                $s
            );
        }

        $produks = [
            ['nama' => 'Pakan Layer Premium (50kg)', 'deskripsi' => 'Pakan pokok layer', 'kategori' => 'Pakan Pokok'],
            ['nama' => 'Vaksin ND-IB (1000 dosis)', 'deskripsi' => 'Vaksin kesehatan', 'kategori' => 'Kesehatan'],
            ['nama' => 'Vitamin Stress (1kg)', 'deskripsi' => 'Suplemen', 'kategori' => 'Suplemen'],
            ['nama' => 'Jagung Giling (50kg)', 'deskripsi' => 'Bahan campuran', 'kategori' => 'Bahan Campuran'],
            ['nama' => 'Egg Tray Plastik (30 Butir)', 'deskripsi' => 'Perlengkapan', 'kategori' => 'Perlengkapan'],
        ];

        $produkModels = [];
        foreach ($produks as $p) {
            $produkModels[$p['nama']] = MasterProduk::updateOrCreate(
                ['nama' => $p['nama']],
                ['deskripsi' => $p['deskripsi']]
            );
        }

        // supplier to produk
        $links = [
            'PT Agrinusa Jaya' => ['Pakan Layer Premium (50kg)', 'Vaksin ND-IB (1000 dosis)', 'Vitamin Stress (1kg)'],
            'CV Medion Farma Unggas' => ['Vaksin ND-IB (1000 dosis)', 'Vitamin Stress (1kg)'],
            'Makmur Poultry Supply' => ['Egg Tray Plastik (30 Butir)'],
            'Jaya Pakan Nusantara' => ['Pakan Layer Premium (50kg)', 'Jagung Giling (50kg)'],
        ];

        foreach ($links as $supplierName => $produkNames) {
            $ids = collect($produkNames)->map(fn ($n) => $produkModels[$n]->id)->all();
            $supplierModels[$supplierName]->produks()->syncWithoutDetaching($ids);
        }

        // Nilai parameter: [supplier][produk] => [Harga, Kualitas, Kecepatan score]
        // Kecepatan: skor benefit = 100/estimasi_hari (semakin cepat nilai lebih tinggi)
        $values = [
            'Pakan Layer Premium (50kg)' => [
                'PT Agrinusa Jaya' => [380000, 92, 100 / 1],
                'Jaya Pakan Nusantara' => [355000, 85, 100 / 2],
            ],
            'Vaksin ND-IB (1000 dosis)' => [
                'CV Medion Farma Unggas' => [150000, 95, 100 / 3],
                'PT Agrinusa Jaya' => [125000, 88, 100 / 1],
            ],
            'Vitamin Stress (1kg)' => [
                'CV Medion Farma Unggas' => [45000, 94, 100 / 3],
                'PT Agrinusa Jaya' => [55000, 90, 100 / 1],
            ],
            'Jagung Giling (50kg)' => [
                'Jaya Pakan Nusantara' => [250000, 82, 100 / 2],
            ],
            'Egg Tray Plastik (30 Butir)' => [
                'Makmur Poultry Supply' => [12000, 87, 100 / 1],
            ],
        ];

        foreach ($values as $produkName => $supplierData) {
            $produkId = $produkModels[$produkName]->id;
            foreach ($supplierData as $supplierName => $nums) {
                $supplierId = $supplierModels[$supplierName]->id;
                $map = ['Harga', 'Kualitas', 'Kecepatan Pengiriman'];
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

        $this->command?->info('SpkSupplierSeeder: kriteria, supplier, produk, dan nilai evaluasi berhasil di-seed.');
    }
}
