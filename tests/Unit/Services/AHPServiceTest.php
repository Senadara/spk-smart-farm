<?php

namespace Tests\Unit\Services;

use App\Models\SpkAhpPerbandingan;
use App\Models\SpkParameter;
use App\Services\AHPService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AHPServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['spk_ahp_bobots', 'spk_ahp_perbandingans', 'spk_parameters', 'spk_rankings'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('spk_parameters', function (Blueprint $table) {
            $table->id();
            $table->string('nama_parameter');
            $table->string('tipe');
            $table->timestamps();
        });

        Schema::create('spk_ahp_perbandingans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('parameter_1_id');
            $table->unsignedBigInteger('parameter_2_id');
            $table->float('nilai_skala');
            $table->timestamps();
        });

        Schema::create('spk_ahp_bobots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('parameter_id');
            $table->float('bobot');
            $table->boolean('is_valid');
            $table->timestamps();
        });

        Schema::create('spk_rankings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_valid')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Given three parameters and a consistent comparison matrix
     * When the service calculates AHP weights
     * Then it returns a valid CR and saves the computed weights
     */
    public function test_ahp_calculation_returns_valid_cr(): void
    {
        $userId = 1;

        $param1 = SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);
        $param2 = SpkParameter::create(['nama_parameter' => 'Kualitas', 'tipe' => 'benefit']);
        $param3 = SpkParameter::create(['nama_parameter' => 'Kecepatan', 'tipe' => 'benefit']);

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
        $this->assertCount(3, $result['weights']);
    }

    /**
     * Given only one parameter
     * When the service calculates AHP weights
     * Then it returns false because AHP requires at least two parameters
     */
    public function test_ahp_calculation_returns_false_when_parameter_count_is_too_small(): void
    {
        SpkParameter::create(['nama_parameter' => 'Harga', 'tipe' => 'cost']);

        $service = new AHPService();
        $result = $service->calculateAndSaveWeights(1);

        $this->assertFalse($result);
    }
}
