<?php

namespace Tests\Unit\Ahp;

use App\Services\AHPService;
use PHPUnit\Framework\TestCase;

class AHPComputeWeightsTest extends TestCase
{
    private AHPService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AHPService();
    }

    public function test_computeWeights_matriksKonsistenSempurna_bobotSesuaiRasio(): void
    {
        $matrix = [
            [1, 2, 6],
            [1 / 2, 1, 3],
            [1 / 6, 1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(0.6, $r['weights'][0], 1e-6);
        $this->assertEqualsWithDelta(0.3, $r['weights'][1], 1e-6);
        $this->assertEqualsWithDelta(0.1, $r['weights'][2], 1e-6);
        $this->assertEqualsWithDelta(3.0, $r['lambda_max'], 1e-6);
        $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-6);
        $this->assertTrue($r['is_valid']);
    }

    public function test_computeWeights_contohSaaty_crKecilDanValid(): void
    {
        $matrix = [
            [1, 3, 5],
            [1 / 3, 1, 3],
            [1 / 5, 1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(0.633346, $r['weights'][0], 1e-4);
        $this->assertEqualsWithDelta(0.260498, $r['weights'][1], 1e-4);
        $this->assertEqualsWithDelta(0.106156, $r['weights'][2], 1e-4);
        $this->assertEqualsWithDelta(3.038715, $r['lambda_max'], 1e-4);
        $this->assertEqualsWithDelta(0.019357, $r['ci'], 1e-4);
        $this->assertEqualsWithDelta(0.033375, $r['cr'], 1e-4);
        $this->assertTrue($r['is_valid']);
    }

    public function test_computeWeights_semuaKriteriaSama_bobotMerata(): void
    {
        $matrix = [
            [1, 1, 1],
            [1, 1, 1],
            [1, 1, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(1 / 3, $r['weights'][0], 1e-6);
        $this->assertEqualsWithDelta(1 / 3, $r['weights'][1], 1e-6);
        $this->assertEqualsWithDelta(1 / 3, $r['weights'][2], 1e-6);
        $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-6);
        $this->assertTrue($r['is_valid']);
    }

    public function test_computeWeights_matriksTidakKonsisten_ditolak(): void
    {
        $matrix = [
            [1, 5, 1 / 5],
            [1 / 5, 1, 5],
            [5, 1 / 5, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(6.2, $r['lambda_max'], 1e-4);
        $this->assertGreaterThan(0.1, $r['cr']);
        $this->assertFalse($r['is_valid']);
    }

    public function test_computeWeights_ukuran2x2_riNolTidakMembagiNol(): void
    {
        $matrix = [
            [1, 3],
            [1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(0.75, $r['weights'][0], 1e-6);
        $this->assertEqualsWithDelta(0.25, $r['weights'][1], 1e-6);
        $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-9); // RI(2)=0 -> guard mengembalikan 0
        $this->assertTrue($r['is_valid']);
    }

    public function test_computeWeights_totalBobotSelaluSatu(): void
    {
        $matrix = [
            [1, 3, 5],
            [1 / 3, 1, 3],
            [1 / 5, 1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(1.0, array_sum($r['weights']), 1e-9);
    }
}