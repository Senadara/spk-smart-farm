<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\MamdaniEngine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MamdaniEngineTest extends TestCase
{
    private function invoke(string $method, array $args)
    {
        $engine = new MamdaniEngine();
        $ref = new ReflectionMethod(MamdaniEngine::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($engine, $args);
    }

    private array $fuzzified = [
        'suhu'   => ['Panas' => 0.8, 'Nyaman' => 0.3],
        'amonia' => ['Tinggi' => 0.6, 'Rendah' => 0.4],
    ];

    public function test_computeAlpha_AND_mengambil_minimum(): void
    {
        $rule = ['operator' => 'AND', 'conditions' => [
            ['variable_name' => 'suhu', 'set_name' => 'Panas'],
            ['variable_name' => 'amonia', 'set_name' => 'Tinggi'],
        ]];
        $this->assertEqualsWithDelta(0.6, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    public function test_computeAlpha_OR_mengambil_maksimum(): void
    {
        $rule = ['operator' => 'OR', 'conditions' => [
            ['variable_name' => 'suhu', 'set_name' => 'Panas'],
            ['variable_name' => 'amonia', 'set_name' => 'Tinggi'],
        ]];
        $this->assertEqualsWithDelta(0.8, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    public function test_computeAlpha_set_tak_dikenal_dianggap_nol(): void
    {
        $rule = ['operator' => 'AND', 'conditions' => [
            ['variable_name' => 'suhu', 'set_name' => 'Panas'],
            ['variable_name' => 'amonia', 'set_name' => 'TidakAda'],
        ]];
        $this->assertEqualsWithDelta(0.0, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    public function test_computeAlpha_tanpa_kondisi_mengembalikan_nol(): void
    {
        $rule = ['operator' => 'AND', 'conditions' => []];
        $this->assertEqualsWithDelta(0.0, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    public function test_evaluateRules_agregasi_MAX_dan_dominant(): void
    {
        $rules = [
            ['name' => 'R1', 'operator' => 'AND', 'output_set_id' => 10, 'conditions' => [
                ['variable_name' => 'suhu', 'set_name' => 'Panas'],
                ['variable_name' => 'amonia', 'set_name' => 'Tinggi'],
            ]], // min(0.8, 0.6) = 0.6
            ['name' => 'R2', 'operator' => 'OR', 'output_set_id' => 20, 'conditions' => [
                ['variable_name' => 'suhu', 'set_name' => 'Nyaman'],
                ['variable_name' => 'amonia', 'set_name' => 'Rendah'],
            ]], // max(0.3, 0.4) = 0.4
            ['name' => 'R3', 'operator' => 'AND', 'output_set_id' => 10, 'conditions' => [
                ['variable_name' => 'suhu', 'set_name' => 'Panas'],
                ['variable_name' => 'amonia', 'set_name' => 'Rendah'],
            ]], // min(0.8, 0.4) = 0.4
        ];
        [$aggregated, $dominant] = $this->invoke('evaluateRules', [$rules, $this->fuzzified]);
        $this->assertEqualsWithDelta(0.6, $aggregated[10], 1e-9); // MAX(0.6, 0.4)
        $this->assertEqualsWithDelta(0.4, $aggregated[20], 1e-9);
        $this->assertSame('R1', $dominant['name']);
        $this->assertEqualsWithDelta(0.6, $dominant['alpha'], 1e-9);
    }

    public function test_evaluateRules_melewati_rule_beralpha_nol(): void
    {
        $rules = [
            ['name' => 'Rz', 'operator' => 'AND', 'output_set_id' => 99, 'conditions' => [
                ['variable_name' => 'suhu', 'set_name' => 'Panas'],
                ['variable_name' => 'amonia', 'set_name' => 'TidakAda'],
            ]],
        ];
        [$aggregated, $dominant] = $this->invoke('evaluateRules', [$rules, $this->fuzzified]);
        $this->assertSame([], $aggregated);
        $this->assertNull($dominant);
    }

    public function test_determineLabelFromCrisp_memilih_set_beralpha_tertinggi(): void
    {
        $sets = [
            10 => (object) ['id' => 10, 'name' => 'Buruk'],
            20 => (object) ['id' => 20, 'name' => 'Optimal'],
        ];
        $aggregated = [10 => 0.6, 20 => 0.4];
        $label = $this->invoke('determineLabelFromCrisp', [$sets, $aggregated, 12.0]);
        $this->assertSame('Buruk', $label);
    }

    public function test_determineLabelFromCrisp_mengabaikan_crispValue(): void
    {
        $sets = [
            10 => (object) ['id' => 10, 'name' => 'Buruk'],
            20 => (object) ['id' => 20, 'name' => 'Optimal'],
        ];
        $aggregated = [10 => 0.6, 20 => 0.4];
        $labelLow  = $this->invoke('determineLabelFromCrisp', [$sets, $aggregated, 5.0]);
        $labelHigh = $this->invoke('determineLabelFromCrisp', [$sets, $aggregated, 95.0]);
        $this->assertSame($labelLow, $labelHigh);
    }
}
