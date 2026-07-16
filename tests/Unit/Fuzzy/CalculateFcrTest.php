<?php

namespace Tests\Unit\Fuzzy;

use App\Services\Fuzzy\CalculateFcr;
use PHPUnit\Framework\TestCase;

class CalculateFcrTest extends TestCase
{
    private CalculateFcr $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculateFcr();
    }

    public function test_rasioFcr_pakanDibagiMassaTelur(): void
    {
        $this->assertSame(1.25, $this->service->rasioFcr(250, 200));
    }

    public function test_rasioFcr_pembulatanTigaDesimal(): void
    {
        $this->assertSame(1.5, $this->service->rasioFcr(300, 200));
    }

    public function test_rasioFcr_massaTelurNolMengembalikanNol(): void
    {
        $this->assertSame(0.0, $this->service->rasioFcr(100, 0));
    }
}