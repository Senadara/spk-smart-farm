<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use App\Services\AuthService;
use Mockery;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_halaman_login_ditampilkan_dengan_benar(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.login');
    }

    public function test_kredensial_kosong_mengembalikan_error_validasi_saat_login(): void
    {
        $response = $this->withSession(['_token' => 'test-csrf-token'])->post('/login', [
            '_token' => 'test-csrf-token',
            'email' => '',
            'password' => '',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_berhasil_menyimpan_session_dan_redirect_ke_dashboard(): void
    {
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('login')
            ->once()
            ->with('admin@farm.com', 'secret')
            ->andReturn([
                'token' => 'token-123',
                'data' => [
                    'id' => 1,
                    'name' => 'QA Tester',
                    'email' => 'admin@farm.com',
                    'role' => 'admin',
                ],
            ]);
        $authService->shouldReceive('storeSession')->once();
        $this->app->instance(AuthService::class, $authService);

        $loginHistory = Mockery::mock('alias:' . LoginHistory::class);
        $loginHistory->shouldReceive('updateOrCreate')->once()->andReturnTrue();

        $response = $this->withSession(['_token' => 'test-csrf-token'])->post('/login', [
            '_token' => 'test-csrf-token',
            'email' => 'admin@farm.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect(route('dashboard'));
    }

    public function test_logout_membersihkan_session_dan_redirect_ke_login(): void
    {
        $authService = Mockery::mock(AuthService::class);
        $authService->shouldReceive('clearSession')->once();
        $this->app->instance(AuthService::class, $authService);

        $historyQuery = Mockery::mock();
        $historyQuery->shouldReceive('where')->andReturnSelf();
        $historyQuery->shouldReceive('where')->andReturnSelf();
        $historyQuery->shouldReceive('delete')->once()->andReturn(1);

        $loginHistory = Mockery::mock('alias:' . LoginHistory::class);
        $loginHistory->shouldReceive('where')->andReturn($historyQuery);

        $response = $this->withSession([
            '_token' => 'test-csrf-token',
            'api_token' => 'token-123',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'admin@farm.com'],
        ])->post('/logout', [
                    '_token' => 'test-csrf-token',
                ]);

        $response->assertRedirect(route('login'));
    }
}
