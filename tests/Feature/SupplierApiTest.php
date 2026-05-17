<?php

namespace Tests\Feature;

use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkParameter;
use App\Models\SpkRanking;
use App\Services\AHPService;
use App\Services\SAWRecommenderService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach (['sessions', 'spk_ahp_perbandingans', 'spk_supplier_parameter_values', 'spk_rankings', 'inventory_supplier_produk', 'spk_parameters', 'master_produks', 'master_suppliers', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('master_suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('alamat')->nullable();
            $table->string('kontak')->nullable();
            $table->timestamps();
        });

        Schema::create('master_produks', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->text('deskripsi')->nullable();
            $table->timestamps();
        });

        Schema::create('inventory_supplier_produk', function (Blueprint $table) {
            $table->foreignId('supplier_id')->constrained('master_suppliers')->onDelete('cascade');
            $table->foreignId('produk_id')->constrained('master_produks')->onDelete('cascade');
            $table->primary(['supplier_id', 'produk_id']);
            $table->timestamps();
        });

        Schema::create('spk_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('nama_parameter');
            $table->enum('tipe', ['benefit', 'cost']);
            $table->timestamps();
        });

        Schema::create('spk_supplier_parameter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('master_suppliers')->onDelete('cascade');
            $table->foreignId('produk_id')->constrained('master_produks')->onDelete('cascade');
            $table->foreignId('parameter_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->float('value');
            $table->timestamps();
        });

        Schema::create('spk_ahp_perbandingans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('parameter_1_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->foreignId('parameter_2_id')->constrained('spk_parameters')->onDelete('cascade');
            $table->float('nilai_skala');
            $table->timestamps();
        });

        Schema::create('spk_rankings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('produk_id')->constrained('master_produks')->onDelete('cascade');
            $table->foreignId('supplier_id')->constrained('master_suppliers')->onDelete('cascade');
            $table->float('final_score')->nullable();
            $table->integer('ranking')->nullable();
            $table->boolean('is_valid')->default(true);
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();
        });

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'QA Tester',
            'email' => 'qa@farm.com',
            'password' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ];
    }

    public function test_daftar_supplier_mengembalikan_json(): void
    {
        MasterSupplier::create(['nama' => 'PT Agrinusa Jaya', 'alamat' => 'Jl. Raya 1', 'kontak' => '08123456789']);

        $response = $this->withSession($this->authSession())->getJson('/supplier-spk/suppliers');

        $response->assertStatus(200);
        $response->assertJsonPath('0.nama', 'PT Agrinusa Jaya');
    }

    public function test_detail_supplier_mengembalikan_json_dengan_relasi_produk(): void
    {
        $supplier = MasterSupplier::create(['nama' => 'PT Agrinusa Jaya', 'alamat' => 'Jl. Raya 1', 'kontak' => '08123456789']);
        $produk = MasterProduk::create(['nama' => 'Pakan Layer', 'deskripsi' => 'Pakan ayam layer']);
        $supplier->produks()->attach($produk->id);

        $response = $this->withSession($this->authSession())->getJson('/supplier-spk/suppliers/' . $supplier->id);

        $response->assertStatus(200);
        $response->assertJsonPath('id', $supplier->id);
        $response->assertJsonPath('produks.0.nama', 'Pakan Layer');
    }

    public function test_hapus_supplier_mengembalikan_status_no_content(): void
    {
        $supplier = MasterSupplier::create(['nama' => 'PT Agrinusa Jaya', 'alamat' => 'Jl. Raya 1', 'kontak' => '08123456789']);

        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->deleteJson('/supplier-spk/suppliers/' . $supplier->id, [
            '_token' => 'csrf-token',
        ]);

        $response->assertStatus(204);
        $this->assertDatabaseMissing('master_suppliers', ['id' => $supplier->id]);
    }

    public function test_daftar_parameter_spk_mengembalikan_json(): void
    {
        SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);

        $response = $this->withSession($this->authSession())->getJson('/supplier-spk/parameters');

        $response->assertStatus(200);
        $response->assertJsonPath('0.nama_parameter', 'Harga');
    }

    public function test_tambah_parameter_spk_mengembalikan_status_created(): void
    {
        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->post('/supplier-spk/parameters', [
            '_token' => 'csrf-token',
            'nama_parameter' => 'Kualitas',
            'tipe' => 'benefit',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('nama_parameter', 'Kualitas');
    }

    public function test_assign_value_parameter_supplier_mengembalikan_json_value(): void
    {
        $supplier = MasterSupplier::create(['nama' => 'PT Agrinusa Jaya', 'alamat' => 'Jl. Raya 1', 'kontak' => '08123456789']);
        $produk = MasterProduk::create(['nama' => 'Pakan Layer', 'deskripsi' => 'Pakan ayam layer']);
        $parameter = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);

        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->post('/supplier-spk/parameters/assign', [
            '_token' => 'csrf-token',
            'supplier_id' => $supplier->id,
            'produk_id' => $produk->id,
            'parameter_id' => $parameter->id,
            'value' => 12.5,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('value', 12.5);
        $this->assertDatabaseHas('spk_supplier_parameter_values', [
            'supplier_id' => $supplier->id,
            'produk_id' => $produk->id,
            'parameter_id' => $parameter->id,
            'value' => 12.5,
        ]);
    }

    public function test_store_perbandingan_ahp_menghasilkan_respon_valid(): void
    {
        $parameter1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $parameter2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);

        $ahpService = Mockery::mock(AHPService::class);
        $ahpService->shouldReceive('calculateAndSaveWeights')->once()->andReturn([
            'cr' => 0.05,
            'is_valid' => true,
            'weights' => [0.6, 0.4],
        ]);
        $this->app->instance(AHPService::class, $ahpService);

        $presenceVerifier = Mockery::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
        $presenceVerifier->shouldReceive('getCount')->andReturn(1);
        $presenceVerifier->shouldReceive('getMultiCount')->andReturn(1);
        $this->app['validator']->setPresenceVerifier($presenceVerifier);

        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->post('/supplier-spk/ahp/perbandingan', [
            '_token' => 'csrf-token',
            'perbandingans' => [
                ['parameter_1_id' => $parameter1->id, 'parameter_2_id' => $parameter2->id, 'nilai_skala' => 3],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'AHP weights calculated successfully');
        $this->assertDatabaseHas('spk_ahp_perbandingans', [
            'user_id' => 1,
            'parameter_1_id' => $parameter1->id,
            'parameter_2_id' => $parameter2->id,
            'nilai_skala' => 3,
        ]);
    }

    public function test_store_perbandingan_ahp_menolak_cr_tidak_valid(): void
    {
        $parameter1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $parameter2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);

        $ahpService = Mockery::mock(AHPService::class);
        $ahpService->shouldReceive('calculateAndSaveWeights')->once()->andReturn([
            'cr' => 0.25,
            'is_valid' => false,
            'weights' => [0.6, 0.4],
        ]);
        $this->app->instance(AHPService::class, $ahpService);

        $presenceVerifier = Mockery::mock(\Illuminate\Validation\PresenceVerifierInterface::class);
        $presenceVerifier->shouldReceive('getCount')->andReturn(1);
        $presenceVerifier->shouldReceive('getMultiCount')->andReturn(1);
        $this->app['validator']->setPresenceVerifier($presenceVerifier);

        $response = $this->withSession(array_merge($this->authSession(), ['_token' => 'csrf-token']))->post('/supplier-spk/ahp/perbandingan', [
            '_token' => 'csrf-token',
            'perbandingans' => [
                ['parameter_1_id' => $parameter1->id, 'parameter_2_id' => $parameter2->id, 'nilai_skala' => 3],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'Weights calculated but Consistency Ratio is invalid (> 0.1)');
    }

    public function test_get_ranking_supplier_mengembalikan_404_saat_tidak_ada_data(): void
    {
        $service = Mockery::mock(SAWRecommenderService::class);
        $service->shouldReceive('getRecommendations')->andReturn(collect());
        $this->app->instance(SAWRecommenderService::class, $service);

        $response = $this->withSession($this->authSession())->getJson('/supplier-spk/recommendation/1');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'No valid rankings available. Please ensure AHP weights are valid and product has suppliers.');
    }

    public function test_get_ranking_supplier_mengembalikan_json_saat_data_ada(): void
    {
        $service = Mockery::mock(SAWRecommenderService::class);

        $supplier = MasterSupplier::create(['nama' => 'PT Agrinusa Jaya', 'alamat' => 'Jl. Raya 1', 'kontak' => '08123456789']);
        $produk = MasterProduk::create(['nama' => 'Pakan Layer', 'deskripsi' => 'Pakan ayam layer']);

        $ranking = new SpkRanking([
            'user_id' => 1,
            'produk_id' => $produk->id,
            'supplier_id' => $supplier->id,
            'final_score' => 0.91,
            'ranking' => 1,
            'is_valid' => true,
        ]);

        $rankings = new class ([$ranking]) extends EloquentCollection {
            public function load($relations)
            {
                return $this;
            }
        };

        $service->shouldReceive('getRecommendations')->andReturn($rankings);
        $this->app->instance(SAWRecommenderService::class, $service);

        $response = $this->withSession($this->authSession())->getJson('/supplier-spk/recommendation/' . $produk->id);

        $response->assertStatus(200);
        $response->assertJsonPath('0.ranking', 1);
    }
}