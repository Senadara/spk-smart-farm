<?php

namespace Tests\Unit\Fuzzy;

use App\Services\Fuzzy\CalculatePakan;
use PHPUnit\Framework\TestCase;

class CalculatePakanTest extends TestCase
{
    private CalculatePakan $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculatePakan();
    }

    public function test_pakanPerEkor_konversiKgKeGramPerEkor(): void
    {
        $this->assertSame(125.0, $this->service->pakanPerEkor(250, 2000));
    }

    public function test_pakanPerEkor_pembulatanSatuDesimal(): void
    {
        $this->assertSame(33333.3, $this->service->pakanPerEkor(100, 3));
    }

    public function test_pakanPerEkor_populasiNolMengembalikanNol(): void
    {
        $this->assertSame(0.0, $this->service->pakanPerEkor(50, 0));
    }
}