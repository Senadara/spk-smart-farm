<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\CalculateHdp;
use PHPUnit\Framework\TestCase;

// Tujuan: memastikan persentase produksi telur (HDP) dihitung benar — membandingkan jumlah telur dengan jumlah ayam yang ada.

class CalculateHdpTest extends TestCase
{
    private CalculateHdp $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculateHdp();
    }

    // HDP = (jumlah telur ÷ jumlah ayam) × 100%, hasil dalam persen
    public function test_menghitung_persen_produksi_telur(): void
    {
        $this->assertSame(90.0, $this->service->persenHdp(1800, 2000));
    }

    // Kalau semua ayam bertelur, HDP harus 100%
    public function test_produksi_telur_maksimal(): void
    {
        $this->assertSame(100.0, $this->service->persenHdp(2000, 2000));
    }

    // Kandang kosong — kembalikan 0%, jangan error pembagian nol
    public function test_kandang_kosong_hasil_nol(): void
    {
        $this->assertSame(0.0, $this->service->persenHdp(1800, 0));
    }
}
