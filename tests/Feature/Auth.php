<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Auth extends TestCase
{
    public function test_user_bisa_melihat_halaman_login(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('halaman.yang.belum.dibuat');
    }

    public function test_user_tidak_bisa_login_dengan_data_kosong(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => ''
        ]);

        $response->assertStatus(200);
    }

    public function test_user_berhasil_login_dan_history_dicatat(): void
    {
        Http::fake([
            '*/auth/login*' => Http::response([
                'token' => 'fake-token-123',
                'data' => [
                    'id' => 'uuid-user-1',
                    'name' => 'QA Tester',
                    'email' => 'qa@farm.com',
                    'role' => 'admin'
                ]
            ], 200)
        ]);

        $response = $this->post('/login', [
            'email' => 'qa@farm.com',
            'password' => 'secret123'
        ]);

        $response->assertRedirect('/halaman-rahasia');
        $response->assertSessionHas('success', 'Selamat datang, QA Tester!');

        $this->assertSessionHas('api_token', 'fake-token-123');
        $this->assertEquals('qa@farm.com', session('user.email'));

        $this->assertDatabaseHas('login_histories', [
            'email' => 'qa@farm.com',
            'role' => 'admin',
            'ipAddress' => '127.0.0.1'
        ]);
    }

    public function test_user_gagal_login_karena_kredensial_salah(): void
    {
        Http::fake([
            '*/auth/login*' => Http::response([
                'success' => false,
                'message' => 'Email atau password salah'
            ], 400)
        ]);

        $response = $this->post('/login', [
            'email' => 'salah@farm.com',
            'password' => 'wrongpass'
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Email atau password salah');

        $this->assertDatabaseMissing('login_histories', [
            'email' => 'salah@farm.com'
        ]);
    }

    public function test_user_berhasil_logout_dan_history_dihapus(): void
    {
        session([
            'api_token' => 'dummy-token',
            'user' => ['email' => 'qa@farm.com', 'name' => 'QA Tester'],
            'logged_in_at' => now()->format('d M Y, H:i')
        ]);

        LoginHistory::updateOrCreate(
            [
                'email' => 'qa@farm.com',
                'ipAddress' => '127.0.0.1',
                'userAgent' => 'Symfony',
            ],
            [
                'name' => 'QA Tester',
                'role' => 'admin',
                'createdAt' => now()
            ]
        );

        $this->assertDatabaseHas('login_histories', ['email' => 'qa@farm.com']);

        $response = $this->post('/logout', [], [
            'User-Agent' => 'Symfony'
        ]);

        $response->assertRedirect(route('login'));

        $this->assertDatabaseMissing('login_histories', [
            'email' => 'qa@farm.com',
            'ipAddress' => '127.0.0.1'
        ]);
    }
}
