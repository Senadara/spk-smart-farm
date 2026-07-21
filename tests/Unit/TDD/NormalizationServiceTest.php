<?php

namespace Tests\Unit\TDD;

use App\Services\NormalizationService;
use PHPUnit\Framework\TestCase;

class NormalizationServiceTest extends TestCase
{
    private NormalizationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new NormalizationService();
    }

    private function row(int $parameterId, float $value): object
    {
        return (object) ['parameter_id' => $parameterId, 'value' => $value];
    }

    public function test_computeMinMax_min_dan_max_per_parameter(): void
    {
        $rows = [
            $this->row(1, 3000), $this->row(1, 2500), $this->row(1, 2800),
            $this->row(2, 80),   $this->row(2, 90),   $this->row(2, 85),
        ];

        $minMax = $this->svc->computeMinMax($rows);

        $this->assertEqualsWithDelta(2500, $minMax[1]['min'], 1e-9);
        $this->assertEqualsWithDelta(3000, $minMax[1]['max'], 1e-9);
        $this->assertEqualsWithDelta(80,   $minMax[2]['min'], 1e-9);
        $this->assertEqualsWithDelta(90,   $minMax[2]['max'], 1e-9);
    }

    public function test_computeMinMax_nilai_tunggal_min_sama_dengan_max(): void
    {
        $minMax = $this->svc->computeMinMax([$this->row(5, 42.0)]);

        $this->assertEqualsWithDelta(42.0, $minMax[5]['min'], 1e-9);
        $this->assertEqualsWithDelta(42.0, $minMax[5]['max'], 1e-9);
    }

    public function test_normalizeForEntity_benefit_value_dibagi_max(): void
    {
        $norm = $this->svc->normalizeForEntity(
            [2 => 80.0], [2 => 'benefit'], [2 => ['min' => 80.0, 'max' => 90.0]]
        );
        $this->assertEqualsWithDelta(80.0 / 90.0, $norm[2], 1e-9);
    }

    public function test_normalizeForEntity_cost_min_dibagi_value(): void
    {
        $norm = $this->svc->normalizeForEntity(
            [1 => 3000.0], [1 => 'cost'], [1 => ['min' => 2500.0, 'max' => 3000.0]]
        );
        $this->assertEqualsWithDelta(2500.0 / 3000.0, $norm[1], 1e-9);
    }

    public function test_normalizeForEntity_default_type_benefit(): void
    {
        $norm = $this->svc->normalizeForEntity(
            [7 => 50.0], [], [7 => ['min' => 10.0, 'max' => 100.0]]
        );
        $this->assertEqualsWithDelta(0.5, $norm[7], 1e-9);
    }

    public function test_normalizeForEntity_benefit_max_nol_aman(): void
    {
        $norm = $this->svc->normalizeForEntity(
            [3 => 10.0], [3 => 'benefit'], [3 => ['min' => 0.0, 'max' => 0.0]]
        );
        $this->assertEqualsWithDelta(0.0, $norm[3], 1e-9);
    }

    public function test_normalizeForEntity_cost_value_nol_aman(): void
    {
        $norm = $this->svc->normalizeForEntity(
            [4 => 0.0], [4 => 'cost'], [4 => ['min' => 5.0, 'max' => 20.0]]
        );
        $this->assertEqualsWithDelta(0.0, $norm[4], 1e-9);
    }


    public function test_saw_pipeline_skor_dan_ranking_benar(): void
    {
        $paramTypes = [1 => 'cost', 2 => 'benefit', 3 => 'benefit'];
        $weights    = [1 => 0.5, 2 => 0.3, 3 => 0.2]; 

        $suppliers = [
            'A' => [1 => 3000.0, 2 => 80.0, 3 => 90.0],
            'B' => [1 => 2500.0, 2 => 90.0, 3 => 70.0],
            'C' => [1 => 2800.0, 2 => 85.0, 3 => 80.0],
        ];

        $rows = [];
        foreach ($suppliers as $vals) {
            foreach ($vals as $pid => $v) {
                $rows[] = $this->row($pid, $v);
            }
        }
        $minMax = $this->svc->computeMinMax($rows);

        $scores = [];
        foreach ($suppliers as $name => $vals) {
            $norm  = $this->svc->normalizeForEntity($vals, $paramTypes, $minMax);
            $score = 0.0;
            foreach ($norm as $pid => $nv) {
                $score += $nv * $weights[$pid];
            }
            $scores[$name] = $score;
        }

        $this->assertEqualsWithDelta(0.883333, $scores['A'], 1e-4);
        $this->assertEqualsWithDelta(0.955556, $scores['B'], 1e-4);
        $this->assertEqualsWithDelta(0.907540, $scores['C'], 1e-4);

        arsort($scores);
        $this->assertSame(['B', 'C', 'A'], array_keys($scores));
    }
}