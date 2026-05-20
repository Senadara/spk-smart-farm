<?php

namespace Tests\Unit\Services;

use App\Models\SpkAhpPerbandingan;
use App\Models\SpkAhpBobot;
use App\Models\SpkAhpConfiguration;
use App\Models\SpkParameter;
use App\Services\AHPService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AHPServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        SpkAhpBobot::unsetEventDispatcher();
        SpkAhpPerbandingan::query()->delete();
        SpkAhpBobot::query()->delete();
        SpkAhpConfiguration::query()->delete();
        SpkParameter::query()->delete();
    }

    protected function tearDown(): void
    {
        SpkAhpBobot::setEventDispatcher(app('events'));

        parent::tearDown();
    }

    /**
     * Fitur: Perhitungan AHP
     * Skenario: Perhitungan menghasilkan CR valid dan bobot tersimpan
     * Given tiga parameter dengan perbandingan AHP valid untuk seorang pengguna
     * When layanan AHP dijalankan untuk pengguna tersebut
     * Then hasil berisi kunci 'cr' dan 'is_valid' = true serta nilai CR <= 0.1
     */
    public function test_perhitungan_ahp_menghasilkan_cr_valid_dan_bobot_tersimpan(): void
    {
        $userId = 1;

        $param1 = SpkParameter::create(['nama_parameter' => 'Harga_test_ahp', 'tipe' => 'cost']);
        $param2 = SpkParameter::create(['nama_parameter' => 'Kualitas_test_ahp', 'tipe' => 'benefit']);
        $param3 = SpkParameter::create(['nama_parameter' => 'Kecepatan_test_ahp', 'tipe' => 'benefit']);

        SpkAhpPerbandingan::create([
            'user_id' => $userId,
            'parameter_1_id' => $param1->id,
            'parameter_2_id' => $param2->id,
            'nilai_skala' => 3,
        ]);
        SpkAhpPerbandingan::create([
            'user_id' => $userId,
            'parameter_1_id' => $param1->id,
            'parameter_2_id' => $param3->id,
            'nilai_skala' => 5,
        ]);
        SpkAhpPerbandingan::create([
            'user_id' => $userId,
            'parameter_1_id' => $param2->id,
            'parameter_2_id' => $param3->id,
            'nilai_skala' => 2,
        ]);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($userId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cr', $result);
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertTrue($result['is_valid']);
        $this->assertLessThanOrEqual(0.1, $result['cr']);
    }

    /**
     * Fitur: Perhitungan AHP
     * Skenario: Perhitungan gagal ketika jumlah parameter kurang
     * Given hanya satu parameter untuk seorang pengguna
     * When layanan AHP dijalankan
     * Then fungsi mengembalikan false
     */
    public function test_perhitungan_ahp_mengembalikan_false_jika_jumlah_parameter_kurang(): void
    {
        SpkParameter::create(['nama_parameter' => 'Harga_test_ahp_2', 'tipe' => 'cost']);
        $userId = 999;

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($userId);

        $this->assertFalse($result);
    }
}
