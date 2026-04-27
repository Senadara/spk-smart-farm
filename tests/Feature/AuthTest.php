<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_halaman_login_ditampilkan_dengan_benar(): void
    {
        $response = $this->get('/login');

        $response->assertViewIs('auth.unimplemented_login_view');
    }

    public function test_kredensial_kosong_mengembalikan_error_validasi_saat_login(): void
    {
        $response = $this->post('/login', [
            'email' => '',
            'password' => ''
        ]);

        $response->assertSessionHasErrors(['email', 'password']);
    }

    public function test_login_sukses_mengarahkan_pengguna_ke_dashboard(): void
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

        $response->assertRedirect('/dashboard-not-implemented');
    }
}
