<?php

namespace Tests\Unit\TDD;

use App\Services\Fuzzy\InputResolver;
use App\Services\LivestockMasterConfigService;
use App\Services\PeternakanService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

// Tujuan: memastikan pembacaan data sensor (suhu, amonia, dll) diterjemahkan ke status normal/warning/bahaya, lengkap dengan analisis tren naik-turun dan pengisian data kosong.

class PeternakanSensorTest extends TestCase
{
    private PeternakanService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new PeternakanService(
            $this->createMock(LivestockMasterConfigService::class),
            $this->createMock(InputResolver::class),
        );
    }

    private function invoke(string $method, array $args)
    {
        $ref = new ReflectionMethod(PeternakanService::class, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->svc, $args);
    }

    // ---------- Klasifikasi status sensor ----------

    public static function sensorStatusProvider(): array
    {
        return [
            'nilai nol — tetap normal'          => [ 0.0, 20.0, 30.0, 'normal'],
            'nilai negatif — tetap normal'      => [-5.0, 20.0, 30.0, 'normal'],
            'di dalam rentang — normal'         => [25.0, 20.0, 30.0, 'normal'],
            'tepat di batas bawah — normal'     => [20.0, 20.0, 30.0, 'normal'],
            'sedikit di bawah batas — warning'  => [19.0, 20.0, 30.0, 'warning'],
            'jauh di bawah batas — berbahaya'   => [10.0, 20.0, 30.0, 'danger'],
            'sedikit di atas batas — warning'   => [32.0, 20.0, 30.0, 'warning'],
            'jauh di atas batas — berbahaya'    => [40.0, 20.0, 30.0, 'danger'],
            'selisih sedikit — warning'         => [ 9.0, 10.0, 30.0, 'warning'],
            'tidak ada batas — normal'          => [25.0, null, null, 'normal'],
        ];
    }

    #[DataProvider('sensorStatusProvider')]
    public function test_klasifikasi_status_sensor(float $value, ?float $min, ?float $max, string $expected): void
    {
        $this->assertSame($expected, $this->invoke('evaluateSensorStatus', [$value, $min, $max]));
    }

    // ---------- Status terburuk ----------

    public static function worstStatusProvider(): array
    {
        return [
            'ada yang berbahaya — langsung berbahaya'  => [['normal', 'warning', 'danger'], 'danger'],
            'ada warning tanpa bahaya — jadi warning'  => [['normal', 'warning', 'normal'], 'warning'],
            'semua normal — tetap normal'              => [['normal', 'normal'], 'normal'],
            'array kosong — default normal'             => [[], 'normal'],
        ];
    }

    #[DataProvider('worstStatusProvider')]
    public function test_status_keseluruhan_mengikuti_yang_terburuk(array $statuses, string $expected): void
    {
        $this->assertSame($expected, $this->invoke('worstStatus', $statuses));
    }

    // ---------- Ambang batas per kode sensor ----------

    public static function thresholdForProvider(): array
    {
        return [
            'kode cocok — ambil langsung' => [
                ['TEMP' => ['min' => 18.0, 'max' => 30.0]], 'TEMP',
                ['min' => 18.0, 'max' => 30.0],
            ],
            'kode gas lain — null semua' => [
                ['AMMON' => ['min' => 0.0, 'max' => 25.0]], 'GAS_XYZ',
                ['min' => null, 'max' => null],
            ],
            'kode tidak dikenal — null semua' => [
                ['TEMP' => ['min' => 18.0, 'max' => 30.0]], 'UNKNOWN',
                ['min' => null, 'max' => null],
            ],
        ];
    }

    #[DataProvider('thresholdForProvider')]
    public function test_pencarian_ambang_batas_sensor(array $thresholds, string $code, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('thresholdFor', [$thresholds, $code]));
    }

    // ---------- Interpolasi data null ----------

    public static function interpolateProvider(): array
    {
        return [
            'null di tengah — isi dari data sebelumnya' => [[1, null, 3, null], [1, 1, 3, 3]],
            'null di awal — isi nol'                    => [[null, null, 5], [0, 0, 5]],
            'null di akhir — isi dari data sebelumnya'   => [[null, 2, null], [0, 2, 2]],
            'tanpa null — tidak berubah'                 => [[1, 2, 3], [1, 2, 3]],
            'array kosong — tetap kosong'                => [[], []],
        ];
    }

    #[DataProvider('interpolateProvider')]
    public function test_mengisi_data_kosong_dari_nilai_sebelumnya(array $input, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('interpolateArray', [$input]));
    }

    // ---------- Analisis tren ----------

    public static function calcTrendProvider(): array
    {
        return [
            'tidak berubah — stable'         => [0.0, 0.0, 'higher_is_better',
                ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral']],
            'naik, semakin besar baik — positif' => [10.0, 5.0, 'higher_is_better',
                ['direction' => 'up', 'value' => '5', 'status' => 'positive']],
            'turun, semakin besar baik — warning' => [5.0, 10.0, 'higher_is_better',
                ['direction' => 'down', 'value' => '5', 'status' => 'warning']],
            'turun, semakin kecil baik — positif'  => [5.0, 10.0, 'lower_is_better',
                ['direction' => 'down', 'value' => '5', 'status' => 'positive']],
            'naik, semakin kecil baik — warning'   => [10.0, 5.0, 'lower_is_better',
                ['direction' => 'up', 'value' => '5', 'status' => 'warning']],
            'beda tipis — dianggap stabil'    => [5.0, 5.0, 'higher_is_better',
                ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral']],
            'arah tidak penting, beda besar — warning' => [20.0, 5.0, 'apa_saja',
                ['direction' => 'up', 'value' => '15', 'status' => 'warning']],
            'arah tidak penting, beda kecil — biasa'   => [8.0, 5.0, 'apa_saja',
                ['direction' => 'up', 'value' => '3', 'status' => 'neutral']],
        ];
    }

    #[DataProvider('calcTrendProvider')]
    public function test_analisis_tren_naik_turun_nilai(float $current, float $previous, string $mode, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('calcTrend', [$current, $previous, $mode]));
    }

    // ---------- Tren mortalitas ----------

    public static function mortalityTrendProvider(): array
    {
        return [
            'sama dengan bulan lalu — stabil' => [5, 5, ['direction' => 'stable', 'value' => 'Stable', 'status' => 'neutral']],
            'naik dari bulan lalu — warning'  => [10, 5, ['direction' => 'up', 'value' => '5 ekor', 'status' => 'warning']],
            'turun dari bulan lalu — positif' => [3, 8, ['direction' => 'down', 'value' => '5 ekor', 'status' => 'positive']],
        ];
    }

    #[DataProvider('mortalityTrendProvider')]
    public function test_tren_kematian_ayam_bulanan(int $bulanIni, int $bulanLalu, array $expected): void
    {
        $this->assertSame($expected, $this->invoke('calcMortalityTrend', [$bulanIni, $bulanLalu]));
    }
}
