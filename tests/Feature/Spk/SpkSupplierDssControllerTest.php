<?php

namespace Tests\Feature\Spk;

use Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class SpkSupplierDssControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure session is in-memory and disable middleware so tests can exercise controller logic
        $this->app['config']->set('session.driver', 'array');
        $this->app['config']->set('app.debug', true);
        $this->withoutMiddleware();
        $this->withSession(['user' => []]);

        // Ensure minimal master tables exist to let controller load produk list
        if (!Schema::hasTable('master_produks')) {
            Schema::create('master_produks', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('master_suppliers')) {
            Schema::create('master_suppliers', function (Blueprint $table) {
                $table->id();
                $table->string('nama')->nullable();
                $table->text('alamat')->nullable();
                $table->string('kontak')->nullable();
                $table->timestamps();
            });
        }
        if (Schema::hasTable('inventory_supplier_produk')) {
            Schema::dropIfExists('inventory_supplier_produk');
        }
        Schema::create('inventory_supplier_produk', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produk_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Fitur: DSS Supplier - Dashboard dan API ringan
     *
     *   Sebagai pengguna (tanpa mapping DSS)
     *   Ketika saya membuka dashboard DSS
     *   Maka halaman dashboard ditampilkan meskipun tidak ada user_id untuk DSS
     */
    public function test_dashboard_menampilkan_produks_dan_tidak_error_jika_tidak_ada_user()
    {
        $this->withSession(['user' => []]);

        $response = $this->get(route('spk.suppliers.dss.dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('produks');
    }

    public function test_api_rankings_mengembalikan_401_jika_user_tidak_terpeta()
    {
        $this->withSession(['user' => []]);

        $response = $this->getJson(route('spk.suppliers.dss.api.rankings', ['produkId' => 1]));

        $response->assertStatus(401);
    }
}
