<?php

namespace Tests\Unit\Fuzzy;

use App\Services\Fuzzy\CalculateMortalitas;
use PHPUnit\Framework\TestCase;

class CalculateMortalitasTest extends TestCase
{
    private CalculateMortalitas $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculateMortalitas();
    }

    public function test_persenMortalitas_hitungPersen(): void
    {
        $this->assertSame(0.25, $this->service->persenMortalitas(5, 2000));
    }

    public function test_persenMortalitas_pembulatanTigaDesimal(): void
    {
        $this->assertSame(33.333, $this->service->persenMortalitas(1, 3));
    }

    public function test_persenMortalitas_populasiNolMengembalikanNol(): void
    {
        $this->assertSame(0.0, $this->service->persenMortalitas(5, 0));
    }
}