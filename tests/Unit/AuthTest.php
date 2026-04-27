<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AuthService;
use App\Services\ApiService;
use App\Exceptions\ApiException;
use Mockery;

class AuthTest extends TestCase
{
    public function test_pastikan_class_auth_service_tersedia(): void
    {
        $this->assertTrue(class_exists('App\Services\AuthService'));
    }

    public function test_pembuatan_token_autentikasi_mengembalikan_struktur_jwt_yang_valid(): void
    {
        $apiServiceMock = Mockery::mock(ApiService::class);
        $authService = new AuthService($apiServiceMock);

        $apiServiceMock->shouldReceive('post')
            ->once()
            ->with('/auth/login', ['email' => 'admin@farm.com', 'password' => 'secret'])
            ->andReturn([
                'token' => 'invalid_format_bukan_jwt',
                'data' => ['id' => 1, 'role' => 'admin']
            ]);

        $hasilAktual = $authService->login('admin@farm.com', 'secret');

        $this->assertArrayHasKey('token', $hasilAktual);
        $this->assertStringStartsWith('eyJ', $hasilAktual['token'], 'Format token gagal diverifikasi, bukan JWT standar.');
    }

    public function test_validasi_kredensial_login_menangani_password_yang_salah(): void
    {
        $apiServiceMock = Mockery::mock(ApiService::class);
        $authService = new AuthService($apiServiceMock);

        $apiServiceMock->shouldReceive('post')
            ->once()
            ->andThrow(new ApiException('Unauthorized', 401));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Unauthorized');
        
        $authService->login('test@test.com', 'wrong_password');
    }
}
