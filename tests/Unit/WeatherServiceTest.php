<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class WeatherServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::setDefaultDriver('array');
        Cache::store('array')->flush();
    }

    public function test_penanganan_kegagalan_api_cuaca_mengembalikan_data_default(): void
    {
        Http::fake([
            '*api.bmkg.go.id*' => Http::response(null, 500)
        ]);

        $weatherService = new WeatherService();
        $hasilAktual = $weatherService->getForecast();

        $this->assertIsArray($hasilAktual);
        $this->assertTrue($hasilAktual['error']);
        $this->assertEquals('Sarirogo', $hasilAktual['location']);
    }

    public function test_penguraian_respons_api_cuaca_memvalidasi_data_sukses(): void
    {
        $dataMock = [
            'data' => [
                [
                    'cuaca' => [
                        [
                            ['local_datetime' => '2026-04-23 10:00:00', 't' => 28, 'hu' => 65],
                            ['local_datetime' => '2026-04-23 11:00:00', 't' => 29, 'hu' => 60]
                        ]
                    ]
                ]
            ]
        ];

        Http::fake([
            '*api.bmkg.go.id*' => Http::response($dataMock, 200)
        ]);

        $weatherService = new WeatherService();
        $hasilAktual = $weatherService->getForecast();

        $this->assertIsArray($hasilAktual);
        $this->assertArrayHasKey('forecast', $hasilAktual);
    }
}
