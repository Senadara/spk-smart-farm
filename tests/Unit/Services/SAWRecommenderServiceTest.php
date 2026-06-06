<?php

namespace Tests\Unit\Services;

use App\Services\NormalizationService;
use App\Services\SAWRecommenderService;
use App\Services\SupplierInsightService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * CATATAN QA:
 * Test ini menggunakan @runTestsInSeparateProcesses untuk menghindari Mockery alias conflict.
 * Mockery alias mock di test pertama akan conflict dengan test kedua jika dijalankan dalam
 * process yang sama.
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SAWRecommenderServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Fitur: Rekomendasi SAW
     * Skenario: Mengembalikan ranking tersimpan tanpa hitung ulang
     * Given ranking valid sudah ada untuk user dan produk
     * When layanan rekomendasi dipanggil tanpa force recalculation
     * Then ranking cache langsung dikembalikan
     */
    public function test_mengembalikan_ranking_cache_jika_sudah_ada(): void
    {
        $ranking = (object) [
            'user_id' => 7,
            'produk_id' => 99,
            'supplier_id' => 5,
            'final_score' => 0.9123,
            'ranking' => 1,
            'is_valid' => true,
        ];

        $cachedRankings = new Collection([$ranking]);

        $query = Mockery::mock();
        $query->shouldReceive('where')->with('produk_id', 99)->andReturnSelf();
        $query->shouldReceive('where')->with('is_valid', true)->andReturnSelf();
        $query->shouldReceive('orderBy')->with('ranking', 'asc')->andReturnSelf();
        $query->shouldReceive('get')->andReturn($cachedRankings);

        $rankingModel = Mockery::mock('alias:App\Models\SpkRanking');
        $rankingModel->shouldReceive('where')->once()->with('user_id', 7)->andReturn($query);

        $service = new SAWRecommenderService(
            new NormalizationService(),
            Mockery::mock(SupplierInsightService::class)
        );

        $result = $service->getRecommendations(7, 99, false);

        $this->assertSame($cachedRankings, $result);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]->ranking);
    }

    /**
     * Fitur: Evaluasi SAW
     * Skenario: Matriks evaluasi dibangun untuk supplier dan parameter yang tersedia
     * Given produk memiliki dua supplier dan dua parameter
     * When matriks evaluasi diminta
     * Then setiap supplier mendapatkan atribut dan nilai normalisasi yang sesuai
     */
    public function test_matriks_evaluasi_menampilkan_atribut_dan_normalisasi_supplier(): void
    {
        $supplierA = (object) ['id' => 1, 'nama' => 'Supplier A'];
        $supplierB = (object) ['id' => 2, 'nama' => 'Supplier B'];

        $product = (object) [
            'id' => 99,
            'suppliers' => new Collection([$supplierA, $supplierB]),
        ];

        $produkQuery = Mockery::mock();
        $produkQuery->shouldReceive('find')->with(99)->andReturn($product);

        $produkModel = Mockery::mock('alias:App\Models\MasterProduk');
        $produkModel->shouldReceive('with')->with('suppliers')->andReturn($produkQuery);

        $parameter1 = (object) ['id' => 10, 'nama_parameter' => 'Harga Pokok', 'tipe' => 'cost'];
        $parameter2 = (object) ['id' => 11, 'nama_parameter' => 'Kualitas Bahan', 'tipe' => 'benefit'];

        $parameterModel = Mockery::mock('alias:App\Models\SpkParameter');
        $parameterModel->shouldReceive('all')->andReturn(new Collection([$parameter1, $parameter2]));

        $values = new EloquentCollection([
            (object) ['supplier_id' => 1, 'parameter_id' => 10, 'value' => 100],
            (object) ['supplier_id' => 1, 'parameter_id' => 11, 'value' => 80],
            (object) ['supplier_id' => 2, 'parameter_id' => 10, 'value' => 200],
            (object) ['supplier_id' => 2, 'parameter_id' => 11, 'value' => 90],
        ]);

        $valueQuery = Mockery::mock();
        $valueQuery->shouldReceive('where')->with('produk_id', 99)->andReturnSelf();
        $valueQuery->shouldReceive('whereIn')
            ->with('supplier_id', Mockery::on(function ($ids) {
                return $ids instanceof Collection && $ids->values()->all() === [1, 2];
            }))
            ->andReturnSelf();
        $valueQuery->shouldReceive('get')->andReturn($values);

        $valueModel = Mockery::mock('alias:App\Models\SpkSupplierParameterValue');
        $valueModel->shouldReceive('where')->with('produk_id', 99)->andReturn($valueQuery);

        $insightService = Mockery::mock(SupplierInsightService::class);

        $service = new SAWRecommenderService(new NormalizationService(), $insightService);
        $matrix = $service->getEvaluationMatrix(99);

        $this->assertCount(2, $matrix);
        $this->assertSame('Supplier A', $matrix[0]['name']);
        $this->assertArrayHasKey('price', $matrix[0]['attributes']);
        $this->assertArrayHasKey('quality', $matrix[0]['attributes']);
        $this->assertEqualsWithDelta(1.0, $matrix[0]['normalized']['price'], 0.0001);
        $this->assertEqualsWithDelta(80 / 90, $matrix[0]['normalized']['quality'], 0.0001);
        $this->assertEqualsWithDelta(100 / 200, $matrix[1]['normalized']['price'], 0.0001);
        $this->assertEqualsWithDelta(1.0, $matrix[1]['normalized']['quality'], 0.0001);
    }
}
