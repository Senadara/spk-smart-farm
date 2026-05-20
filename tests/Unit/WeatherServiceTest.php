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
        Cache::flush();
    }

    /**
     * Fitur: WeatherService
     * Skenario: Menangani kegagalan API cuaca dan mengembalikan data default
     * Given respons API eksternal gagal (500)
     * When `getForecast()` dipanggil
     * Then fungsi mengembalikan array dengan flag error dan lokasi default
     */
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


    /**
     * Fitur: WeatherService
     * Skenario: Memvalidasi parsing respons API cuaca yang sukses
     * Given respons API BMKG berisi data forecast
     * When `getForecast()` dipanggil
     * Then hasil mengandung kunci 'forecast' dan struktur data yang valid
     */
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
