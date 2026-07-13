<?php

namespace Tests\Feature;

use App\Models\FarmProfile;
use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkAhpBobot;
use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use App\Models\SupplierOrder;
use App\Models\SupplierOrderDetail;
use App\Models\SupplierProduct;
use App\Models\SupplierStore;
use App\Models\User;
use App\Services\SAWRecommenderService;
use App\Services\SupplierDistanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierRecommendationFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_saw_ranking_uses_farm_location_distance_per_user(): void
    {
        $buyer = $this->createUser('user');
        FarmProfile::query()->create([
            'user_id' => $buyer->id,
            'farm_name' => 'Farm Uji Jarak',
            'address' => 'Pakis, Malang',
            'latitude' => -7.9459000,
            'longitude' => 112.7147000,
        ]);

        $product = MasterProduk::query()->create([
            'nama' => 'Produk Uji Jarak',
            'deskripsi' => 'Produk untuk test SAW jarak dinamis.',
        ]);

        $nearSupplier = MasterSupplier::query()->create([
            'nama' => 'Supplier Dekat Uji',
            'alamat' => 'Malang',
            'latitude' => -7.9500000,
            'longitude' => 112.7200000,
            'kontak' => '081230000001',
            'kategori' => 'pakan',
            'rating' => 4.5,
            'jarak_km' => 99,
        ]);

        $farSupplier = MasterSupplier::query()->create([
            'nama' => 'Supplier Jauh Uji',
            'alamat' => 'Surabaya',
            'latitude' => -7.2574719,
            'longitude' => 112.7520883,
            'kontak' => '081230000002',
            'kategori' => 'pakan',
            'rating' => 4.5,
            'jarak_km' => 1,
        ]);

        $product->suppliers()->sync([$nearSupplier->id, $farSupplier->id]);

        $parameters = $this->ensureSupplierParameters();
        foreach ($parameters as $name => $parameter) {
            SpkAhpBobot::query()->create([
                'user_id' => $buyer->id,
                'parameter_id' => $parameter->id,
                'bobot' => $name === 'Jarak' ? 0.70 : 0.10,
                'is_valid' => true,
            ]);
        }

        foreach ([$nearSupplier, $farSupplier] as $supplier) {
            foreach ([
                'Harga' => 100000,
                'Kualitas' => 90,
                'Kecepatan Pengiriman' => 50,
            ] as $name => $value) {
                SpkSupplierParameterValue::query()->create([
                    'supplier_id' => $supplier->id,
                    'produk_id' => $product->id,
                    'parameter_id' => $parameters[$name]->id,
                    'value' => $value,
                ]);
            }
        }

        $rankings = app(SAWRecommenderService::class)
            ->getRecommendations($buyer->id, $product->id, true);

        $this->assertSame($nearSupplier->id, $rankings->first()->supplier_id);

        $distance = app(SupplierDistanceService::class)->distanceToSupplier($nearSupplier, $buyer->id);
        $this->assertNotNull($distance);
        $this->assertStringStartsWith(
            'Estimasi',
            app(SupplierDistanceService::class)->deliveryEstimateLabel($distance)
        );
    }

    public function test_buyer_can_create_simple_supplier_order_without_payment_gateway(): void
    {
        $buyer = $this->createUser('user');
        $supplierUser = $this->createUser('supplier');

        $store = SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $supplierUser->id,
            'nama' => 'Toko Order Uji',
            'phone' => '081240000001',
            'alamat' => 'Malang',
            'latitude' => -7.9500000,
            'longitude' => 112.7200000,
            'isDeleted' => false,
            'tokoStatus' => 'active',
            'TypeToko' => 'umkm',
        ]);

        $supplier = MasterSupplier::query()->create([
            'nama' => $store->nama,
            'alamat' => $store->alamat,
            'latitude' => $store->latitude,
            'longitude' => $store->longitude,
            'kontak' => $store->phone,
            'kategori' => 'pakan',
            'rating' => 4.5,
        ]);

        $product = SupplierProduct::query()->create([
            'id' => Str::uuid()->toString(),
            'tokoId' => $store->id,
            'nama' => 'Pakan Order Uji',
            'deskripsi' => 'Produk untuk test pesanan sederhana.',
            'stok' => 10,
            'satuan' => 'Karung',
            'harga' => 250000,
            'isDeleted' => false,
        ]);

        $this->withSession($this->sessionFor($buyer))
            ->post("/spk-suppliers/{$supplier->id}/orders", [
                'product_id' => $product->id,
                'quantity' => 3,
            ])
            ->assertRedirect(route('spk.suppliers.orders.index'))
            ->assertSessionHas('success');

        $order = SupplierOrder::query()
            ->where('userId', $buyer->id)
            ->where('tokoId', $store->id)
            ->firstOrFail();

        $this->assertSame('menunggu', $order->status);
        $this->assertSame(750000, $order->totalHarga);
        $this->assertNull($order->MidtransOrderId);

        $this->assertDatabaseHas('pesananDetail', [
            'pesananId' => $order->id,
            'produkId' => $product->id,
            'jumlah' => 3,
        ]);
    }

    public function test_buyer_can_view_order_history_and_cancel_pending_order(): void
    {
        $buyer = $this->createUser('user');
        $supplierUser = $this->createUser('supplier');

        $store = SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $supplierUser->id,
            'nama' => 'Toko Histori Uji',
            'phone' => '081240000002',
            'alamat' => 'Malang',
            'isDeleted' => false,
            'tokoStatus' => 'active',
            'TypeToko' => 'umkm',
        ]);

        $product = SupplierProduct::query()->create([
            'id' => Str::uuid()->toString(),
            'tokoId' => $store->id,
            'nama' => 'Vitamin Histori Uji',
            'deskripsi' => 'Produk untuk test histori pesanan.',
            'stok' => 15,
            'satuan' => 'Paket',
            'harga' => 50000,
            'isDeleted' => false,
        ]);

        $order = SupplierOrder::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $buyer->id,
            'tokoId' => $store->id,
            'status' => 'menunggu',
            'totalHarga' => 100000,
            'isDeleted' => false,
        ]);

        SupplierOrderDetail::query()->create([
            'id' => Str::uuid()->toString(),
            'pesananId' => $order->id,
            'produkId' => $product->id,
            'jumlah' => 2,
            'isDeleted' => false,
        ]);

        $this->withSession($this->sessionFor($buyer))
            ->get('/spk-suppliers/orders')
            ->assertOk()
            ->assertSee('Toko Histori Uji')
            ->assertSee('Vitamin Histori Uji')
            ->assertSee('Batalkan Pesanan');

        $this->withSession($this->sessionFor($buyer))
            ->patch("/spk-suppliers/orders/{$order->id}/cancel")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('dibatalkan', $order->fresh()->status);
    }

    public function test_buyer_can_add_supplier_product_to_cart_and_checkout(): void
    {
        $buyer = $this->createUser('user');
        $supplierUser = $this->createUser('supplier');

        $store = SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $supplierUser->id,
            'nama' => 'Toko Cart Uji',
            'phone' => '081240000003',
            'alamat' => 'Malang',
            'kategori' => 'Pakan',
            'isDeleted' => false,
            'tokoStatus' => 'active',
            'TypeToko' => 'umkm',
        ]);

        $product = SupplierProduct::query()->create([
            'id' => Str::uuid()->toString(),
            'tokoId' => $store->id,
            'nama' => 'Pakan Cart Uji',
            'deskripsi' => 'Produk untuk test keranjang.',
            'kategori' => 'Pakan',
            'stok' => 20,
            'satuan' => 'Karung',
            'harga' => 100000,
            'isDeleted' => false,
        ]);

        $this->withSession($this->sessionFor($buyer))
            ->get('/spk-suppliers/products?search=Pakan')
            ->assertOk()
            ->assertSee('Pakan Cart Uji')
            ->assertSee('Toko Cart Uji')
            ->assertSee('Keranjang');

        $this->withSession($this->sessionFor($buyer))
            ->post('/spk-suppliers/cart', [
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas("supplier_cart.{$product->id}", 2);

        $this->withSession(array_merge($this->sessionFor($buyer), [
            'supplier_cart' => [$product->id => 2],
        ]))
            ->post('/spk-suppliers/cart/checkout')
            ->assertRedirect(route('spk.suppliers.orders.index'))
            ->assertSessionHas('success');

        $order = SupplierOrder::query()
            ->where('userId', $buyer->id)
            ->where('tokoId', $store->id)
            ->firstOrFail();

        $this->assertSame(200000, $order->totalHarga);
        $this->assertDatabaseHas('pesananDetail', [
            'pesananId' => $order->id,
            'produkId' => $product->id,
            'jumlah' => 2,
        ]);
    }

    public function test_owner_profile_renders_farm_location_picker(): void
    {
        $buyer = $this->createUser('user');

        $this->withSession($this->sessionFor($buyer))
            ->get('/profil')
            ->assertOk()
            ->assertSee('farm-location-picker-map')
            ->assertSee('Cari lokasi');
    }

    /**
     * @return array<string, SpkParameter>
     */
    private function ensureSupplierParameters(): array
    {
        return [
            'Harga' => SpkParameter::query()->updateOrCreate(
                ['nama_parameter' => 'Harga'],
                ['tipe' => 'cost', 'deskripsi' => 'Harga satuan']
            ),
            'Kualitas' => SpkParameter::query()->updateOrCreate(
                ['nama_parameter' => 'Kualitas'],
                ['tipe' => 'benefit', 'deskripsi' => 'Skor kualitas']
            ),
            'Kecepatan Pengiriman' => SpkParameter::query()->updateOrCreate(
                ['nama_parameter' => 'Kecepatan Pengiriman'],
                ['tipe' => 'benefit', 'deskripsi' => 'Skor pengiriman']
            ),
            'Jarak' => SpkParameter::query()->updateOrCreate(
                ['nama_parameter' => 'Jarak'],
                ['tipe' => 'cost', 'deskripsi' => 'Jarak dinamis per user']
            ),
        ];
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'id' => Str::uuid()->toString(),
            'name' => ucfirst($role).' Recommendation Tester',
            'email' => Str::uuid().'@test.local',
            'password' => 'password123',
            'role' => $role,
            'isActive' => true,
            'isDeleted' => false,
        ]);
    }

    private function sessionFor(User $user): array
    {
        return [
            'api_token' => 'testing-token',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ];
    }
}
