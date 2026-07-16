<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventorySupplierProductLink;
use App\Models\SupplierProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_inventory_dashboard_and_read_endpoints_are_available(): void
    {
        $item = InventoryItem::query()->firstOrFail();
        $session = $this->userSession('inventory-read-token');

        $this->withSession($session)
            ->get('/inventory')
            ->assertOk()
            ->assertSee('Monitoring Stok')
            ->assertSee('Cari Barang Supplier')
            ->assertSee('Stok Menipis')
            ->assertSee('Semua Stok')
            ->assertDontSee('Tambah Inventaris');

        $this->withSession($session)
            ->get('/inventory?restock_view=all')
            ->assertOk()
            ->assertSee('Mode audit menampilkan semua stok');

        $this->withSession($session)
            ->getJson("/inventory/items/{$item->id}")
            ->assertOk()
            ->assertJsonStructure([
                'item' => [
                    'raw_id',
                    'id',
                    'name',
                    'stock',
                    'status',
                ],
                'movements',
            ]);

        $this->withSession($session)
            ->getJson('/inventory/analysis')
            ->assertOk()
            ->assertJsonStructure([
                'total_items',
                'critical',
                'warning',
                'optimal',
                'top_risk',
            ]);

        $this->withSession($session)
            ->postJson('/inventory/purchase-order', [
                '_token' => 'inventory-read-token',
            ])
            ->assertOk()
            ->assertJsonStructure([
                'po_number',
                'generated_at',
                'items',
                'message',
            ]);
    }

    public function test_inventory_write_endpoints_are_not_web_primary_flow(): void
    {
        $session = $this->userSession('inventory-write-token');

        $this->withSession($session)
            ->post('/inventory/items', [
                '_token' => 'inventory-write-token',
                'sku' => 'TEST-INV-UPLOAD',
                'name' => 'Item Uji Upload',
                'category' => 'Pengujian',
                'stock' => 10,
                'unit' => 'Pcs',
                'daily_usage' => 2,
                'minimum_stock' => 3,
                'reorder_point' => 5,
                'lead_time_days' => 2,
            ])
            ->assertForbidden();

        $item = InventoryItem::query()->firstOrFail();

        $this->withSession($session)
            ->post("/inventory/items/{$item->id}/adjust", [
                '_token' => 'inventory-write-token',
                'type' => 'outflow',
                'quantity' => 99,
                'note' => 'Uji stok keluar melebihi saldo.',
            ])
            ->assertForbidden();
    }

    public function test_inventory_item_can_be_linked_to_supplier_product_and_ordered(): void
    {
        $item = InventoryItem::query()->firstOrFail();
        $product = SupplierProduct::query()
            ->where('isDeleted', false)
            ->where('stok', '>', 0)
            ->firstOrFail();
        $session = $this->userSession('inventory-link-token');

        $this->withSession($session)
            ->post(route('inventory.items.supplier-links.store', $item), [
                '_token' => 'inventory-link-token',
                'supplier_product_id' => $product->id,
                'conversion_qty' => 50,
                'conversion_unit' => $item->unit,
            ])
            ->assertRedirect(route('inventory'));

        $this->assertTrue(InventorySupplierProductLink::query()
            ->where('inventory_item_id', $item->id)
            ->where('supplier_product_id', $product->id)
            ->where('is_preferred', true)
            ->exists());

        $this->withSession($session)
            ->post(route('inventory.items.restock-order', $item), [
                '_token' => 'inventory-link-token',
            ])
            ->assertRedirect(route('spk.suppliers.products', ['search' => $product->nama]))
            ->assertSessionHas('supplier_cart');
    }

    public function test_inventory_restock_supplier_recommendation_page_is_available(): void
    {
        $item = InventoryItem::query()->firstOrFail();
        $session = $this->userSession('inventory-recommendation-token');

        $this->withSession($session)
            ->get(route('inventory.items.supplier-recommendations', $item))
            ->assertOk()
            ->assertSee('Cari Supplier Terbaik')
            ->assertSee('Urutan Supplier yang Disarankan')
            ->assertSee('Cara kerja rekomendasi restock');
    }

    public function test_inventory_restock_configuration_can_be_updated(): void
    {
        $item = InventoryItem::query()->firstOrFail();
        $session = $this->userSession('inventory-config-token');

        $this->withSession($session)
            ->patch(route('inventory.items.restock-config', $item), [
                '_token' => 'inventory-config-token',
                'lead_time_days' => 6,
                'safety_stock_days' => 4,
                'reorder_point_override' => 123.5,
            ])
            ->assertRedirect(route('inventory'));

        $item->refresh();

        $this->assertSame(6, $item->lead_time_days);
        $this->assertSame(4, $item->safety_stock_days);
        $this->assertSame(123.5, (float) $item->reorder_point_override);
        $this->assertSame(123.5, (float) $item->reorder_point);
    }

    private function userSession(?string $token = null): array
    {
        $session = [
            'api_token' => 'testing-token',
            'user' => [
                'id' => null,
                'name' => 'Inventory Tester',
                'email' => 'inventory@test.local',
                'role' => 'pjawab',
            ],
        ];

        if ($token) {
            $session['_token'] = $token;
        }

        return $session;
    }
}
