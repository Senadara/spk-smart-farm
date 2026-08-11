<?php

namespace Database\Seeders;

use App\Models\SupplierProduct;
use App\Models\SupplierStore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RealSupplierSeeder extends Seeder
{
    public function run(): void
    {
        // Cari atau buat user supplier untuk dummy
        $user = User::query()->where('role', 'supplier')->first();
        if (!$user) {
            $user = User::query()->create([
                'id' => Str::uuid()->toString(),
                'name' => 'Demo Supplier',
                'email' => 'supplier_demo@example.com',
                'password' => bcrypt('password'),
                'role' => 'supplier',
            ]);
        }

        $suppliers = [
            [
                'nama' => 'PT Agrinusa Jaya',
                'alamat' => 'Malang, Jawa Timur',
                'latitude' => -7.9666204,
                'longitude' => 112.6326321,
                'phone' => '6281234567890',
                'kategori' => 'pakan,obat',
                'deskripsi' => 'Distributor utama pakan ayam petelur berkualitas tinggi.',
                'produks' => [
                    ['nama' => 'Pakan Layer Premium (50kg)', 'kategori' => 'Pakan', 'stok' => 500, 'satuan' => 'Sak', 'harga' => 380000],
                    ['nama' => 'Pakan Layer Ekonomi (50kg)', 'kategori' => 'Pakan', 'stok' => 1000, 'satuan' => 'Sak', 'harga' => 295000],
                    ['nama' => 'Vaksin ND-IB (1000 dosis)', 'kategori' => 'Vaksin', 'stok' => 200, 'satuan' => 'Botol', 'harga' => 125000],
                    ['nama' => 'Vitamin Stress (1kg)', 'kategori' => 'Vitamin', 'stok' => 300, 'satuan' => 'Bungkus', 'harga' => 55000],
                    ['nama' => 'Premix Mineral (25kg)', 'kategori' => 'Pakan', 'stok' => 150, 'satuan' => 'Sak', 'harga' => 320000],
                ]
            ],
            [
                'nama' => 'CV Medion Farma Unggas',
                'alamat' => 'Bandung, Jawa Barat',
                'latitude' => -6.9174639,
                'longitude' => 107.6191228,
                'phone' => '6289876543210',
                'kategori' => 'obat,suplemen',
                'deskripsi' => 'Spesialis obat-obatan dan vaksin ternak skala nasional.',
                'produks' => [
                    ['nama' => 'Vaksin ND-IB (1000 dosis)', 'kategori' => 'Vaksin', 'stok' => 800, 'satuan' => 'Botol', 'harga' => 150000],
                    ['nama' => 'Vitamin Stress (1kg)', 'kategori' => 'Vitamin', 'stok' => 500, 'satuan' => 'Bungkus', 'harga' => 45000],
                    ['nama' => 'Desinfektan Kandang (5L)', 'kategori' => 'Desinfektan', 'stok' => 100, 'satuan' => 'Jerigen', 'harga' => 85000],
                    ['nama' => 'Premix Mineral (25kg)', 'kategori' => 'Pakan', 'stok' => 250, 'satuan' => 'Sak', 'harga' => 345000],
                ]
            ],
            [
                'nama' => 'Makmur Poultry Supply',
                'alamat' => 'Blitar, Jawa Timur',
                'latitude' => -8.0983000,
                'longitude' => 112.1681000,
                'phone' => '628111222333',
                'kategori' => 'alat,perlengkapan',
                'deskripsi' => 'Perlengkapan kandang dan otomasi peternakan.',
                'produks' => [
                    ['nama' => 'Pakan Layer Ekonomi (50kg)', 'kategori' => 'Pakan', 'stok' => 300, 'satuan' => 'Sak', 'harga' => 290000],
                    ['nama' => 'Egg Tray Plastik (30 Butir)', 'kategori' => 'Peralatan', 'stok' => 5000, 'satuan' => 'Pcs', 'harga' => 12000],
                    ['nama' => 'Desinfektan Kandang (5L)', 'kategori' => 'Desinfektan', 'stok' => 150, 'satuan' => 'Jerigen', 'harga' => 78000],
                ]
            ],
            [
                'nama' => 'Jaya Pakan Nusantara',
                'alamat' => 'Surabaya, Jawa Timur',
                'latitude' => -7.2574719,
                'longitude' => 112.7520883,
                'phone' => '628334455667',
                'kategori' => 'pakan',
                'deskripsi' => 'Supplier pakan komersil ekonomis.',
                'produks' => [
                    ['nama' => 'Pakan Layer Premium (50kg)', 'kategori' => 'Pakan', 'stok' => 800, 'satuan' => 'Sak', 'harga' => 355000],
                    ['nama' => 'Pakan Layer Ekonomi (50kg)', 'kategori' => 'Pakan', 'stok' => 1200, 'satuan' => 'Sak', 'harga' => 275000],
                    ['nama' => 'Jagung Giling (50kg)', 'kategori' => 'Pakan', 'stok' => 2000, 'satuan' => 'Sak', 'harga' => 250000],
                    ['nama' => 'Premix Mineral (25kg)', 'kategori' => 'Pakan', 'stok' => 200, 'satuan' => 'Sak', 'harga' => 298000],
                ]
            ],
            [
                'nama' => 'UD Sejahtera Farm',
                'alamat' => 'Kediri, Jawa Timur',
                'latitude' => -7.8167000,
                'longitude' => 112.0120000,
                'phone' => '628556677889',
                'kategori' => 'pakan,suplemen',
                'deskripsi' => 'Toko pakan dan suplemen ternak lokal dengan harga bersaing.',
                'produks' => [
                    ['nama' => 'Pakan Layer Premium (50kg)', 'kategori' => 'Pakan', 'stok' => 400, 'satuan' => 'Sak', 'harga' => 365000],
                    ['nama' => 'Pakan Layer Ekonomi (50kg)', 'kategori' => 'Pakan', 'stok' => 600, 'satuan' => 'Sak', 'harga' => 280000],
                    ['nama' => 'Vitamin Stress (1kg)', 'kategori' => 'Vitamin', 'stok' => 150, 'satuan' => 'Bungkus', 'harga' => 48000],
                    ['nama' => 'Jagung Giling (50kg)', 'kategori' => 'Pakan', 'stok' => 1000, 'satuan' => 'Sak', 'harga' => 245000],
                    ['nama' => 'Egg Tray Plastik (30 Butir)', 'kategori' => 'Peralatan', 'stok' => 2000, 'satuan' => 'Pcs', 'harga' => 13500],
                ]
            ],
            [
                'nama' => 'PT Nutri Ternak Indonesia',
                'alamat' => 'Mojokerto, Jawa Timur',
                'latitude' => -7.4725000,
                'longitude' => 112.4341000,
                'phone' => '628778899001',
                'kategori' => 'pakan,obat,alat',
                'deskripsi' => 'Supplier lengkap pakan, obat, dan peralatan peternakan.',
                'produks' => [
                    ['nama' => 'Pakan Layer Premium (50kg)', 'kategori' => 'Pakan', 'stok' => 600, 'satuan' => 'Sak', 'harga' => 370000],
                    ['nama' => 'Vaksin ND-IB (1000 dosis)', 'kategori' => 'Vaksin', 'stok' => 150, 'satuan' => 'Botol', 'harga' => 140000],
                    ['nama' => 'Jagung Giling (50kg)', 'kategori' => 'Pakan', 'stok' => 1200, 'satuan' => 'Sak', 'harga' => 260000],
                    ['nama' => 'Egg Tray Plastik (30 Butir)', 'kategori' => 'Peralatan', 'stok' => 3000, 'satuan' => 'Pcs', 'harga' => 11500],
                    ['nama' => 'Desinfektan Kandang (5L)', 'kategori' => 'Desinfektan', 'stok' => 200, 'satuan' => 'Jerigen', 'harga' => 82000],
                    ['nama' => 'Premix Mineral (25kg)', 'kategori' => 'Pakan', 'stok' => 300, 'satuan' => 'Sak', 'harga' => 310000],
                ]
            ],
            [
                'nama' => 'Sumber Unggas Mandiri',
                'alamat' => 'Pasuruan, Jawa Timur',
                'latitude' => -7.6454000,
                'longitude' => 112.9075000,
                'phone' => '628990011223',
                'kategori' => 'pakan,perlengkapan',
                'deskripsi' => 'Distributor pakan dan perlengkapan peternakan area Pasuruan-Probolinggo.',
                'produks' => [
                    ['nama' => 'Pakan Layer Premium (50kg)', 'kategori' => 'Pakan', 'stok' => 700, 'satuan' => 'Sak', 'harga' => 340000],
                    ['nama' => 'Pakan Layer Ekonomi (50kg)', 'kategori' => 'Pakan', 'stok' => 900, 'satuan' => 'Sak', 'harga' => 268000],
                    ['nama' => 'Jagung Giling (50kg)', 'kategori' => 'Pakan', 'stok' => 1500, 'satuan' => 'Sak', 'harga' => 235000],
                    ['nama' => 'Egg Tray Plastik (30 Butir)', 'kategori' => 'Peralatan', 'stok' => 4000, 'satuan' => 'Pcs', 'harga' => 12500],
                    ['nama' => 'Desinfektan Kandang (5L)', 'kategori' => 'Desinfektan', 'stok' => 250, 'satuan' => 'Jerigen', 'harga' => 75000],
                ]
            ],
        ];

        foreach ($suppliers as $data) {
            $store = SupplierStore::query()->where('nama', $data['nama'])->first();
            if (!$store) {
                $store = SupplierStore::query()->create([
                    'id' => Str::uuid()->toString(),
                    'userId' => $user->id,
                    'nama' => $data['nama'],
                    'phone' => $data['phone'],
                    'alamat' => $data['alamat'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'deskripsi' => $data['deskripsi'],
                    'kategori' => $data['kategori'],
                    'tokoStatus' => 'active',
                    'isDeleted' => false,
                ]);
            }

            foreach ($data['produks'] as $pData) {
                $product = SupplierProduct::query()->where('tokoId', $store->id)->where('nama', $pData['nama'])->first();
                if (!$product) {
                    SupplierProduct::query()->create([
                        'id' => Str::uuid()->toString(),
                        'tokoId' => $store->id,
                        'nama' => $pData['nama'],
                        'deskripsi' => 'Produk ' . $pData['nama'] . ' dari ' . $data['nama'],
                        'kategori' => $pData['kategori'],
                        'stok' => $pData['stok'],
                        'minimum_stock' => 10,
                        'restock_quantity' => 50,
                        'satuan' => $pData['satuan'],
                        'harga' => $pData['harga'],
                        'isDeleted' => false,
                    ]);
                }
            }
        }

        $this->command?->info('RealSupplierSeeder: toko dan produk supplier real berhasil di-seed.');
    }
}
