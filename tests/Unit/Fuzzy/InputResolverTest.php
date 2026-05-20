<?php

namespace Tests\Unit\Fuzzy;

use App\Services\Fuzzy\InputResolver;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\TestCase;

class InputResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_resolve_mengambil_nilai_dari_database_source(): void
    {
        $var = (object) [
            'name' => 'total_feed',
            'group' => 'kesehatan',
            'inputSource' => (object) [
                'source_type' => 'database',
                'source_name' => 'consumption_table',
                'field_name' => 'amount',
            ],
        ];

        $spkVar = Mockery::mock('alias:App\\Models\\SpkFuzzyVariable');
        $spkVar->shouldReceive('with')->with('inputSource')->andReturnSelf();
        $spkVar->shouldReceive('where')->with('type', 'input')->andReturnSelf();
        $spkVar->shouldReceive('get')->andReturn(collect([$var]));

        // Mock DB::table(...)->sum('amount')
        $query = Mockery::mock();
        $query->shouldReceive('sum')->with('amount')->andReturn(123.5);

        DB::shouldReceive('table')->with('consumption_table')->andReturn($query);

        $resolver = new InputResolver();
        $result = $resolver->resolve(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_feed', $result);
        $this->assertEquals(123.5, $result['total_feed']);
    }

    public function test_resolve_mengambil_nilai_dari_iot_source(): void
    {
        $var = (object) [
            'name' => 'suhu',
            'group' => 'lingkungan',
            'inputSource' => (object) [
                'source_type' => 'iot',
                'extra_config' => ['parameterCode' => 'TEMP']
            ],
        ];

        $spkVar = Mockery::mock('alias:App\\Models\\SpkFuzzyVariable');
        $spkVar->shouldReceive('with')->with('inputSource')->andReturnSelf();
        $spkVar->shouldReceive('where')->with('type', 'input')->andReturnSelf();
        $spkVar->shouldReceive('get')->andReturn(collect([$var]));

        // Mock DB query chain for iot_sensor_data
        $query = Mockery::mock();
        $query->shouldReceive('join')->andReturnSelf();
        $query->shouldReceive('where')->andReturnSelf();
        $query->shouldReceive('orderBy')->andReturnSelf();
        $query->shouldReceive('value')->with('iot_sensor_data.value')->andReturn(42.7);

        DB::shouldReceive('table')->with('iot_sensor_data')->andReturn($query);

        $resolver = new InputResolver();
        $result = $resolver->resolve(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('suhu', $result);
        $this->assertEquals(42.7, $result['suhu']);
    }

    public function test_resolve_mengambil_nilai_dari_function_service(): void
    {
        $var = (object) [
            'name' => 'hdp',
            'group' => 'kesehatan',
            'inputSource' => (object) [
                'source_type' => 'function',
                'function_name' => 'Tests\\Unit\\Fuzzy\\Fixtures\\DummyHdpService'
            ],
        ];

        $spkVar = Mockery::mock('alias:App\\Models\\SpkFuzzyVariable');
        $spkVar->shouldReceive('with')->with('inputSource')->andReturnSelf();
        $spkVar->shouldReceive('where')->with('type', 'input')->andReturnSelf();
        $spkVar->shouldReceive('get')->andReturn(collect([$var]));

        // Define dummy service class used by InputResolver
        if (!class_exists('Tests\\Unit\\Fuzzy\\Fixtures\\DummyHdpService')) {
            eval ('namespace Tests\\Unit\\Fuzzy\\Fixtures; class DummyHdpService { public function handle(?string $coopId = null): float { return 77.3; } }');
        }

        $resolver = new InputResolver();
        $result = $resolver->resolve(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hdp', $result);
        $this->assertEquals(77.3, $result['hdp']);
    }
}
