<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\AHPService;
use App\Models\SpkParameter;
use App\Models\SpkAhpPerbandingan;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AHPServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ahp_calculation_returns_valid_cr()
    {
        // Create parameters
        $param1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $param2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);
        $param3 = SpkParameter::create(['nama_parameter' => 'Kecepatan', 'tipe' => 'benefit']);

        // Set comparisons (1 vs 2, 1 vs 3, 2 vs 3)
        // Let's make a moderately consistent matrix
        $userId = 1;
        
        SpkAhpPerbandingan::create([
            'user_id' => $userId,
            'parameter_1_id' => $param1->id,
            'parameter_2_id' => $param2->id,
            'nilai_skala' => 3
        ]);
        SpkAhpPerbandingan::create([
            'user_id' => $userId,
            'parameter_1_id' => $param1->id,
            'parameter_2_id' => $param3->id,
            'nilai_skala' => 5
        ]);
        SpkAhpPerbandingan::create([
            'user_id' => $userId,
            'parameter_1_id' => $param2->id,
            'parameter_2_id' => $param3->id,
            'nilai_skala' => 2
        ]);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights($userId);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('cr', $result);
        $this->assertArrayHasKey('is_valid', $result);
        $this->assertTrue($result['is_valid']);
        $this->assertLessThanOrEqual(0.1, $result['cr']);
    }
}
