<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\PeternakanService;

class DashboardKandangTest extends TestCase
{
    protected PeternakanService $peternakanService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->peternakanService = new PeternakanService();
    }

    public function test_pastikan_class_peternakan_service_tersedia(): void
    {
        $this->assertTrue(class_exists('App\Services\PeternakanService'));
    }

    public function test_pengambilan_detail_kandang_memproses_variabel_dengan_benar(): void
    {
        $dataBarnInput = ['id' => 'no-data', 'name' => 'Kandang Dummy'];

        $hasilAktual = $this->peternakanService->getBarnDetail($dataBarnInput);

        $ekspektasi = [
            'id' => 'no-data',
            'name' => 'Kandang Dummy',
            'flockAge' => '-',
            'totalBirds' => '-',
            'capacity' => '-',
            'breed' => '-',
            'startDate' => '-',
            'location' => '-',
            'photo' => asset('images/barn-placeholder.jpg')
        ];

        $this->assertEquals($ekspektasi, $hasilAktual);
    }

    public function test_pengambilan_data_sensor_mengembalikan_struktur_metrik_akurat(): void
    {
        // Use 'no-data' input to exercise non-DB fallback branch
        $dataBarnInput = ['id' => 'no-data'];

        $hasilAktual = $this->peternakanService->getBarnSensors($dataBarnInput);

        $this->assertIsArray($hasilAktual);
        $this->assertCount(4, $hasilAktual);
        $this->assertEquals('Suhu', $hasilAktual[0]['label']);
        $this->assertEquals('Kelembapan', $hasilAktual[1]['label']);
        $this->assertEquals('Amonia', $hasilAktual[2]['label']);
    }

    public function test_format_tren_sensor_menghasilkan_time_series_valid(): void
    {
        // call with 'no-data' to avoid DB queries and get deterministic series
        $hasilAktual = $this->peternakanService->getBarnSensorTrend('no-data');

        $this->assertIsArray($hasilAktual);
        $this->assertArrayHasKey('labels', $hasilAktual);
        $this->assertArrayHasKey('temperature', $hasilAktual);
    }

    public function test_pengambilan_kpi_kandang_memetakan_kinerja_produksi(): void
    {
        // use 'no-data' to exercise fallback values without DB
        $dataBarnInput = ['id' => 'no-data'];

        $hasilAktual = $this->peternakanService->getBarnKpi($dataBarnInput);

        $this->assertIsArray($hasilAktual);
        $this->assertArrayHasKey('fcr', $hasilAktual);
        $this->assertArrayHasKey('hdp', $hasilAktual);
    }
}
