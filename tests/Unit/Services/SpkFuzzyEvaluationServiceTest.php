<?php

namespace Tests\Unit\Services\Spk;

use App\Models\SpkFuzzyLog;
use App\Models\SpkFuzzyProfile;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\Spk\SpkFuzzyEvaluationService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class SpkFuzzyEvaluationServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function evaluate_menggabungkan_inputs_narrative_dan_profile(): void
    {
        Mockery::mock('alias:' . SpkFuzzyProfile::class)
            ->shouldReceive('resolveForContext')
            ->once()
            ->with('KOM-1', null, null)
            ->andReturnNull();

        $inputs = ['suhu' => 30.0, 'amonia' => 12.0];
        $mamdaniResult = [
            'lingkungan' => ['label' => 'Panas', 'value' => 0.7],
            'kesehatan'  => ['label' => 'Sehat', 'value' => 0.9],
        ];

        $inputResolver = Mockery::mock(InputResolver::class);
        $inputResolver->shouldReceive('resolve')
            ->once()
            ->with(null, 'KOM-1', null) 
            ->andReturn($inputs);

        $mamdani = Mockery::mock(MamdaniEngine::class);
        $mamdani->shouldReceive('processCascaded')
            ->once()
            ->with($inputs, null, 'KOM-1', null)
            ->andReturn($mamdaniResult);

        $narrativeGen = Mockery::mock(NarrativeGenerator::class);
        $narrativeGen->shouldReceive('generate')
            ->once()
            ->with($mamdaniResult, null) 
            ->andReturn('Kondisi kandang panas namun ternak sehat.');

        $service = new SpkFuzzyEvaluationService($inputResolver, $mamdani, $narrativeGen);

        $actual = $service->evaluate(null, 'KOM-1', null);

        $this->assertSame('Panas', $actual['lingkungan']['label']);
        $this->assertSame($inputs, $actual['inputs']);
        $this->assertSame('Kondisi kandang panas namun ternak sehat.', $actual['narrative']);
        $this->assertNull($actual['profile']);
    }

    public function persist_output_value_adalah_minimum_dari_lingkungan_dan_kesehatan(): void
    {
        $result = [
            'inputs'     => ['suhu' => 30.0],
            'profile'    => ['id' => 'PRF-1', 'commodity_id' => 'KOM-1'],
            'lingkungan' => ['label' => 'Panas', 'value' => 0.8, 'fuzzified' => ['panas' => 0.8], 'dominant_rule' => 'R1'],
            'kesehatan'  => ['label' => 'Sehat', 'value' => 0.3, 'fuzzified' => ['sehat' => 0.3], 'dominant_rule' => 'R2'],
            'kausalitas' => ['label' => 'Waspada', 'recommendation' => 'Turunkan suhu'],
            'narrative'  => 'Teks narasi.',
        ];

        $logMock = Mockery::mock('overload:' . SpkFuzzyLog::class);
        $logMock->commodity_id = 'KOM-1';
        $logMock->unit_budidaya_id = 'COOP-9';
        $logMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return $payload['output_value'] === 0.3
                    && $payload['unit_budidaya_id'] === 'COOP-9'
                    && $payload['profile_id'] === 'PRF-1'
                    && $payload['commodity_id'] === 'KOM-1'
                    && $payload['status_lingkungan'] === 'Panas'
                    && $payload['status_kesehatan'] === 'Sehat'
                    && $payload['output_label'] === 'Waspada'
                    && $payload['recommendation'] === 'Turunkan suhu';
            }))
            ->andReturnSelf();

        Cache::shouldReceive('forget')->once()->with('peternakan:KOM-1:fuzzy:COOP-9');

        $service = new SpkFuzzyEvaluationService(
            Mockery::mock(InputResolver::class),
            Mockery::mock(MamdaniEngine::class),
            Mockery::mock(NarrativeGenerator::class),
        );

        $log = $service->persist('COOP-9', $result, 'KOM-1');

        $this->assertSame($logMock, $log);
    }

    public function persist_memakai_default_saat_value_dan_konteks_hilang(): void
    {
        $result = [
            'lingkungan' => ['label' => 'A'],
            'kesehatan'  => ['label' => 'B'],
        ];

        $logMock = Mockery::mock('overload:' . SpkFuzzyLog::class);
        $logMock->commodity_id = null;
        $logMock->unit_budidaya_id = null;
        $logMock->shouldReceive('create')
            ->once()
            ->with(Mockery::on(fn (array $p): bool => $p['output_value'] === 0.0))
            ->andReturnSelf();

        Cache::shouldReceive('forget')->once()->with('peternakan:default:fuzzy:all');

        $service = new SpkFuzzyEvaluationService(
            Mockery::mock(InputResolver::class),
            Mockery::mock(MamdaniEngine::class),
            Mockery::mock(NarrativeGenerator::class),
        );

        $service->persist(null, $result, null);
    }

    public function evaluateAndPersist_memanggil_evaluate_lalu_persist(): void
    {
        $result = ['dummy' => true];
        $log = Mockery::mock(SpkFuzzyLog::class);

        $service = Mockery::mock(SpkFuzzyEvaluationService::class, [
            Mockery::mock(InputResolver::class),
            Mockery::mock(MamdaniEngine::class),
            Mockery::mock(NarrativeGenerator::class),
        ])->makePartial();

        $service->shouldReceive('evaluate')->once()->with('COOP-1', 'KOM-1', 'PRF-1')->andReturn($result);
        $service->shouldReceive('persist')->once()->with('COOP-1', $result, 'KOM-1')->andReturn($log);

        $this->assertSame($log, $service->evaluateAndPersist('COOP-1', 'KOM-1', 'PRF-1'));
    }
}
