<?php

namespace Tests\Unit\Fuzzy;

use App\Services\Fuzzy\MamdaniEngine;
use Illuminate\Support\Facades\Cache;
use Mockery;
use PHPUnit\Framework\TestCase;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;

class MamdaniEngineTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        if (Facade::getFacadeApplication()) {
            Cache::forget('fuzzy_rules_kausalitas');
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication(null);
        }

        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container();
        $store = new ArrayStore();
        $repo = new CacheRepository($store);
        $app->instance('cache', $repo);
        Facade::setFacadeApplication($app);
    }

    public function test_lookup_kausalitas_menemukan_rule_yang_cocok(): void
    {
        $rules = [
            [
                'id' => 1,
                'name' => 'Rule Optimal-Waspada',
                'operator' => 'AND',
                'output_set_id' => 100,
                'output_set_name' => 'Perhatian Tinggi',
                'diagnosis' => 'Ada masalah pada kombinasi',
                'recommendation' => 'Periksa kualitas pakan',
                'conditions' => [
                    ['variable_name' => 'label_lingkungan', 'set_name' => 'Optimal', 'set_id' => null],
                    ['variable_name' => 'label_kesehatan', 'set_name' => 'Waspada', 'set_id' => null],
                ],
            ],
        ];

        Cache::put('fuzzy_rules_kausalitas', $rules, 3600);

        $engine = new MamdaniEngine();
        $res = $engine->lookupKausalitas('Optimal', 'Waspada');

        $this->assertIsArray($res);
        $this->assertSame('Perhatian Tinggi', $res['label']);
        $this->assertStringContainsString('Periksa kualitas', $res['recommendation']);
        $this->assertSame('Rule Optimal-Waspada', $res['matched_rule']);
    }

    public function test_lookup_kausalitas_fallback_jika_tidak_cocok(): void
    {
        Cache::put('fuzzy_rules_kausalitas', [], 3600);

        $engine = new MamdaniEngine();
        $res = $engine->lookupKausalitas('NonExistent', 'AlsoMissing');

        $this->assertIsArray($res);
        $this->assertSame('Tidak Diketahui', $res['label']);
        $this->assertStringContainsString('Kombinasi kondisi belum terdefinisi', $res['diagnosis']);
    }

    public function test_kalo_fuzzify_defuzzify_beres_dan_ngasih_label(): void
    {
        // Given: konfigurasi variable dan rule di-cache
        // When: proses group 'lingkungan' dijalankan
        // Then: harus ngembaliin label yang sesuai dan nilai crisp > 0

        $setFactory = function ($id, $name, $fn) {
            return new class ($id, $name, $fn) {
                public $id;
                public $name;
                private $fn;
                public function __construct($id, $name, $fn)
                {
                    $this->id = $id;
                    $this->name = $name;
                    $this->fn = $fn;
                }
                public function membership($x)
                {
                    $f = $this->fn;
                    return $f($x);
                }
            };
        };

        $inputVar = (object) [
            'name' => 'suhu',
            'sets' => collect([
                $setFactory(null, 'Panas', fn($x) => 1.0),
            ]),
        ];

        $outputSet = $setFactory(10, 'Buruk', fn($x) => 1.0);
        $outputVar = (object) ['sets' => collect([$outputSet])];

        Cache::put('fuzzy_vars_lingkungan', [
            'input' => collect([$inputVar]),
            'output' => collect([$outputVar]),
        ]);

        $rule = [
            'id' => 1,
            'name' => 'rule_panas',
            'operator' => 'AND',
            'output_set_id' => 10,
            'output_set_name' => 'Buruk',
            'conditions' => [['variable_name' => 'suhu', 'set_name' => 'Panas', 'set_id' => null]],
        ];

        Cache::put('fuzzy_rules_lingkungan', [$rule]);

        $engine = new MamdaniEngine();
        $res = $engine->processGroup('lingkungan', ['suhu' => 30.0]);

        $this->assertIsArray($res);
        $this->assertArrayHasKey('label', $res);
        $this->assertEquals('Buruk', $res['label']);
        $this->assertGreaterThan(0, $res['value']);
    }

    public function test_kalo_rule_or_maka_pakai_max_dan_label_tepat(): void
    {
        // Given: dua variable input dengan membership berbeda
        // When: rule operator OR dievaluasi
        // Then: alpha yang dipakai = max(alphas) dan label sesuai output set

        $setFactory = function ($id, $name, $fn) {
            return new class ($id, $name, $fn) {
                public $id;
                public $name;
                private $fn;
                public function __construct($id, $name, $fn)
                {
                    $this->id = $id;
                    $this->name = $name;
                    $this->fn = $fn;
                }
                public function membership($x)
                {
                    $f = $this->fn;
                    return $f($x);
                }
            };
        };

        $inputVarA = (object) ['name' => 'varA', 'sets' => collect([$setFactory(null, 'A1', fn($x) => 0.2)])];
        $inputVarB = (object) ['name' => 'varB', 'sets' => collect([$setFactory(null, 'B1', fn($x) => 0.8)])];

        $outputSet = $setFactory(99, 'Result', fn($x) => 1.0);
        $outputVar = (object) ['sets' => collect([$outputSet])];

        Cache::put('fuzzy_vars_kesehatan', ['input' => collect([$inputVarA, $inputVarB]), 'output' => collect([$outputVar])]);

        $rule = ['id' => 10, 'name' => 'r_or', 'operator' => 'OR', 'output_set_id' => 99, 'output_set_name' => 'Result', 'conditions' => [['variable_name' => 'varA', 'set_name' => 'A1', 'set_id' => null], ['variable_name' => 'varB', 'set_name' => 'B1', 'set_id' => null],],];

        Cache::put('fuzzy_rules_kesehatan', [$rule]);

        $engine = new MamdaniEngine();
        $res = $engine->processGroup('kesehatan', ['varA' => 1.0, 'varB' => 1.0]);

        $this->assertIsArray($res);
        $this->assertArrayHasKey('dominant_rule', $res);
        $this->assertEqualsWithDelta(0.8, $res['dominant_rule']['alpha'], 0.0001);
        $this->assertEquals('Result', $res['label']);
    }

    public function test_defuzzify_flat_membership_balik_midpoint(): void
    {
        // Given: output set flat (membership=1) di seluruh universe
        // When: defuzzifikasi dilakukan (centroid)
        // Then: nilai crisp harus mendekati midpoint (50.0) dan label sesuai

        $setFactory = function ($id, $name, $fn) {
            return new class ($id, $name, $fn) {
                public $id;
                public $name;
                private $fn;
                public function __construct($id, $name, $fn)
                {
                    $this->id = $id;
                    $this->name = $name;
                    $this->fn = $fn;
                }
                public function membership($x)
                {
                    $f = $this->fn;
                    return $f($x);
                }
            };
        };

        $inputVar = (object) ['name' => 'dummy', 'sets' => collect([$setFactory(null, 'D', fn($x) => 0.6)])];
        $outputSet = $setFactory(7, 'Mid', fn($x) => 1.0);
        $outputVar = (object) ['sets' => collect([$outputSet])];

        Cache::put('fuzzy_vars_lingkungan', ['input' => collect([$inputVar]), 'output' => collect([$outputVar])]);

        $rule = ['id' => 7, 'name' => 'r_mid', 'operator' => 'AND', 'output_set_id' => 7, 'output_set_name' => 'Mid', 'conditions' => [['variable_name' => 'dummy', 'set_name' => 'D', 'set_id' => null]],];

        Cache::put('fuzzy_rules_lingkungan', [$rule]);

        $engine = new MamdaniEngine();
        $res = $engine->processGroup('lingkungan', ['dummy' => 0.0]);

        $this->assertIsArray($res);
        $this->assertEquals(50.0, $res['value']);
        $this->assertEquals('Mid', $res['label']);
    }
}
