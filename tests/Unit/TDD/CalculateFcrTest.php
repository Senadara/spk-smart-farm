<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\CalculateFcr;
use PHPUnit\Framework\TestCase;

// Tujuan: memastikan rasio pakan terhadap telur (FCR) dihitung akurat — semakin kecil nilainya berarti peternakan semakin efisien.

class CalculateFcrTest extends TestCase
{
    private CalculateFcr $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculateFcr();
    }

    // FCR = total pakan (kg) ÷ total telur (kg), semakin rendah semakin efisien
    public function test_menghitung_rasio_pakan_dibanding_telur(): void
    {
        $this->assertSame(1.25, $this->service->rasioFcr(250, 200));
    }

    // Hasil perhitungan dibulatkan ke satu desimal
    public function test_hasil_dibulatkan_satu_desimal(): void
    {
        $this->assertSame(1.5, $this->service->rasioFcr(300, 200));
    }

    // Kalau telur belum dipanen (massa 0), kembalikan 0 agar tidak error pembagian nol
    public function test_telur_belum_ada_produksi_hasil_nol(): void
    {
        $this->assertSame(0.0, $this->service->rasioFcr(100, 0));
    }
}
