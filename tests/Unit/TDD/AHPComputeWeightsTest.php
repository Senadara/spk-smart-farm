<?php

namespace Tests\Unit\TDD;

use App\Services\AHPService;
use PHPUnit\Framework\TestCase;

// Tujuan: memastikan bobot prioritas tiap kriteria dihitung adil dan konsisten — total selalu 100%, matriks yang saling bertentangan otomatis ditolak.

class AHPComputeWeightsTest extends TestCase
{
    private AHPService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AHPService();
    }

    // Kasus ideal — semua perbandingan konsisten, bobot keluar sesuai ekspektasi perhitungan AHP
    public function test_matriks_perbandingan_konsisten(): void
    {
        $matrix = [
            [1, 2, 6],
            [1 / 2, 1, 3],
            [1 / 6, 1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(0.6, $r['weights'][0], 1e-6);
        $this->assertEqualsWithDelta(0.3, $r['weights'][1], 1e-6);
        $this->assertEqualsWithDelta(0.1, $r['weights'][2], 1e-6);
        $this->assertEqualsWithDelta(3.0, $r['lambda_max'], 1e-6);
        $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-6);
        $this->assertTrue($r['is_valid']);
    }

    // Contoh dari buku Saaty — matriks sedikit tidak konsisten tapi masih di bawah batas toleransi (CR < 0,1)
    public function test_matriks_dengan_sedikit_ketidakonsistenan(): void
    {
        $matrix = [
            [1, 3, 5],
            [1 / 3, 1, 3],
            [1 / 5, 1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(0.633346, $r['weights'][0], 1e-4);
        $this->assertEqualsWithDelta(0.260498, $r['weights'][1], 1e-4);
        $this->assertEqualsWithDelta(0.106156, $r['weights'][2], 1e-4);
        $this->assertEqualsWithDelta(3.038715, $r['lambda_max'], 1e-4);
        $this->assertEqualsWithDelta(0.019357, $r['ci'], 1e-4);
        $this->assertEqualsWithDelta(0.033375, $r['cr'], 1e-4);
        $this->assertTrue($r['is_valid']);
    }

    // Semua kriteria dianggap sama penting — bobot harus terbagi rata
    public function test_semua_kriteria_dianggap_sama_penting(): void
    {
        $matrix = [
            [1, 1, 1],
            [1, 1, 1],
            [1, 1, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(1 / 3, $r['weights'][0], 1e-6);
        $this->assertEqualsWithDelta(1 / 3, $r['weights'][1], 1e-6);
        $this->assertEqualsWithDelta(1 / 3, $r['weights'][2], 1e-6);
        $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-6);
        $this->assertTrue($r['is_valid']);
    }

    // Matriks yang saling bertentangan — CR jauh di atas 0,1, sistem harus menolak
    public function test_matriks_tidak_konsisten_ditolak(): void
    {
        $matrix = [
            [1, 5, 1 / 5],
            [1 / 5, 1, 5],
            [5, 1 / 5, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(6.2, $r['lambda_max'], 1e-4);
        $this->assertGreaterThan(0.1, $r['cr']);
        $this->assertFalse($r['is_valid']);
    }

    // Matriks 2x2 punya RI = 0, pastikan tidak terjadi pembagian dengan nol
    public function test_matriks_ukuran_dua_kali_dua_aman_dihitung(): void
    {
        $matrix = [
            [1, 3],
            [1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(0.75, $r['weights'][0], 1e-6);
        $this->assertEqualsWithDelta(0.25, $r['weights'][1], 1e-6);
        $this->assertEqualsWithDelta(0.0, $r['cr'], 1e-9);
        $this->assertTrue($r['is_valid']);
    }

    // Sifat dasar AHP: total seluruh bobot harus selalu 1 (100%)
    public function test_total_bobot_selalu_satu(): void
    {
        $matrix = [
            [1, 3, 5],
            [1 / 3, 1, 3],
            [1 / 5, 1 / 3, 1],
        ];

        $r = $this->service->computeWeights($matrix);

        $this->assertEqualsWithDelta(1.0, array_sum($r['weights']), 1e-9);
    }
}
