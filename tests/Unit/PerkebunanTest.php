<?php

namespace Tests\Unit;

use Tests\TestCase;

class PerkebunanTest extends TestCase
{
    public function test_pastikan_class_perkebunan_service_tersedia(): void
    {
        $this->assertTrue(class_exists('App\Services\PerkebunanService'));
    }

    public function test_kalkulasi_statistik_kebun_mengembalikan_struktur_data_valid(): void
    {
        $perkebunanService = app('App\Services\PerkebunanService');
        
        $hasilAktual = $perkebunanService->getKebunStats();
        
        $ekspektasi = [
            'total_blok'     => 8,
            'aktif'          => 5,
            'nonaktif'       => 3,
            'total_tanaman'  => 480,
        ];

        $this->assertEquals($ekspektasi, $hasilAktual);
    }

    public function test_pengambilan_data_evaluasi_spk_terbaru_mengembalikan_struktur_valid(): void
    {
        $perkebunanService = app('App\Services\PerkebunanService');
        
        $hasilAktual = $perkebunanService->getEvaluasiTerbaru();
        
        $ekspektasi = [
            'nama_sesi'         => 'Evaluasi Produktivitas Siklus Maret 2026',
            'tipe'              => 'produktivitas',
            'status'            => 'selesai',
            'tanggal'           => '2026-03-01',
            'jumlah_alternatif' => 5,
            'dinilai_oleh'      => 'Dr. Ahmad (Pakar)',
        ];

        $this->assertEquals($ekspektasi, $hasilAktual);
    }

    public function test_pengambilan_peringkat_kebun_mengembalikan_urutan_spk_yang_tepat(): void
    {
        $perkebunanService = app('App\Services\PerkebunanService');
        
        $hasilAktual = $perkebunanService->getRankingTerbaru();
        
        $ekspektasi = 'Evaluasi Produktivitas Siklus Maret 2026';

        $this->assertEquals($ekspektasi, $hasilAktual['sesi_nama']);
    }

    public function test_pemrosesan_data_sensor_iot_terkalibrasi_menghasilkan_mapping_valid(): void
    {
        $perkebunanService = app('App\Services\PerkebunanService');
        
        $hasilAktual = $perkebunanService->getSensorData();
        
        $ekspektasi = 'Greenhouse A';

        $this->assertEquals($ekspektasi, $hasilAktual[0]['name']);
    }

    public function test_kalkulasi_ringkasan_peringatan_kebun_mengembalikan_kpi_alert_valid(): void
    {
        $perkebunanService = app('App\Services\PerkebunanService');
        
        $hasilAktual = $perkebunanService->getAlertSummary();
        
        $ekspektasi = [
            'total'    => 5,
            'critical' => 1,
            'warning'  => 3,
            'info'     => 1,
        ];

        $this->assertEquals($ekspektasi, $hasilAktual);
    }

    public function test_pengambilan_riwayat_notifikasi_kritis_berjalan_sesuai_urutan(): void
    {
        $perkebunanService = app('App\Services\PerkebunanService');
        
        $hasilAktual = $perkebunanService->getRecentAlerts();
        
        $ekspektasi = 'alert-001';

        $this->assertEquals($ekspektasi, $hasilAktual[0]['id']);
    }
}
