<?php

namespace Tests\Unit\Services;

use App\Models\SpkAhpPerbandingan;
use App\Models\SpkAhpBobot;
use App\Models\SpkParameter;
use App\Services\AHPService;
use Mockery;
use PHPUnit\Framework\TestCase;

/**
 * CATATAN QA:
 * Test ini diubah dari Laravel TestCase + DatabaseTransactions menjadi Pure Unit Test
 * dengan Mockery untuk menghindari dependency pada real database connection.
 * 
 * Alasan: Unit test seharusnya tidak memerlukan koneksi database nyata.
 * Test yang memerlukan database nyata sebaiknya dipindah ke Feature Test.
 * 
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class AHPServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        Mockery::close();
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
        // Arrange
        $userId = 1;

        $param1 = (object) ['id' => 1, 'nama_parameter' => 'Harga_test_ahp', 'tipe' => 'cost'];
        $param2 = (object) ['id' => 2, 'nama_parameter' => 'Kualitas_test_ahp', 'tipe' => 'benefit'];
        $param3 = (object) ['id' => 3, 'nama_parameter' => 'Kecepatan_test_ahp', 'tipe' => 'benefit'];

        $parameters = collect([$param1, $param2, $param3]);

        // Mock SpkParameter::all()
        $parameterModel = Mockery::mock('alias:App\Models\SpkParameter');
        $parameterModel->shouldReceive('all')->andReturn($parameters);

        // Mock SpkAhpPerbandingan::where()->get()
        $perbandinganQuery = Mockery::mock();
        $perbandinganQuery->shouldReceive('get')->andReturn(collect([
            (object) ['parameter_1_id' => 1, 'parameter_2_id' => 2, 'nilai_skala' => 3],
            (object) ['parameter_1_id' => 1, 'parameter_2_id' => 3, 'nilai_skala' => 5],
            (object) ['parameter_1_id' => 2, 'parameter_2_id' => 3, 'nilai_skala' => 2],
        ]));

        $perbandinganModel = Mockery::mock('alias:App\Models\SpkAhpPerbandingan');
        $perbandinganModel->shouldReceive('where')->with('user_id', $userId)->andReturn($perbandinganQuery);

        // Mock SpkAhpBobot::updateOrCreate()
        $bobotModel = Mockery::mock('alias:App\Models\SpkAhpBobot');
        $bobotModel->shouldReceive('updateOrCreate')
            ->times(3) // Called 3 times for 3 parameters
            ->andReturn(true);

        // Mock SpkAhpConfiguration::where()->max() and create()
        $configQuery = Mockery::mock();
        $configQuery->shouldReceive('max')->with('version')->andReturn(0);

        $configModel = Mockery::mock('alias:App\Models\SpkAhpConfiguration');
        $configModel->shouldReceive('where')->with('user_id', $userId)->andReturn($configQuery);
        $configModel->shouldReceive('create')->once()->andReturn(true);

        // Act
        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($userId);

        // Assert
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
        $userId = 999;

        // Arrange: Mock Parameter model - return only 1 parameter (less than 2)
        $param1 = (object) ['id' => 1, 'nama_parameter' => 'Harga_test_ahp_2', 'tipe' => 'cost'];

        $parameterModel = Mockery::mock('alias:App\Models\SpkParameter');
        $parameterModel->shouldReceive('all')->andReturn(collect([$param1]));

        // Act
        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($userId);

        // Assert
        $this->assertFalse($result);
    }
}
