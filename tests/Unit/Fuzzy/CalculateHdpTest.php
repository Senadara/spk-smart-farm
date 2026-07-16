<?php

namespace Tests\Unit\Fuzzy;

use App\Services\Fuzzy\CalculateHdp;
use PHPUnit\Framework\TestCase;

class CalculateHdpTest extends TestCase
{
    private CalculateHdp $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculateHdp();
    }

    public function test_persenHdp_hitungPersenDuaDesimal(): void
    {
        $this->assertSame(90.0, $this->service->persenHdp(1800, 2000));
    }

    public function test_persenHdp_produksiPenuh(): void
    {
        $this->assertSame(100.0, $this->service->persenHdp(2000, 2000));
    }

    public function test_persenHdp_populasiNolMengembalikanNol(): void
    {
        $this->assertSame(0.0, $this->service->persenHdp(1800, 0));
    }
}