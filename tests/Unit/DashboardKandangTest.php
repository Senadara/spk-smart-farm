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
        $dataBarnInput = ['id' => 1];
        
        $hasilAktual = $this->peternakanService->getBarnSensors($dataBarnInput);
        
        $this->assertIsArray($hasilAktual);
        $this->assertArrayHasKey('suhu', $hasilAktual);
        $this->assertArrayHasKey('kelembaban', $hasilAktual);
        $this->assertArrayHasKey('amonia', $hasilAktual);
    }

    public function test_format_tren_sensor_menghasilkan_time_series_valid(): void
    {
        $hasilAktual = $this->peternakanService->getBarnSensorTrend(1);
        
        $this->assertIsArray($hasilAktual);
        $this->assertArrayHasKey('labels', $hasilAktual);
        $this->assertArrayHasKey('datasets', $hasilAktual);
    }

    public function test_pengambilan_kpi_kandang_memetakan_kinerja_produksi(): void
    {
        $dataBarnInput = ['id' => 1];
        
        $hasilAktual = $this->peternakanService->getBarnKpi($dataBarnInput);
        
        $this->assertIsArray($hasilAktual);
        $this->assertArrayHasKey('kematian', $hasilAktual);
        $this->assertArrayHasKey('fcr', $hasilAktual);
    }
}
