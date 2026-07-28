<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\MamdaniEngine;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

// Tujuan: memastikan mesin logika fuzzy berjalan sesuai aturan IF-THEN — menggabungkan banyak kondisi sensor (suhu, amonia) menjadi satu keputusan diagnosis.

class MamdaniEngineTest extends TestCase
{
    private function invoke(string $method, array $args)
    {
        $engine = new MamdaniEngine();
        $ref = new ReflectionMethod(MamdaniEngine::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($engine, $args);
    }

    // Data tiruan hasil fuzzifikasi — tiap sensor sudah dipetakan ke himpunan fuzzy dengan derajat keanggotaan
    private array $fuzzified = [
        'suhu'   => ['Panas' => 0.8, 'Nyaman' => 0.3],
        'amonia' => ['Tinggi' => 0.6, 'Rendah' => 0.4],
    ];

    // Operator AND di fuzzy itu ambil nilai terkecil dari semua kondisi dalam satu rule
    public function test_rule_dengan_operator_dan_mengambil_nilai_terkecil(): void
    {
        $rule = ['operator' => 'AND', 'conditions' => [
            ['variable_name' => 'suhu', 'set_name' => 'Panas'],
            ['variable_name' => 'amonia', 'set_name' => 'Tinggi'],
        ]];
        $this->assertEqualsWithDelta(0.6, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    // Operator OR di fuzzy mengambil nilai terbesar
    public function test_rule_dengan_operator_atau_mengambil_nilai_terbesar(): void
    {
        $rule = ['operator' => 'OR', 'conditions' => [
            ['variable_name' => 'suhu', 'set_name' => 'Panas'],
            ['variable_name' => 'amonia', 'set_name' => 'Tinggi'],
        ]];
        $this->assertEqualsWithDelta(0.8, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    // Kalau himpunan fuzzy yang disebut di rule tidak ada di data sensor, derajatnya nol
    public function test_himpunan_tidak_dikenal_dianggap_nol(): void
    {
        $rule = ['operator' => 'AND', 'conditions' => [
            ['variable_name' => 'suhu', 'set_name' => 'Panas'],
            ['variable_name' => 'amonia', 'set_name' => 'TidakAda'],
        ]];
        $this->assertEqualsWithDelta(0.0, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    // Rule tanpa kondisi sama sekali — otomatis alpha-nya nol
    public function test_rule_tanpa_kondisi_derajat_nol(): void
    {
        $rule = ['operator' => 'AND', 'conditions' => []];
        $this->assertEqualsWithDelta(0.0, $this->invoke('computeAlpha', [$rule, $this->fuzzified]), 1e-9);
    }

    // Beberapa rule bisa mengarah ke output yang sama — sistem ambil derajat tertinggi
    // Rule dengan alpha tertinggi jadi rule dominan yang dipakai untuk narasi
    public function test_beberapa_rule_mengarah_ke_output_sama_digabung_dengan_max(): void
    {
        $rules = [
            ['name' => 'R1', 'operator' => 'AND', 'output_set_id' => 10, 'conditions' => [
                ['variable_name' => 'suhu', 'set_name' => 'Panas'],
                ['variable_name' => 'amonia', 'set_name' => 'Tinggi'],
            ]],
            ['name' => 'R2', 'operator' => 'OR', 'output_set_id' => 20, 'conditions' => [
                ['variable_name' => 'suhu', 'set_name' => 'Nyaman'],
                ['variable_name' => 'amonia', 'set_name' => 'Rendah'],
            ]],
            ['name' => 'R3', 'operator' => 'AND', 'output_set_id' => 10, 'conditions' => [
                ['variable_name' => 'suhu', 'set_name' => 'Panas'],
                ['variable_name' => 'amonia', 'set_name' => 'Rendah'],
            ]],
        ];
        [$aggregated, $dominant] = $this->invoke('evaluateRules', [$rules, $this->fuzzified]);
        $this->assertEqualsWithDelta(0.6, $aggregated[10], 1e-9);
        $this->assertEqualsWithDelta(0.4, $aggregated[20], 1e-9);
        $this->assertSame('R1', $dominant['name']);
        $this->assertEqualsWithDelta(0.6, $dominant['alpha'], 1e-9);
    }

    // Rule yang kondisi sensornya tidak cocok (alpha nol) langsung dilewati
    public function test_rule_bernilai_nol_dilewati(): void
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

    // Label diagnosis diambil dari himpunan yang punya derajat keanggotaan tertinggi
    public function test_label_diagnosis_mengikuti_himpunan_terkuat(): void
    {
        $sets = [
            10 => (object) ['id' => 10, 'name' => 'Buruk'],
            20 => (object) ['id' => 20, 'name' => 'Optimal'],
        ];
        $aggregated = [10 => 0.6, 20 => 0.4];
        $label = $this->invoke('determineLabelFromCrisp', [$sets, $aggregated, 12.0]);
        $this->assertSame('Buruk', $label);
    }

    // Yang menentukan label itu derajat keanggotaan, bukan nilai crisp hasil defuzzifikasi
    public function test_label_tidak_dipengaruhi_nilai_defuzzifikasi(): void
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
