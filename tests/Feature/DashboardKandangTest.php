<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mockery;
use App\Services\PeternakanService;

class DashboardKandangTest extends TestCase
{
    public function test_data_sensor_fuzzy_diteruskan_ke_view_dashboard_peternakan(): void
    {
        $mock = Mockery::mock(PeternakanService::class);
        $mock->shouldReceive('getKpiMetrics')->andReturn([]);
        $mock->shouldReceive('getChartData')->andReturn([
            'labels' => ['Sen', 'Sel', 'Rab'],
            'hdp' => [93, 94, 95],
            'fcr' => [1.51, 1.48, 1.45],
        ]);
        $mock->shouldReceive('getBarnEnvironment')->andReturn([
            'barns' => [
                [
                    'id' => 1,
                    'name' => 'Barn A',
                    'status' => 'normal',
                    'temp' => 25,
                    'sensors' => [
                        ['label' => 'Suhu', 'percent' => 75, 'status' => 'normal', 'statusLabel' => 'Normal'],
                        ['label' => 'Kelembapan', 'percent' => 60, 'status' => 'normal', 'statusLabel' => 'Normal'],
                        ['label' => 'Amonia', 'percent' => 20, 'status' => 'warning', 'statusLabel' => 'Waspada'],
                        ['label' => 'Cahaya', 'percent' => 80, 'status' => 'normal', 'statusLabel' => 'Normal'],
                    ],
                    'summary' => ['avg_temp' => '25°C', 'humidity' => '60%', 'ammonia' => '10ppm', 'ammonia_ok' => true, 'lux' => '100 lx']
                ]
            ]
        ]);
        $mock->shouldReceive('getProduktivitasData')->andReturn([
            'indicators' => [
                ['label' => 'HDP', 'value' => '94.5%', 'color' => 'emerald'],
                ['label' => 'FCR', 'value' => '1.45', 'color' => 'amber'],
                ['label' => 'Mortalitas', 'value' => '0.02%', 'color' => 'emerald'],
            ],
            'spider' => [
                'labels' => ['HDP', 'FCR', 'Mortalitas'],
                'values' => [94.5, 1.45, 0.02],
            ],
        ]);
        $mock->shouldReceive('getSpkResults')->andReturn([
            'lingkungan' => ['status' => 'Baik', 'title' => 'Lingkungan Stabil', 'description' => 'Kondisi kandang stabil', 'statusColor' => 'emerald'],
            'produktivitas' => ['status' => 'Baik', 'title' => 'Produktivitas Stabil', 'description' => 'Performa produksi stabil', 'statusColor' => 'emerald'],
            'gabungan' => ['status' => 'Optimal', 'title' => 'Evaluasi Baik', 'description' => 'Kombinasi data menunjukkan performa baik', 'statusColor' => 'emerald', 'link' => '#'],
        ]);
        $mock->shouldReceive('getProductionLog')->andReturn([]);
        $this->app->instance(PeternakanService::class, $mock);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/peternakan');

        $response->assertStatus(200);
        $response->assertViewHasAll(['kpiMetrics', 'chartData', 'barnEnvironment', 'fuzzySensors', 'produktivitas', 'spkResults', 'productionLog']);
    }

    public function test_detail_kandang_menggunakan_barn_fallback_dan_tetap_merender_view(): void
    {
        $this->withoutExceptionHandling();
        $mock = Mockery::mock(PeternakanService::class);
        $mock->shouldReceive('getBarnEnvironment')->andReturn([
            'barns' => [
                [
                    'id' => 1,
                    'name' => 'Barn A',
                    'status' => 'normal',
                    'temp' => 25,
                    'sensors' => [['status' => 'normal'], ['status' => 'normal'], ['status' => 'normal'], ['status' => 'normal']],
                    'summary' => ['avg_temp' => '25°C', 'humidity' => '60%', 'ammonia' => '10ppm', 'ammonia_ok' => true, 'lux' => '100 lx']
                ]
            ]
        ]);
        $mock->shouldReceive('getBarnIotDevices')->andReturn([['deviceCode' => 'D1', 'deviceName' => 'Sensor 1', 'status' => 'online']]);
        $mock->shouldReceive('getBarnDetail')->andReturn([
            'id' => 1,
            'name' => 'Kandang Mock',
            'status' => 'normal',
            'temp' => 25,
            'breed' => 'Layer',
            'location' => 'Satu',
            'flockAge' => 12,
            'totalBirds' => 1200,
            'capacity' => 2000,
        ]);
        $mock->shouldReceive('getBarnSensors')->andReturn([['label' => 'Suhu', 'value' => 25], ['label' => 'Kelembapan', 'value' => 60]]);
        $mock->shouldReceive('getBarnSensorTrend')->andReturn(['labels' => ['Sen', 'Sel'], 'temperature' => [25, 26], 'humidity' => [60, 61], 'ammonia' => [10, 11], 'light' => [100, 120]]);
        $mock->shouldReceive('getBarnKpi')->andReturn(['hdp' => 94.5, 'hhep' => 92.1, 'feedIntake' => 115, 'fcr' => 1.45, 'mortalitas' => 0.02, 'afkir' => 0.5]);
        $mock->shouldReceive('getBarnProductionLog')->andReturn([]);
        $mock->shouldReceive('getBarnSpkMessages')->andReturn([]);
        $mock->shouldReceive('getBarnActivityLog')->andReturn([]);
        $mock->shouldReceive('getProductivityTrend')->andReturn(['labels' => ['Sen', 'Sel'], 'hdp' => [94.5, 94.8], 'hhep' => [92.1, 92.4], 'fcr' => [1.45, 1.44], 'feedIntake' => [115, 117], 'mortality' => [0.02, 0.03]]);
        $mock->shouldReceive('getEggQuality')->andReturn(['small' => 10, 'medium' => 40, 'large' => 35, 'xl' => 15, 'brokenStatus' => 'normal', 'brokenRate' => 1.2, 'dirtyStatus' => 'normal', 'dirtyRate' => 0.8]);
        $this->app->instance(PeternakanService::class, $mock);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/peternakan/99999');

        $response->assertStatus(200);
        $response->assertViewHasAll(['barn', 'sensors', 'sensorTrend', 'kpi', 'productionLog', 'iotDevice', 'spkMessages', 'activityLog', 'productivityTrend', 'eggQuality']);
    }

    public function test_data_perangkat_iot_diterjemahkan_dengan_benar_ke_view(): void
    {
        $this->withoutExceptionHandling();
        $mock = Mockery::mock(PeternakanService::class);
        $mock->shouldReceive('getBarnEnvironment')->andReturn(['barns' => [['id' => 1, 'name' => 'Barn A', 'status' => 'normal', 'temp' => 25, 'sensors' => [], 'summary' => ['avg_temp' => '25°C', 'humidity' => '60%', 'ammonia' => '10ppm', 'ammonia_ok' => true, 'lux' => '100 lx']]]]);
        $mock->shouldReceive('getBarnIotDevices')->andReturn([['deviceCode' => 'D1', 'deviceName' => 'Sensor1', 'status' => 'online']]);
        $mock->shouldReceive('getBarnDetail')->andReturn([
            'id' => 1,
            'name' => 'Kandang Mock',
            'status' => 'normal',
            'temp' => 25,
            'breed' => 'Layer',
            'location' => 'Satu',
            'flockAge' => 12,
            'totalBirds' => 1200,
            'capacity' => 2000,
        ]);
        $mock->shouldReceive('getBarnSensors')->andReturn([['label' => 'Suhu', 'value' => 25], ['label' => 'Kelembapan', 'value' => 60]]);
        $mock->shouldReceive('getBarnSensorTrend')->andReturn(['labels' => ['Sen', 'Sel'], 'temperature' => [25, 26], 'humidity' => [60, 61], 'ammonia' => [10, 11], 'light' => [100, 120]]);
        $mock->shouldReceive('getBarnKpi')->andReturn(['hdp' => 94.5, 'hhep' => 92.1, 'feedIntake' => 115, 'fcr' => 1.45, 'mortalitas' => 0.02, 'afkir' => 0.5]);
        $mock->shouldReceive('getBarnProductionLog')->andReturn([]);
        $mock->shouldReceive('getBarnSpkMessages')->andReturn([]);
        $mock->shouldReceive('getBarnActivityLog')->andReturn([]);
        $mock->shouldReceive('getProductivityTrend')->andReturn(['labels' => ['Sen', 'Sel'], 'hdp' => [94.5, 94.8], 'hhep' => [92.1, 92.4], 'fcr' => [1.45, 1.44], 'feedIntake' => [115, 117], 'mortality' => [0.02, 0.03]]);
        $mock->shouldReceive('getEggQuality')->andReturn(['small' => 10, 'medium' => 40, 'large' => 35, 'xl' => 15, 'brokenStatus' => 'normal', 'brokenRate' => 1.2, 'dirtyStatus' => 'normal', 'dirtyRate' => 0.8]);
        $this->app->instance(PeternakanService::class, $mock);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/peternakan/1');

        $response->assertStatus(200);
        $response->assertViewHas('iotDevice');
    }
}
