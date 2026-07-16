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
        $buyer = $this->createUser('pjawab');
        $supplierUser = $this->createUser('supplier');
        $session = array_merge($this->sessionFor($buyer), ['_token' => 'supplier-order-token']);

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

        $this->withSession($session)
            ->post("/spk-suppliers/{$supplier->id}/orders", [
                '_token' => 'supplier-order-token',
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
        $buyer = $this->createUser('pjawab');
        $supplierUser = $this->createUser('supplier');
        $session = array_merge($this->sessionFor($buyer), ['_token' => 'supplier-cancel-token']);

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

        $this->withSession($session)
            ->get('/spk-suppliers/orders')
            ->assertOk()
            ->assertSee('Toko Histori Uji')
            ->assertSee('Vitamin Histori Uji')
            ->assertSee('Batalkan Pesanan');

        $this->withSession($session)
            ->patch("/spk-suppliers/orders/{$order->id}/cancel", [
                '_token' => 'supplier-cancel-token',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('dibatalkan', $order->fresh()->status);
    }

    public function test_buyer_can_add_supplier_product_to_cart_and_checkout(): void
    {
        $buyer = $this->createUser('pjawab');
        $supplierUser = $this->createUser('supplier');
        $session = array_merge($this->sessionFor($buyer), ['_token' => 'supplier-cart-token']);

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

        $this->withSession($session)
            ->get('/spk-suppliers/products?search=Pakan')
            ->assertOk()
            ->assertSee('Pakan Cart Uji')
            ->assertSee('Toko Cart Uji')
            ->assertSee('Keranjang');

        $this->withSession($session)
            ->post('/spk-suppliers/cart', [
                '_token' => 'supplier-cart-token',
                'product_id' => $product->id,
                'quantity' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas("supplier_cart.{$product->id}", 2);

        $this->withSession(array_merge($session, [
            'supplier_cart' => [$product->id => 2],
        ]))
            ->post('/spk-suppliers/cart/checkout', [
                '_token' => 'supplier-cart-token',
            ])
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

    public function test_superadmin_can_add_manual_supplier_partner_with_location_and_whatsapp(): void
    {
        $admin = $this->createUser('admin');
        $session = array_merge($this->sessionFor($admin), ['_token' => 'manual-supplier-token']);

        $this->withSession($session)
            ->get('/super-admin/suppliers/create')
            ->assertOk()
            ->assertSee('Tambah mitra supplier baru')
            ->assertSee('managed-supplier-location-picker-map');

        $this->withSession($session)
            ->post('/super-admin/suppliers', [
                '_token' => 'manual-supplier-token',
                'nama' => 'Mitra Manual Uji',
                'whatsapp' => '0812-3456-7890',
                'kategori' => ['Pakan', 'Vitamin'],
                'alamat' => 'Ngantang, Kabupaten Malang',
                'latitude' => -7.8543000,
                'longitude' => 112.3701000,
                'deskripsi' => 'Supplier manual untuk uji tambah mitra.',
            ])
            ->assertRedirect(route('superadmin.suppliers.index', ['status' => 'active']));

        $supplier = MasterSupplier::query()
            ->where('nama', 'Mitra Manual Uji')
            ->firstOrFail();

        $this->assertSame('6281234567890', $supplier->kontak);
        $this->assertSame('Pakan,Vitamin', $supplier->kategori);
        $this->assertSame(-7.8543, round((float) $supplier->latitude, 4));
        $this->assertSame(112.3701, round((float) $supplier->longitude, 4));

        $this->assertDatabaseHas('toko', [
            'nama' => 'Mitra Manual Uji',
            'phone' => '6281234567890',
            'alamat' => 'Ngantang, Kabupaten Malang',
            'tokoStatus' => 'active',
            'TypeToko' => 'umkm',
            'isDeleted' => false,
        ]);
    }

    public function test_owner_cannot_access_manual_supplier_management_from_supplier_catalog(): void
    {
        $owner = $this->createUser('pjawab');

        $this->withSession($this->sessionFor($owner))
            ->get('/spk-suppliers')
            ->assertOk()
            ->assertDontSee('Tambah Mitra Supplier')
            ->assertDontSee('Tambah mitra supplier baru');

        $this->withSession($this->sessionFor($owner))
            ->get('/spk-suppliers/create')
            ->assertNotFound();
    }

    public function test_pending_supplier_is_hidden_until_superadmin_approves_it(): void
    {
        $owner = $this->createUser('pjawab');
        $admin = $this->createUser('admin');
        $supplierUser = $this->createUser('supplier');

        $store = SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $supplierUser->id,
            'nama' => 'Toko Pending Uji',
            'phone' => '081240000099',
            'alamat' => 'Malang',
            'kategori' => 'Pakan',
            'isDeleted' => false,
            'tokoStatus' => 'request',
            'TypeToko' => 'umkm',
        ]);

        $product = SupplierProduct::query()->create([
            'id' => Str::uuid()->toString(),
            'tokoId' => $store->id,
            'nama' => 'Pakan Pending Uji',
            'deskripsi' => 'Produk tidak boleh tampil sebelum approval.',
            'kategori' => 'Pakan',
            'stok' => 20,
            'satuan' => 'Karung',
            'harga' => 150000,
            'isDeleted' => false,
        ]);

        $this->withSession($this->sessionFor($owner))
            ->get('/spk-suppliers/products?search=Pending')
            ->assertOk()
            ->assertDontSee('Pakan Pending Uji');

        $this->withSession(array_merge($this->sessionFor($owner), ['_token' => 'pending-cart-token']))
            ->post('/spk-suppliers/cart', [
                '_token' => 'pending-cart-token',
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertNotFound();

        $this->withSession(array_merge($this->sessionFor($admin), ['_token' => 'approve-supplier-token']))
            ->patch("/super-admin/supplier-stores/{$store->id}/approve", [
                '_token' => 'approve-supplier-token',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('toko', [
            'id' => $store->id,
            'tokoStatus' => 'active',
        ]);
        $this->assertDatabaseHas('master_suppliers', [
            'nama' => 'Toko Pending Uji',
            'kontak' => '6281240000099',
        ]);

        $this->withSession($this->sessionFor($owner))
            ->get('/spk-suppliers/products?search=Pending')
            ->assertOk()
            ->assertSee('Pakan Pending Uji')
            ->assertSee('Toko Pending Uji');
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
