<?php

namespace Tests\Unit\TDD;

use App\Services\SupplierDistanceService;
use PHPUnit\Framework\TestCase;

class SupplierDistanceServiceTest extends TestCase
{
    private SupplierDistanceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SupplierDistanceService();
    }

    public function test_haversine_titik_sama_menghasilkan_nol(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->svc->haversineKm(-6.2, 106.8, -6.2, 106.8), 1e-9);
    }

    public function test_haversine_satu_derajat_lintang_sekitar_111km(): void
    {
        $this->assertEqualsWithDelta(111.19, $this->svc->haversineKm(0.0, 0.0, 1.0, 0.0), 0.05);
    }

    public function test_haversine_satu_derajat_bujur_di_ekuator_sekitar_111km(): void
    {
        $this->assertEqualsWithDelta(111.19, $this->svc->haversineKm(0.0, 0.0, 0.0, 1.0), 0.05);
    }

    public function test_haversine_jakarta_bandung_sekitar_116km(): void
    {
        $d = $this->svc->haversineKm(-6.2088, 106.8456, -6.9175, 107.6191);
        $this->assertEqualsWithDelta(116.2, $d, 1.0);
    }

    public function test_haversine_simetris(): void
    {
        $ab = $this->svc->haversineKm(-6.2088, 106.8456, -6.9175, 107.6191);
        $ba = $this->svc->haversineKm(-6.9175, 107.6191, -6.2088, 106.8456);
        $this->assertEqualsWithDelta($ab, $ba, 1e-9);
    }

    public function test_haversine_dibulatkan_maksimal_2_desimal(): void
    {
        $d = $this->svc->haversineKm(-6.2088, 106.8456, -6.9175, 107.6191);
        $this->assertSame(round($d, 2), $d);
    }

    public function test_distanceLabel_null_menghasilkan_placeholder(): void
    {
        $this->assertSame('Lokasi belum lengkap', $this->svc->distanceLabel(null));
    }

    public function test_distanceLabel_desimal_pakai_koma(): void
    {
        $this->assertSame('116,2 km', $this->svc->distanceLabel(116.239));
    }

    public function test_distanceLabel_pemisah_ribuan_pakai_titik(): void
    {
        $this->assertSame('1.234,5 km', $this->svc->distanceLabel(1234.5));
    }

    public function test_distanceLabel_nol(): void
    {
        $this->assertSame('0,0 km', $this->svc->distanceLabel(0.0));
    }
}