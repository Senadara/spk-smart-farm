<?php

namespace Tests\Unit\TDD;

use App\Services\SupplierDistanceService;
use PHPUnit\Framework\TestCase;

// Tujuan: memastikan jarak antara peternakan dan supplier dihitung akurat dari koordinat GPS — hasil ditampilkan dalam format Indonesia (koma desimal, titik ribuan).

class SupplierDistanceServiceTest extends TestCase
{
    private SupplierDistanceService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new SupplierDistanceService();
    }

    // ---------- Rumus Haversine: menghitung jarak dari koordinat GPS ----------

    // Titik yang sama — jaraknya nol
    public function test_titik_yang_sama_jarak_nol(): void
    {
        $this->assertEqualsWithDelta(0.0, $this->svc->haversineKm(-6.2, 106.8, -6.2, 106.8), 1e-9);
    }

    // Satu derajat lintang di ekuator ≈ 111,19 km
    public function test_satu_derajat_lintang_sekitar_seratus_sebelas_kilometer(): void
    {
        $this->assertEqualsWithDelta(111.19, $this->svc->haversineKm(0.0, 0.0, 1.0, 0.0), 0.05);
    }

    // Satu derajat bujur di ekuator juga ≈ 111,19 km
    public function test_satu_derajat_bujur_di_ekuator_sekitar_seratus_sebelas_kilometer(): void
    {
        $this->assertEqualsWithDelta(111.19, $this->svc->haversineKm(0.0, 0.0, 0.0, 1.0), 0.05);
    }

    // Jarak Jakarta – Bandung ≈ 116 km
    public function test_jarak_jakarta_ke_bandung(): void
    {
        $d = $this->svc->haversineKm(-6.2088, 106.8456, -6.9175, 107.6191);
        $this->assertEqualsWithDelta(116.2, $d, 1.0);
    }

    // A ke B = B ke A (simetris)
    public function test_jarak_a_ke_b_sama_dengan_b_ke_a(): void
    {
        $ab = $this->svc->haversineKm(-6.2088, 106.8456, -6.9175, 107.6191);
        $ba = $this->svc->haversineKm(-6.9175, 107.6191, -6.2088, 106.8456);
        $this->assertEqualsWithDelta($ab, $ba, 1e-9);
    }

    // Hasil dibulatkan maksimal dua desimal
    public function test_hasil_dibulatkan_maksimal_dua_desimal(): void
    {
        $d = $this->svc->haversineKm(-6.2088, 106.8456, -6.9175, 107.6191);
        $this->assertSame(round($d, 2), $d);
    }

    // ---------- Label jarak: tampilan untuk user ----------

    // Belum ada data koordinat
    public function test_lokasi_tidak_diketahui(): void
    {
        $this->assertSame('Lokasi belum lengkap', $this->svc->distanceLabel(null));
    }

    // Format Indonesia: koma untuk desimal
    public function test_format_indonesia_koma_desimal(): void
    {
        $this->assertSame('116,2 km', $this->svc->distanceLabel(116.239));
    }

    // Format Indonesia: titik untuk pemisah ribuan
    public function test_format_indonesia_titik_pemisah_ribuan(): void
    {
        $this->assertSame('1.234,5 km', $this->svc->distanceLabel(1234.5));
    }

    // Jarak nol tetap ditampilkan
    public function test_jarak_nol_tetap_ditampilkan(): void
    {
        $this->assertSame('0,0 km', $this->svc->distanceLabel(0.0));
    }
}
