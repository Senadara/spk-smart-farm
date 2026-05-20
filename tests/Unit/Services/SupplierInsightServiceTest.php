<?php

namespace Tests\Unit\Services;

use App\Services\SupplierInsightService;
use Illuminate\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase;

class SupplierInsightServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        $db = Mockery::mock();
        $db->shouldReceive('raw')->andReturn('COUNT(*) as total');

        $app->instance('db', $db);
        Facade::setFacadeApplication($app);
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Fitur: Insight supplier
     * Skenario: Semua insight utama terbentuk dari riwayat dan data parameter
     * Given riwayat, harga, kualitas, kecepatan, dan ranking tersedia
     * When layanan insight dijalankan
     * Then insight yang relevan dikembalikan secara konsisten
     */
    public function test_menghasilkan_insight_lengkap_dari_riwayat_dan_data_supplier(): void
    {
        $selectionQuery = Mockery::mock();
        $selectionQuery->shouldReceive('when')->with(99, Mockery::type('callable'))->andReturnSelf();
        $selectionQuery->shouldReceive('select')->andReturnSelf();
        $selectionQuery->shouldReceive('groupBy')->with('supplier_id')->andReturnSelf();
        $selectionQuery->shouldReceive('orderByDesc')->with('total')->andReturnSelf();
        $selectionQuery->shouldReceive('first')->andReturn((object) [
            'supplier_id' => 10,
            'total' => 3,
        ]);

        $selectionLog = Mockery::mock('alias:App\Models\SpkSupplierSelectionLog');
        $selectionLog->shouldReceive('where')->with('user_id', 7)->andReturn($selectionQuery);

        $supplierModel = Mockery::mock('alias:App\Models\MasterSupplier');
        $supplierModel->shouldReceive('find')->with(10)->andReturn((object) ['nama' => 'Supplier Alpha']);

        $hargaParamQuery = Mockery::mock();
        $hargaParamQuery->shouldReceive('first')->andReturn((object) ['id' => 21]);

        $qualityParamQuery = Mockery::mock();
        $qualityParamQuery->shouldReceive('first')->andReturn((object) ['id' => 22]);

        $kecepatanParamQuery = Mockery::mock();
        $kecepatanParamQuery->shouldReceive('first')->andReturn((object) ['id' => 23]);

        $parameterModel = Mockery::mock('alias:App\Models\SpkParameter');
        $parameterModel->shouldReceive('where')->with('nama_parameter', 'like', '%Harga%')->andReturn($hargaParamQuery);
        $parameterModel->shouldReceive('where')->with('nama_parameter', 'like', '%Kualitas%')->andReturn($qualityParamQuery);
        $parameterModel->shouldReceive('where')->with('nama_parameter', 'like', '%Kecepatan%')->andReturn($kecepatanParamQuery);

        $hargaValues = new Collection([
            (object) ['value' => 100, 'supplier' => (object) ['nama' => 'Supplier Murah']],
            (object) ['value' => 180, 'supplier' => (object) ['nama' => 'Supplier Mahal']],
        ]);

        $qualityValues = new Collection([
            (object) ['value' => 70, 'supplier' => (object) ['nama' => 'Supplier Rendah']],
            (object) ['value' => 90, 'supplier' => (object) ['nama' => 'Supplier Tinggi']],
        ]);

        $kecepatanValue = (object) ['value' => 4, 'supplier' => (object) ['nama' => 'Supplier Lambat']];

        $hargaQuery = Mockery::mock();
        $hargaQuery->shouldReceive('where')->with('produk_id', 99)->andReturnSelf();
        $hargaQuery->shouldReceive('where')->with('parameter_id', 21)->andReturnSelf();
        $hargaQuery->shouldReceive('with')->with('supplier')->andReturnSelf();
        $hargaQuery->shouldReceive('orderBy')->with('value')->andReturnSelf();
        $hargaQuery->shouldReceive('get')->andReturn($hargaValues);

        $qualityQuery = Mockery::mock();
        $qualityQuery->shouldReceive('where')->with('produk_id', 99)->andReturnSelf();
        $qualityQuery->shouldReceive('where')->with('parameter_id', 22)->andReturnSelf();
        $qualityQuery->shouldReceive('with')->with('supplier')->andReturnSelf();
        $qualityQuery->shouldReceive('get')->andReturn($qualityValues);

        $kecepatanQuery = Mockery::mock();
        $kecepatanQuery->shouldReceive('where')->with('produk_id', 99)->andReturnSelf();
        $kecepatanQuery->shouldReceive('where')->with('parameter_id', 23)->andReturnSelf();
        $kecepatanQuery->shouldReceive('with')->with('supplier')->andReturnSelf();
        $kecepatanQuery->shouldReceive('orderByDesc')->with('value')->andReturnSelf();
        $kecepatanQuery->shouldReceive('first')->andReturn($kecepatanValue);

        $valueModel = Mockery::mock('alias:App\Models\SpkSupplierParameterValue');
        $valueModel->shouldReceive('where')->with('produk_id', 99)->andReturnUsing(function () use ($hargaQuery, $qualityQuery, $kecepatanQuery) {
            static $call = 0;
            $call++;

            return match ($call) {
                1 => $hargaQuery,
                2 => $qualityQuery,
                3 => $kecepatanQuery,
            };
        });

        $rankingQuery = Mockery::mock();
        $rankingQuery->shouldReceive('when')->with(99, Mockery::type('callable'))->andReturnSelf();
        $rankingQuery->shouldReceive('where')->with('produk_id', 99)->andReturnSelf();
        $rankingQuery->shouldReceive('where')->with('is_valid', true)->andReturnSelf();
        $rankingQuery->shouldReceive('orderBy')->with('ranking')->andReturnSelf();
        $rankingQuery->shouldReceive('with')->with('supplier')->andReturnSelf();
        $rankingQuery->shouldReceive('first')->andReturn((object) [
            'supplier' => (object) ['nama' => 'Supplier Unggul'],
            'final_score' => 0.8765,
            'ranking' => 1,
        ]);

        $rankingModel = Mockery::mock('alias:App\Models\SpkRanking');
        $rankingModel->shouldReceive('where')->with('user_id', 7)->andReturn($rankingQuery);

        $service = new SupplierInsightService();
        $insights = $service->generateInsights(7, 99);

        $this->assertCount(5, $insights);
        $this->assertSame('selection_pattern', $insights[0]['type']);
        $this->assertSame('price_gap', $insights[1]['type']);
        $this->assertSame('quality_gap', $insights[2]['type']);
        $this->assertSame('delivery_risk', $insights[3]['type']);
        $this->assertSame('saw_recommendation', $insights[4]['type']);
        $this->assertStringContainsString('Supplier Alpha', $insights[0]['message']);
        $this->assertStringContainsString('44.4%', $insights[1]['message']);
        $this->assertStringContainsString('Supplier Rendah', $insights[2]['message']);
        $this->assertStringContainsString('4 hari', $insights[3]['message']);
        $this->assertStringContainsString('Supplier Unggul', $insights[4]['message']);
    }

    /**
     * Fitur: Insight supplier
     * Skenario: Tidak ada data pendukung
     * Given tidak ada riwayat maupun parameter supplier
     * When layanan insight dijalankan
     * Then hasilnya kosong
     */
    public function test_mengembalikan_koleksi_kosong_jika_tidak_ada_data_pendukung(): void
    {
        $selectionQuery = Mockery::mock();
        $selectionQuery->shouldReceive('when')->with(null, Mockery::type('callable'))->andReturnSelf();
        $selectionQuery->shouldReceive('select')->andReturnSelf();
        $selectionQuery->shouldReceive('groupBy')->with('supplier_id')->andReturnSelf();
        $selectionQuery->shouldReceive('orderByDesc')->with('total')->andReturnSelf();
        $selectionQuery->shouldReceive('first')->andReturn(null);

        $selectionLog = Mockery::mock('alias:App\Models\SpkSupplierSelectionLog');
        $selectionLog->shouldReceive('where')->with('user_id', 7)->andReturn($selectionQuery);

        $parameterModel = Mockery::mock('alias:App\Models\SpkParameter');
        $parameterModel->shouldReceive('where')->with('nama_parameter', 'like', '%Harga%')->andReturnSelf();
        $parameterModel->shouldReceive('where')->with('nama_parameter', 'like', '%Kualitas%')->andReturnSelf();
        $parameterModel->shouldReceive('where')->with('nama_parameter', 'like', '%Kecepatan%')->andReturnSelf();
        $parameterModel->shouldReceive('first')->andReturnNull();

        $rankingQuery = Mockery::mock();
        $rankingQuery->shouldReceive('when')->with(null, Mockery::type('callable'))->andReturnSelf();
        $rankingQuery->shouldReceive('where')->with('is_valid', true)->andReturnSelf();
        $rankingQuery->shouldReceive('orderBy')->with('ranking')->andReturnSelf();
        $rankingQuery->shouldReceive('with')->with('supplier')->andReturnSelf();
        $rankingQuery->shouldReceive('first')->andReturnNull();

        $rankingModel = Mockery::mock('alias:App\Models\SpkRanking');
        $rankingModel->shouldReceive('where')->with('user_id', 7)->andReturn($rankingQuery);

        $service = new SupplierInsightService();
        $insights = $service->generateInsights(7, null);

        $this->assertCount(0, $insights);
    }

    /**
     * Fitur: Logging pemilihan supplier
     * Skenario: Data log tersimpan saat dipanggil
     * Given input pemilihan supplier tersedia
     * When metode logSelection dipanggil
     * Then record baru dibuat
     */
    public function test_mencatat_selection_log_baru(): void
    {
        $selectionLog = Mockery::mock('alias:App\Models\SpkSupplierSelectionLog');
        $selectionLog->shouldReceive('create')->once()->with([
            'user_id' => 7,
            'supplier_id' => 10,
            'produk_id' => 99,
            'final_score' => 0.8123,
            'ranking' => 2,
        ]);

        $service = new SupplierInsightService();
        $service->logSelection(7, 10, 99, 0.8123, 2);

        $this->assertTrue(true);
    }
}
