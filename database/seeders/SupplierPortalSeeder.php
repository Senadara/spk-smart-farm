<?php

namespace Database\Seeders;

use App\Models\FarmProfile;
use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderDetail;
use App\Models\SupplierProduct;
use App\Models\SupplierStore;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SupplierPortalSeeder extends Seeder
{
    public function run(): void
    {
        $supplierUser = User::query()->updateOrCreate(
            ['email' => 'supplier.demo@smartfarm.test'],
            [
                'id' => User::query()->where('email', 'supplier.demo@smartfarm.test')->value('id')
                    ?? Str::uuid()->toString(),
                'name' => 'Supplier Demo SmartFarm',
                'phone' => '081234567890',
                'password' => Hash::make('password123'),
                'role' => 'supplier',
                'isActive' => true,
                'isDeleted' => false,
            ]
        );

        $customer = User::query()->updateOrCreate(
            ['email' => 'buyer.demo@smartfarm.test'],
            [
                'id' => User::query()->where('email', 'buyer.demo@smartfarm.test')->value('id')
                    ?? Str::uuid()->toString(),
                'name' => 'Pembeli Demo',
                'phone' => '081234567891',
                'password' => Hash::make('password123'),
                'role' => 'user',
                'isActive' => true,
                'isDeleted' => false,
            ]
        );

        $store = SupplierStore::query()->updateOrCreate(
            ['userId' => $supplierUser->id, 'isDeleted' => false],
            [
                'id' => SupplierStore::query()->where('userId', $supplierUser->id)->value('id')
                    ?? Str::uuid()->toString(),
                'nama' => 'CV Sumber Ternak Digital',
                'phone' => '081234567890',
                'alamat' => 'Kabupaten Malang, Jawa Timur',
                'latitude' => -7.9797000,
                'longitude' => 112.6304000,
                'deskripsi' => 'Penyedia pakan, vitamin, dan perlengkapan peternakan ayam petelur.',
                'tokoStatus' => 'active',
                'TypeToko' => 'umkm',
            ]
        );

        FarmProfile::query()->updateOrCreate(
            ['user_id' => $customer->id],
            [
                'farm_name' => 'Demo Farm Layer Malang',
                'address' => 'Pakis, Kabupaten Malang, Jawa Timur',
                'latitude' => -7.9459000,
                'longitude' => 112.7147000,
            ]
        );

        $catalog = [
            [
                'nama' => 'Pakan Layer Premium 50 kg',
                'deskripsi' => 'Pakan lengkap ayam petelur dengan protein seimbang.',
                'kategori' => 'Pakan',
                'stok' => 48,
                'satuan' => 'Karung',
                'harga' => 378000,
            ],
            [
                'nama' => 'Vitamin Ternak 1 kg',
                'deskripsi' => 'Suplemen vitamin untuk menjaga performa dan daya tahan ternak.',
                'kategori' => 'Vitamin',
                'stok' => 8,
                'satuan' => 'Paket',
                'harga' => 52000,
            ],
            [
                'nama' => 'Egg Tray Plastik',
                'deskripsi' => 'Tray telur plastik kapasitas 30 butir yang dapat digunakan kembali.',
                'kategori' => 'Kemasan',
                'stok' => 120,
                'satuan' => 'Pcs',
                'harga' => 12500,
            ],
        ];

        $products = collect($catalog)->map(function (array $item) use ($store) {
            return SupplierProduct::query()->updateOrCreate(
                ['tokoId' => $store->id, 'nama' => $item['nama']],
                [
                    'id' => SupplierProduct::query()
                        ->where('tokoId', $store->id)
                        ->where('nama', $item['nama'])
                        ->value('id') ?? Str::uuid()->toString(),
                    ...$item,
                    'isDeleted' => false,
                ]
            );
        });

        $orders = [
            [
                'id' => '11111111-1111-4111-8111-111111111111',
                'detail_id' => '21111111-1111-4111-8111-111111111111',
                'status' => 'menunggu',
                'product' => 0,
                'quantity' => 2,
            ],
            [
                'id' => '11111111-1111-4111-8111-222222222222',
                'detail_id' => '21111111-1111-4111-8111-222222222222',
                'status' => 'diterima',
                'product' => 1,
                'quantity' => 4,
            ],
            [
                'id' => '11111111-1111-4111-8111-333333333333',
                'detail_id' => '21111111-1111-4111-8111-333333333333',
                'status' => 'selesai',
                'product' => 2,
                'quantity' => 10,
            ],
        ];

        foreach ($orders as $index => $item) {
            $product = $products[$item['product']];
            $order = SupplierOrder::query()->updateOrCreate(
                ['id' => $item['id']],
                [
                    'userId' => $customer->id,
                    'tokoId' => $store->id,
                    'status' => $item['status'],
                    'totalHarga' => $product->harga * $item['quantity'],
                    'isDeleted' => false,
                    'MidtransOrderId' => null,
                ]
            );
            $order->forceFill(['createdAt' => now()->subMonths(2 - $index)])->saveQuietly();

            SupplierOrderDetail::query()->updateOrCreate(
                ['pesananId' => $order->id, 'produkId' => $product->id],
                [
                    'id' => $item['detail_id'],
                    'jumlah' => $item['quantity'],
                    'isDeleted' => false,
                ]
            );
        }

        $this->seedSpkAlternative($store);

        $this->command?->info('SupplierPortalSeeder: akun, toko, produk, pesanan, dan alternatif SPK siap.');
    }

    private function seedSpkAlternative(SupplierStore $store): void
    {
        $supplier = MasterSupplier::query()->updateOrCreate(
            ['nama' => $store->nama],
            [
                'alamat' => $store->alamat,
                'latitude' => $store->latitude,
                'longitude' => $store->longitude,
                'kontak' => $store->phone,
                'deskripsi' => $store->deskripsi,
                'kategori' => 'pakan,vitamin,perlengkapan',
                'rating' => 4.6,
                'jarak_km' => 18,
            ]
        );

        $product = MasterProduk::query()->firstOrCreate(
            ['nama' => 'Pakan Layer Premium (50kg)'],
            ['deskripsi' => 'Pakan pokok layer']
        );
        $supplier->produks()->syncWithoutDetaching([$product->id]);

        $values = [
            'Harga' => 378000,
            'Kualitas' => 90,
            'Kecepatan Pengiriman' => 50,
        ];

        foreach ($values as $parameterName => $value) {
            $parameter = SpkParameter::query()->where('nama_parameter', $parameterName)->first();
            if (! $parameter) {
                continue;
            }

            SpkSupplierParameterValue::query()->updateOrCreate(
                [
                    'supplier_id' => $supplier->id,
                    'produk_id' => $product->id,
                    'parameter_id' => $parameter->id,
                ],
                ['value' => $value]
            );
        }
    }
}
