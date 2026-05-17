<?php

namespace Tests\Feature;

use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class SpkFuzzyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('session.driver', 'array');
        $this->app['config']->set('cache.default', 'array');
        Cache::store('array')->flush();

        Schema::dropIfExists('spk_fuzzy_logs');

        Schema::create('spk_fuzzy_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('unit_budidaya_id')->nullable();
            $table->json('input_json')->nullable();
            $table->json('fuzzified_json')->nullable();
            $table->json('rule_result_json')->nullable();
            $table->string('status_lingkungan')->nullable();
            $table->string('status_kesehatan')->nullable();
            $table->string('diagnosis_kausalitas')->nullable();
            $table->float('output_value')->nullable();
            $table->string('output_label')->nullable();
            $table->longText('narrative')->nullable();
            $table->longText('recommendation')->nullable();
            $table->timestamp('createdAt')->nullable()->useCurrent();
        });

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
     * Given mocked fuzzy services and an authenticated session
     * When the global process endpoint is called
     * Then it returns a successful response and stores a log record
     */
    public function test_fuzzy_process_returns_successful_response(): void
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
     * Given an existing fuzzy log
     * When the history endpoint is called
     * Then it returns a formatted list item
     */
    public function test_fuzzy_history_returns_successful_response(): void
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
     * Given a missing fuzzy log identifier
     * When the history detail endpoint is called
     * Then it returns a 404 response with a not-found message
     */
    public function test_fuzzy_history_detail_returns_404_when_log_is_missing(): void
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
