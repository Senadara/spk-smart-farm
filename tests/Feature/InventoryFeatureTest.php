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

        $this->withSession($this->userSession())
            ->get('/inventory')
            ->assertOk()
            ->assertSee('Monitoring Stok')
            ->assertSee('Cari Barang Supplier')
            ->assertDontSee('Tambah Inventaris');

        $this->withSession($this->userSession())
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

        $this->withSession($this->userSession())
            ->getJson('/inventory/analysis')
            ->assertOk()
            ->assertJsonStructure([
                'total_items',
                'critical',
                'warning',
                'optimal',
                'top_risk',
            ]);

        $this->withSession($this->userSession())
            ->postJson('/inventory/purchase-order')
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
        $this->withSession($this->userSession())
            ->post('/inventory/items', [
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

        $this->withSession($this->userSession())
            ->post("/inventory/items/{$item->id}/adjust", [
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

        $this->withSession($this->userSession())
            ->post(route('inventory.items.supplier-links.store', $item), [
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

        $this->withSession($this->userSession())
            ->post(route('inventory.items.restock-order', $item))
            ->assertRedirect(route('spk.suppliers.products', ['search' => $product->nama]))
            ->assertSessionHas('supplier_cart');
    }

    private function userSession(): array
    {
        return [
            'api_token' => 'testing-token',
            'user' => [
                'id' => null,
                'name' => 'Inventory Tester',
                'email' => 'inventory@test.local',
                'role' => 'pjawab',
            ],
        ];
    }
}
