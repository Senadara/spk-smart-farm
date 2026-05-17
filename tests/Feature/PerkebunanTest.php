<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PerkebunanTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('session.driver', 'array');
        $this->app['config']->set('cache.default', 'array');
        \Illuminate\Support\Facades\Cache::store('array')->flush();
    }

    public function test_halaman_dashboard_perkebunan_ditampilkan_dengan_benar(): void
    {
        Http::fake([
            '*api.bmkg.go.id*' => Http::response(null, 500),
        ]);

        $this->withSession([
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com'],
        ]);
        $response = $this->get('/perkebunan');

        $response->assertStatus(200);
        $response->assertViewHasAll(['user', 'weather', 'kebunStats', 'evaluasiTerbaru', 'rankingTerbaru', 'sensorData', 'alertSummary', 'recentAlerts']);
    }
}
