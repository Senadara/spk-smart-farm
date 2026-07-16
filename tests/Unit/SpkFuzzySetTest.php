<?php

namespace Tests\Unit;

use App\Models\SpkFuzzySet;
use PHPUnit\Framework\TestCase;

class SpkFuzzySetTest extends TestCase
{
    public function test_left_shoulder_trapezoid_is_active_at_lower_boundary(): void
    {
        $set = new SpkFuzzySet([
            'shape' => 'trapezoid',
            'a' => 0,
            'b' => 0,
            'c' => 55,
            'd' => 70,
        ]);

        $this->assertSame(1.0, $set->membership(0.0));
    }

    public function test_right_shoulder_trapezoid_is_active_at_upper_boundary(): void
    {
        $set = new SpkFuzzySet([
            'shape' => 'trapezoid',
            'a' => 86,
            'b' => 93,
            'c' => 100,
            'd' => 100,
        ]);

        $this->assertSame(1.0, $set->membership(100.0));
    }

    public function test_triangle_shoulder_is_active_at_peak_boundary(): void
    {
        $set = new SpkFuzzySet([
            'shape' => 'triangle',
            'a' => 0,
            'b' => 0,
            'c' => 1,
        ]);

        $this->assertSame(1.0, $set->membership(0.0));
    }
}
