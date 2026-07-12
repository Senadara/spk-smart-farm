<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertSee('Manajemen Inventaris')
            ->assertSee('Tambah Inventaris');

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

    public function test_inventory_item_can_be_created_with_photo_and_adjusted(): void
    {
        Storage::fake('public');

        $response = $this->withSession($this->userSession())
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
                'photo' => UploadedFile::fake()->createWithContent(
                    'item.png',
                    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII=')
                ),
            ]);

        $response->assertRedirect(route('inventory'));

        $item = InventoryItem::where('sku', 'TEST-INV-UPLOAD')->firstOrFail();
        Storage::disk('public')->assertExists($item->photo_path);
        $this->assertSame(10.0, $item->stock);
        $this->assertSame(10.0, $item->movements()->latest()->first()->quantity);

        $this->withSession($this->userSession())
            ->post("/inventory/items/{$item->id}/adjust", [
                'type' => 'outflow',
                'quantity' => 99,
                'note' => 'Uji stok keluar melebihi saldo.',
            ])
            ->assertRedirect(route('inventory'));

        $item->refresh();
        $movement = $item->movements()
            ->where('type', 'outflow')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(0.0, $item->stock);
        $this->assertSame(-10.0, $movement->quantity);
        $this->assertSame(0.0, $movement->stock_after);
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
