<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\CalculatePakan;
use PHPUnit\Framework\TestCase;

// Tujuan: memastikan konsumsi pakan per ekor dihitung dalam gram secara benar — berguna untuk memantau efisiensi pakan harian.

class CalculatePakanTest extends TestCase
{
    private CalculatePakan $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculatePakan();
    }

    // Pakan per ekor = total pakan (kg) × 1000 ÷ jumlah ayam, hasil dalam gram
    public function test_menghitung_gram_pakan_per_ekor(): void
    {
        $this->assertSame(125.0, $this->service->pakanPerEkor(250, 2000));
    }

    // Hasil dibulatkan ke satu desimal
    public function test_hasil_dibulatkan_satu_desimal(): void
    {
        $this->assertSame(33333.3, $this->service->pakanPerEkor(100, 3));
    }

    // Populasi nol — aman, kembalikan 0
    public function test_kandang_kosong_hasil_nol(): void
    {
        $this->assertSame(0.0, $this->service->pakanPerEkor(50, 0));
    }
}
