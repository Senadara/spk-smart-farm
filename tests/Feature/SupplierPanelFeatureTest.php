<?php

namespace Tests\Feature;

use App\Models\SupplierOrder;
use App\Models\SupplierProduct;
use App\Models\SupplierStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupplierPanelFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private User $supplier;

    private SupplierStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = $this->createUser('supplier');
        $this->store = SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $this->supplier->id,
            'nama' => 'Toko Supplier Uji',
            'phone' => '081200000001',
            'alamat' => 'Malang',
            'deskripsi' => 'Toko untuk pengujian panel supplier.',
            'isDeleted' => false,
            'tokoStatus' => 'active',
            'TypeToko' => 'umkm',
        ]);
    }

    public function test_supplier_sees_own_panel_and_is_blocked_from_farm_dashboard(): void
    {
        $this->withSession($this->supplierSession())
            ->get('/supplier')
            ->assertOk()
            ->assertSee('Dashboard Toko')
            ->assertSee('Toko Supplier Uji');

        $this->withSession($this->supplierSession())
            ->get('/supplier/products')
            ->assertOk()
            ->assertSee('Katalog Produk');

        $this->withSession($this->supplierSession())
            ->get('/supplier/orders')
            ->assertOk()
            ->assertSee('Manajemen Pesanan');

        $this->withSession($this->supplierSession())
            ->get('/supplier/finance')
            ->assertOk()
            ->assertSee('Ringkasan Keuangan');

        $this->withSession($this->supplierSession())
            ->get('/supplier/store')
            ->assertOk()
            ->assertSee('supplier-store-location-picker-map')
            ->assertSee('Cari lokasi');

        $this->withSession($this->supplierSession())
            ->get('/dashboard')
            ->assertForbidden();

        $owner = $this->createUser('pjawab');
        $this->withSession($this->sessionFor($owner))
            ->get('/supplier')
            ->assertForbidden();
    }

    public function test_supplier_can_create_product_with_photo_and_cannot_edit_another_store_product(): void
    {
        Storage::fake('public');

        $this->withSession($this->supplierSession())
            ->post('/supplier/products', [
                'nama' => 'Pakan Uji Supplier',
                'deskripsi' => 'Produk dibuat melalui feature test.',
                'kategori' => 'Pakan',
                'stok' => 25,
                'satuan' => 'Karung',
                'harga' => 350000,
                'gambar' => UploadedFile::fake()->createWithContent(
                    'produk.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=')
                ),
            ])
            ->assertRedirect(route('supplier.products.index'));

        $product = SupplierProduct::query()
            ->where('tokoId', $this->store->id)
            ->where('nama', 'Pakan Uji Supplier')
            ->firstOrFail();

        Storage::disk('public')->assertExists($product->gambar);
        $this->assertSame(25, $product->stok);
        $this->assertSame('Pakan', $product->kategori);

        $otherSupplier = $this->createUser('supplier');
        $otherStore = SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $otherSupplier->id,
            'nama' => 'Toko Supplier Lain',
            'phone' => '081200000002',
            'alamat' => 'Blitar',
            'isDeleted' => false,
            'tokoStatus' => 'active',
            'TypeToko' => 'umkm',
        ]);
        $otherProduct = SupplierProduct::query()->create([
            'id' => Str::uuid()->toString(),
            'tokoId' => $otherStore->id,
            'nama' => 'Produk Milik Toko Lain',
            'deskripsi' => 'Tidak boleh diubah supplier pertama.',
            'stok' => 10,
            'satuan' => 'Pcs',
            'harga' => 10000,
            'isDeleted' => false,
        ]);

        $this->withSession($this->supplierSession())
            ->put("/supplier/products/{$otherProduct->id}", [
                'nama' => 'Nama Disusupi',
                'deskripsi' => 'Percobaan mengubah tenant lain.',
                'stok' => 1,
                'satuan' => 'Pcs',
                'harga' => 1,
            ])
            ->assertNotFound();

        $this->assertSame('Produk Milik Toko Lain', $otherProduct->fresh()->nama);
    }

    public function test_supplier_product_category_must_use_master_data(): void
    {
        $this->withSession($this->supplierSession())
            ->get('/supplier/products')
            ->assertOk()
            ->assertSee('Pakan')
            ->assertSee('Vitamin')
            ->assertSee('Kategori berasal dari data master');

        $this->withSession($this->supplierSession())
            ->from('/supplier/products')
            ->post('/supplier/products', [
                'nama' => 'Produk Kategori Bebas',
                'deskripsi' => 'Kategori ini tidak boleh dibuat sembarangan.',
                'kategori' => 'Kategori Buatan Sendiri',
                'stok' => 10,
                'satuan' => 'Pcs',
                'harga' => 10000,
            ])
            ->assertRedirect('/supplier/products')
            ->assertSessionHasErrors('kategori');

        $this->assertDatabaseMissing('produk', [
            'tokoId' => $this->store->id,
            'nama' => 'Produk Kategori Bebas',
        ]);
    }

    public function test_order_status_uses_store_api_and_rejects_cross_store_access(): void
    {
        Http::fake([
            '*' => Http::response([
                'message' => 'Status pesanan berhasil diperbarui',
                'data' => ['status' => 'diterima'],
            ]),
        ]);

        $customer = $this->createUser('user');
        $order = SupplierOrder::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $customer->id,
            'tokoId' => $this->store->id,
            'status' => 'menunggu',
            'totalHarga' => 750000,
            'isDeleted' => false,
        ]);

        $this->withSession($this->supplierSession())
            ->patch("/supplier/orders/{$order->id}/status", ['status' => 'diterima'])
            ->assertRedirect();

        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), '/store/pesanan/status')
            && $request['pesananId'] === $order->id
            && $request['status'] === 'diterima');

        $otherSupplier = $this->createUser('supplier');
        $otherStore = SupplierStore::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $otherSupplier->id,
            'nama' => 'Toko Pesanan Lain',
            'phone' => '081200000003',
            'alamat' => 'Kediri',
            'isDeleted' => false,
            'tokoStatus' => 'active',
            'TypeToko' => 'umkm',
        ]);
        $otherOrder = SupplierOrder::query()->create([
            'id' => Str::uuid()->toString(),
            'userId' => $customer->id,
            'tokoId' => $otherStore->id,
            'status' => 'menunggu',
            'totalHarga' => 100000,
            'isDeleted' => false,
        ]);

        $this->withSession($this->supplierSession())
            ->patch("/supplier/orders/{$otherOrder->id}/status", ['status' => 'diterima'])
            ->assertNotFound();
    }

    private function createUser(string $role): User
    {
        return User::query()->create([
            'id' => Str::uuid()->toString(),
            'name' => ucfirst($role).' Tester',
            'email' => Str::uuid().'@test.local',
            'password' => 'password123',
            'role' => $role,
            'isActive' => true,
            'isDeleted' => false,
        ]);
    }

    private function supplierSession(): array
    {
        return $this->sessionFor($this->supplier);
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
