<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use Mockery;
use Tests\TestCase;

/**
 * @runInSeparateProcess
 * @preserveGlobalState disabled
 */
class DashboardProfilSettingsTest extends TestCase
{
    private function authSession(): array
    {
        return [
            'api_token' => 'fake-token',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ];
    }

    /**
     * Feature: Halaman Dashboard
     * Scenario: Menampilkan halaman dashboard dengan data pengguna
     * Given pengguna login sebagai admin
     * When halaman '/dashboard' diakses
     * Then status 200 dan view dashboard menampilkan data user
     */
    public function test_dashboard_halaman_utama_ditampilkan_dengan_data_user(): void
    {
        $response = $this->withSession($this->authSession())->get('/dashboard');

        $response->assertStatus(200);
        $response->assertViewIs('dashboard.index');
        $response->assertViewHas('user');
    }

    /**
     * Feature: Halaman Profil
     * Scenario: Menampilkan histori login pengguna
     * Given data histori login tersedia untuk email pengguna
     * When halaman '/profil' diakses
     * Then status 200 dan view profil menampilkan histori login
     */
    public function test_halaman_profil_menampilkan_histori_login_pengguna(): void
    {
        $historyQuery = Mockery::mock();
        $historyQuery->shouldReceive('orderByDesc')->with('createdAt')->andReturnSelf();
        $historyQuery->shouldReceive('take')->with(10)->andReturnSelf();
        $historyQuery->shouldReceive('get')->andReturn(collect([
            (object) [
                'email' => 'qa@farm.com',
                'createdAt' => now(),
                'ipAddress' => '127.0.0.1',
                'userAgent' => 'Mozilla/5.0',
            ],
        ]));

        $loginHistory = Mockery::mock('alias:' . LoginHistory::class);
        $loginHistory->shouldReceive('where')->with('email', 'qa@farm.com')->andReturn($historyQuery);

        $response = $this->withSession($this->authSession())->get('/profil');

        $response->assertStatus(200);
        $response->assertViewIs('profile.show');
        $response->assertViewHasAll(['user', 'loginHistories']);
    }

    /**
     * Feature: Halaman Settings
     * Scenario: Menampilkan halaman pengaturan dengan benar
     * Given pengguna login sebagai admin
     * When halaman '/settings' diakses
     * Then status 200 dan view settings ditampilkan
     */
    public function test_halaman_settings_hub_ditampilkan_dengan_benar(): void
    {
        $response = $this->withSession($this->authSession())->get('/settings');

        $response->assertStatus(200);
        $response->assertViewIs('settings.index');
    }
}