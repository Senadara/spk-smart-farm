<?php

namespace Tests\Unit;

use Tests\TestCase;

class DataMasterTest extends TestCase
{
    public function test_pastikan_class_data_master_service_tersedia(): void
    {
        $this->assertTrue(class_exists('App\Services\DataMasterService'));
    }

    public function test_pengambilan_data_pengguna_sistem_mengembalikan_struktur_valid(): void
    {
        $dataMasterService = app('App\Services\DataMasterService');

        $hasilAktual = $dataMasterService->getUsersList();

        $ekspektasi = [
            [
                'id' => 'usr-001',
                'nama' => 'Dr. Ahmad Suryadi',
            ]
        ];

        $this->assertEquals($ekspektasi[0]['id'], $hasilAktual[0]['id']);
    }

    public function test_pengambilan_data_blok_kebun_mengembalikan_struktur_valid(): void
    {
        $dataMasterService = app('App\Services\DataMasterService');

        $hasilAktual = $dataMasterService->getBlokKebunList();

        $ekspektasi = 'ub-001';

        $this->assertEquals($ekspektasi, $hasilAktual[0]['id']);
    }

    public function test_pengambilan_kategori_jenis_budidaya_mengembalikan_array_valid(): void
    {
        $dataMasterService = app('App\Services\DataMasterService');

        $hasilAktual = $dataMasterService->getJenisBudidayaList();

        $ekspektasi = [
            'jb-001' => 'Melon',
            'jb-002' => 'Pakcoy',
        ];

        $this->assertEquals($ekspektasi, $hasilAktual);
    }
}
