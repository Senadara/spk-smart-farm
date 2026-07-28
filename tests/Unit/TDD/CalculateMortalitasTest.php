<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\CalculateMortalitas;
use PHPUnit\Framework\TestCase;

// Tujuan: memastikan angka kematian ayam dihitung dalam persen secara benar — termasuk penanganan data ekstrem seperti kandang kosong.

class CalculateMortalitasTest extends TestCase
{
    private CalculateMortalitas $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalculateMortalitas();
    }

    // Mortalitas = (jumlah mati ÷ populasi) × 100%
    public function test_menghitung_persen_angka_kematian(): void
    {
        $this->assertSame(0.25, $this->service->persenMortalitas(5, 2000));
    }

    // Hasil dibulatkan ke tiga desimal untuk akurasi
    public function test_hasil_dibulatkan_tiga_desimal(): void
    {
        $this->assertSame(33.333, $this->service->persenMortalitas(1, 3));
    }

    // Kandang kosong — kembalikan 0 agar tidak error
    public function test_populasi_nol_hasil_nol(): void
    {
        $this->assertSame(0.0, $this->service->persenMortalitas(5, 0));
    }
}
