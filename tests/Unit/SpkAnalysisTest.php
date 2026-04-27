<?php

namespace Tests\Unit;

use Tests\TestCase;

class SpkAnalysisTest extends TestCase
{
    public function test_pastikan_class_spk_service_tersedia(): void
    {
        $this->assertTrue(class_exists('App\Services\SpkService'));
    }

    public function test_pengambilan_data_peringkat_ahp_saw_mengkalkulasi_data_riwayat_dengan_benar(): void
    {
        $spkService = app('App\Services\SpkService');

        $parameterHistori = ['komoditas' => 'petelur', 'lokasi' => 'barn_a'];

        $hasilAktual = $spkService->getAhpSawRanking($parameterHistori);

        $ekspektasi = [
            ['rank' => 1, 'name' => 'Vendor A', 'score' => 0.89, 'kategori' => 'Tinggi'],
            ['rank' => 2, 'name' => 'Vendor B', 'score' => 0.76, 'kategori' => 'Menengah'],
        ];

        $this->assertEquals($ekspektasi, $hasilAktual, 'Logika AHP SAW untuk SPK Analysis belum dikonstruksikan dengan sempurna, data meleset dari struktur dashboard.');
    }

    public function test_perhitungan_logika_fuzzy_menangani_data_sensor_dan_memberikan_status_yang_tepat(): void
    {
        $spkService = app('App\Services\SpkService');

        $historyDataAcak = ['id' => 1, 'kondisi' => 'kritis'];

        $hasilAktual = $spkService->getFuzzyStatus($historyDataAcak);

        $ekspektasi = [
            'status' => 'Bahaya', 
            'confidence' => 88.5, 
            'detail_komputasi' => ['suhu' => 'Tinggi', 'amonia' => 'Normal']
        ];

        $this->assertEquals($ekspektasi, $hasilAktual, 'Logika SpkService->getFuzzyStatus() belum merender hasil fuzzy yang terkomputasi lengkap.');
    }
}
