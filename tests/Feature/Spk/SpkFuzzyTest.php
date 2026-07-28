<?php

namespace Tests\Feature;

use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SpkFuzzyTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('session.driver', 'array');
        $this->app['config']->set('cache.default', 'array');
        Cache::flush();

        $resolver = Mockery::mock(InputResolver::class);
        $resolver->shouldReceive('resolve')->andReturn([
            'suhu' => 28.5,
            'kelembapan' => 65.0,
            'amonia' => 12.0,
            'hdp' => 94.0,
            'pakan' => 120.0,
            'mortalitas' => 0.2,
        ]);

        $engine = Mockery::mock(MamdaniEngine::class);
        $engine->shouldReceive('processCascaded')->andReturn([
            'inputs' => [
                'suhu' => 28.5,
                'kelembapan' => 65.0,
                'amonia' => 12.0,
                'hdp' => 94.0,
                'pakan' => 120.0,
                'mortalitas' => 0.2,
            ],
            'lingkungan' => [
                'label' => 'Baik',
                'value' => 78.4,
                'fuzzified' => [],
                'dominant_rule' => ['diagnosis' => 'Lingkungan stabil', 'alpha' => 0.8],
            ],
            'kesehatan' => [
                'label' => 'Optimal',
                'value' => 91.2,
                'fuzzified' => [],
                'dominant_rule' => ['diagnosis' => 'Kesehatan bagus', 'alpha' => 0.9],
            ],
            'kausalitas' => [
                'label' => 'Stabil',
                'recommendation' => 'Lanjutkan monitoring',
                'diagnosis' => 'Kondisi stabil',
            ],
        ]);

        $narrator = Mockery::mock(NarrativeGenerator::class);
        $narrator->shouldReceive('generate')->andReturn('Narasi uji');

        $this->app->instance(InputResolver::class, $resolver);
        $this->app->instance(MamdaniEngine::class, $engine);
        $this->app->instance(NarrativeGenerator::class, $narrator);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * Fitur: Proses Fuzzy
     * Skenario: Proses global menyimpan log dan mengembalikan respon sukses
     * Given layanan fuzzy yang dimock dan session terautentikasi
     * When endpoint proses global dipanggil
     * Then respon sukses dikembalikan dan sebuah log disimpan
     */
    public function test_proses_fuzzy_mengembalikan_respon_sukses(): void
    {
        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);

        $response = $this->postJson('/spk-fuzzy/process');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'success',
            'log_id',
            'inputs' => [
                'suhu',
                'kelembapan',
                'amonia',
                'hdp',
                'pakan',
                'mortalitas'
            ],
            'result' => [
                'status_lingkungan',
                'score_lingkungan',
                'status_kesehatan',
                'score_kesehatan',
                'diagnosis_kausalitas',
                'recommendation',
                'narrative',
            ]
        ]);

        $this->assertTrue($response->json('success'));
        $this->assertNotNull($response->json('result.narrative'));
        $this->assertDatabaseCount('spk_fuzzy_logs', 1);
    }

    /**
     * Fitur: Histori Fuzzy
     * Skenario: Mendapatkan daftar histori fuzzy yang terformat
     * Given sebuah log fuzzy yang sudah ada
     * When endpoint histori dipanggil
     * Then daftar histori terformat dikembalikan dengan status sukses
     */
    public function test_histori_fuzzy_mengembalikan_respon_sukses(): void
    {
        SpkFuzzyLog::create([
            'unit_budidaya_id' => null,
            'input_json' => ['suhu' => 28.5],
            'fuzzified_json' => ['lingkungan' => []],
            'rule_result_json' => ['lingkungan' => null],
            'status_lingkungan' => 'Baik',
            'status_kesehatan' => 'Optimal',
            'diagnosis_kausalitas' => 'Stabil',
            'output_value' => 78.4,
            'output_label' => 'Stabil',
            'narrative' => 'Narasi uji',
            'recommendation' => 'Lanjutkan monitoring',
        ]);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);

        $response = $this->getJson('/spk-fuzzy/history');

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'success',
            'total',
            'data' => [
                '*' => [
                    'id',
                    'date',
                    'time',
                    'mode',
                    'status',
                    'verdict',
                    'scores'
                ]
            ]
        ]);

        $this->assertTrue($response->json('success'));
    }

    /**
     * Fitur: Detail Histori Fuzzy
     * Skenario: Mendapatkan 404 ketika log tidak ditemukan
     * Given identifier log yang tidak ada
     * When endpoint detail histori dipanggil
     * Then respon 404 dengan pesan 'Log tidak ditemukan' dikembalikan
     */
    public function test_detail_histori_fuzzy_mengembalikan_404_ketika_log_tidak_ditemukan(): void
    {
        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);

        $response = $this->getJson('/spk-fuzzy/history/missing-log-id');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Log tidak ditemukan',
        ]);
    }
}
