<?php

namespace Tests\Unit\Services;

use App\Services\NormalizationService;
use PHPUnit\Framework\TestCase;

class NormalizationServiceTest extends TestCase
{
    public function test_benefit_and_cost_normalization(): void
    {
        $service = new NormalizationService();

        $minMax = [
            1 => ['min' => 100, 'max' => 500],
            2 => ['min' => 80, 'max' => 95],
        ];

        $normalized = $service->normalizeForEntity(
            [1 => 250, 2 => 90],
            [1 => 'cost', 2 => 'benefit'],
            $minMax
        );

        $this->assertEqualsWithDelta(100 / 250, $normalized[1], 0.0001);
        $this->assertEqualsWithDelta(90 / 95, $normalized[2], 0.0001);
    }
}
