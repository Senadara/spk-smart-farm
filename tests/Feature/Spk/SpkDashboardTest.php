<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use App\Models\SpkFuzzyLog;
use App\Services\PeternakanService;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use Mockery;
use Tests\TestCase;

class SpkDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('session.driver', 'array');
        $this->withoutMiddleware();
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Fitur: Dashboard SPK
     * Skenario: Menampilkan halaman dashboard SPK dengan data yang ter-prepare
     * Given layanan peternakan dan fuzzy yang dimock serta session terautentikasi
     * When endpoint '/spk-analysis' dipanggil
     * Then respon status 200 dan view berisi data yang diperlukan
     */
    public function test_halaman_dashboard_spk_ditampilkan_dengan_benar()
    {
        $peternakanService = Mockery::mock(PeternakanService::class);
        $peternakanService->shouldReceive('forKomoditas')->with('petelur')->andReturnSelf();
        $peternakanService->shouldReceive('getProduktivitasData')->with(null)->andReturn([
            'spider' => ['labels' => ['HDP', 'Umur Biologis', 'Feed Consumption', 'Mortalitas'], 'values' => [84, 10, 78, 91]],
            'indicators' => [],
            'productivitySensors' => [],
        ]);

        $jenisQuery = Mockery::mock();
        $jenisQuery->shouldReceive('where')->with('nama', 'like', '%Ayam Petelur%')->andReturnSelf();
        $jenisQuery->shouldReceive('where')->with('isDeleted', 0)->andReturnSelf();
        $jenisQuery->shouldReceive('first')->andReturn((object) ['id' => 'jb-001']);

        $unitQuery = Mockery::mock();
        $unitQuery->shouldReceive('where')->with('jenisBudidayaId', 'jb-001')->andReturnSelf();
        $unitQuery->shouldReceive('where')->with('status', 1)->andReturnSelf();
        $unitQuery->shouldReceive('where')->with('isDeleted', 0)->andReturnSelf();
        $unitQuery->shouldReceive('get')->andReturn(collect([(object) ['id' => 'ub-001', 'nama' => 'Kandang A']]));

        DB::shouldReceive('table')->with('jenisBudidaya')->andReturn($jenisQuery);
        DB::shouldReceive('table')->with('unitBudidaya')->andReturn($unitQuery);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);

        $resolver = Mockery::mock(InputResolver::class);
        $resolver->shouldReceive('resolve')->andReturn([
            'suhu' => 28,
            'kelembapan' => 65,
            'amonia' => 10,
            'hdp' => 94,
            'pakan' => 120,
            'mortalitas' => 0.2,
            'fcr' => 1.4,
        ]);
        $engine = Mockery::mock(MamdaniEngine::class);
        $engine->shouldReceive('processCascaded')->andReturn([
            'lingkungan' => ['label' => 'Baik', 'value' => 82.4, 'fuzzified' => [], 'dominant_rule' => ['diagnosis' => 'Lingkungan stabil']],
            'kesehatan' => ['label' => 'Optimal', 'value' => 91.2, 'fuzzified' => [], 'dominant_rule' => ['diagnosis' => 'Kesehatan bagus']],
            'kausalitas' => ['label' => 'Stabil', 'recommendation' => 'Lanjutkan monitoring'],
        ]);
        $narrator = Mockery::mock(NarrativeGenerator::class);
        $narrator->shouldReceive('generate')->andReturn('Narasi uji');
        $this->app->instance(PeternakanService::class, $peternakanService);
        $this->app->instance(InputResolver::class, $resolver);
        $this->app->instance(MamdaniEngine::class, $engine);
        $this->app->instance(NarrativeGenerator::class, $narrator);

        $spkLogBuilder = Mockery::mock();
        $spkLogBuilder->shouldReceive('orderBy')->andReturnSelf();
        $spkLogBuilder->shouldReceive('limit')->andReturnSelf();
        $spkLogBuilder->shouldReceive('get')->andReturn(collect());
        $spkLog = Mockery::mock('alias:' . SpkFuzzyLog::class);
        $spkLog->shouldReceive('query')->andReturn($spkLogBuilder);

        $response = $this->withoutExceptionHandling()->get('/spk-analysis');

        $response->assertStatus(200);
        $response->assertViewHasAll(['komoditas', 'coopId', 'filterOptions', 'kpi', 'fuzzyData', 'chartData', 'recommendedSuppliers', 'actionTickets', 'barnsOption', 'spkHistory', 'activeHistory', 'latestResult']);
    }

    /**
     * Fitur: Dashboard SPK - Riwayat
     * Skenario: Menangani parameter history_id yang tidak valid tanpa crash
     * Given history_id yang tidak valid di query string
     * When endpoint '/spk-analysis?history_id=INVALID_ID' dipanggil
     * Then respon status 200 dan view tetap menyediakan fallback untuk 'activeHistory'
     */
    public function test_dashboard_spk_menangani_id_riwayat_yang_tidak_valid_dengan_error()
    {
        $peternakanService = Mockery::mock(PeternakanService::class);
        $peternakanService->shouldReceive('forKomoditas')->with('petelur')->andReturnSelf();
        $peternakanService->shouldReceive('getProduktivitasData')->with(null)->andReturn([
            'spider' => ['labels' => ['HDP', 'Umur Biologis', 'Feed Consumption', 'Mortalitas'], 'values' => [0, 0, 0, 0]],
            'indicators' => [],
            'productivitySensors' => [],
        ]);

        $jenisQuery = Mockery::mock();
        $jenisQuery->shouldReceive('where')->with('nama', 'like', '%Ayam Petelur%')->andReturnSelf();
        $jenisQuery->shouldReceive('where')->with('isDeleted', 0)->andReturnSelf();
        $jenisQuery->shouldReceive('first')->andReturn((object) ['id' => 'jb-001']);

        $unitQuery = Mockery::mock();
        $unitQuery->shouldReceive('where')->with('jenisBudidayaId', 'jb-001')->andReturnSelf();
        $unitQuery->shouldReceive('where')->with('status', 1)->andReturnSelf();
        $unitQuery->shouldReceive('where')->with('isDeleted', 0)->andReturnSelf();
        $unitQuery->shouldReceive('get')->andReturn(collect([(object) ['id' => 'ub-001', 'nama' => 'Kandang A']]));

        DB::shouldReceive('table')->with('jenisBudidaya')->andReturn($jenisQuery);
        DB::shouldReceive('table')->with('unitBudidaya')->andReturn($unitQuery);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);

        $resolver = Mockery::mock(InputResolver::class);
        $resolver->shouldReceive('resolve')->andReturn([]);
        $engine = Mockery::mock(MamdaniEngine::class);
        $engine->shouldReceive('processCascaded')->andReturn([
            'lingkungan' => ['label' => 'Baik', 'value' => 82.4, 'fuzzified' => [], 'dominant_rule' => ['diagnosis' => 'Lingkungan stabil']],
            'kesehatan' => ['label' => 'Optimal', 'value' => 91.2, 'fuzzified' => [], 'dominant_rule' => ['diagnosis' => 'Kesehatan bagus']],
            'kausalitas' => ['label' => 'Stabil', 'recommendation' => 'Lanjutkan monitoring'],
        ]);
        $narrator = Mockery::mock(NarrativeGenerator::class);
        $narrator->shouldReceive('generate')->andReturn('Narasi uji');
        $this->app->instance(PeternakanService::class, $peternakanService);
        $this->app->instance(InputResolver::class, $resolver);
        $this->app->instance(MamdaniEngine::class, $engine);
        $this->app->instance(NarrativeGenerator::class, $narrator);

        $spkLogBuilder = Mockery::mock();
        $spkLogBuilder->shouldReceive('orderBy')->andReturnSelf();
        $spkLogBuilder->shouldReceive('limit')->andReturnSelf();
        $spkLogBuilder->shouldReceive('get')->andReturn(collect());
        $spkLog = Mockery::mock('alias:' . SpkFuzzyLog::class);
        $spkLog->shouldReceive('query')->andReturn($spkLogBuilder);

        $response = $this->withoutExceptionHandling()->get('/spk-analysis?history_id=INVALID_ID_999');

        $response->assertStatus(200);
        $response->assertViewHas('activeHistory');
    }
}
