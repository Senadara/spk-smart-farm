<?php

namespace Tests\Feature;

use App\Models\LoginHistory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

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
        $this->withoutExceptionHandling();
        $userId = 'a1ce85d7-b558-4bd7-8f99-123456789012';

        // ARRANGE: Siapkan data dummy di tabel user (Tabel bawaan schema backend Node.js)
        // Agar relasi foreign key dari login_histories berhasil tersimpan.
        DB::table('user')->insert([
            'id' => $userId,
            'name' => 'QA Tester',
            'email' => 'admin@farm.com',
            'password' => 'hashed_password',
            'role' => 'admin',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);

        Http::fake([
            '*/auth/login' => Http::response([
                'token' => 'token-123',
                'data' => [
                    'id' => $userId,
                    'name' => 'QA Tester',
                    'email' => 'admin@farm.com',
                    'role' => 'admin',
                ]
            ], 200)
        ]);

        $response = $this->withSession(['_token' => 'test-csrf-token'])->post('/login', [
            '_token' => 'test-csrf-token',
            'email' => 'admin@farm.com',
            'password' => 'secret',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals('token-123', session('api_token'));
    }

    public function test_logout_membersihkan_session_dan_redirect_ke_login(): void
    {
        $this->withoutExceptionHandling();
        $userEmail = 'admin@farm.com';
        $userId = 'a1ce85d7-b558-4bd7-8f99-123456789012';

        DB::table('user')->insert([
            'id' => $userId,
            'name' => 'QA Tester',
            'email' => $userEmail,
            'password' => 'hashed_password',
            'role' => 'admin',
            'createdAt' => now(),
            'updatedAt' => now(),
        ]);
        
        LoginHistory::create([
            'userId' => $userId,
            'name' => 'QA Tester',
            'role' => 'admin',
            'email' => $userEmail,
            'ipAddress' => '127.0.0.1',
            'userAgent' => 'Symfony', 
            'createdAt' => now(),
        ]);

        $response = $this->withSession([
            '_token' => 'test-csrf-token',
            'api_token' => 'token-123',
            'user' => ['id' => $userId, 'name' => 'QA Tester', 'email' => $userEmail],
        ])->post('/logout', [
            '_token' => 'test-csrf-token',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertNull(session('api_token'));
        $this->assertNull(session('user'));

        $this->assertDatabaseMissing('login_histories', [
            'email' => $userEmail,
            'ipAddress' => '127.0.0.1',
        ]);
    }
}
