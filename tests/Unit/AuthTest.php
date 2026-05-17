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

    public function test_login_auth_service_mengembalikan_payload_api_yang_diteruskan(): void
    {
        $apiServiceMock = Mockery::mock(ApiService::class);
        $authService = new AuthService($apiServiceMock);

        $apiServiceMock->shouldReceive('post')
            ->once()
            ->with('/auth/login', ['email' => 'admin@farm.com', 'password' => 'secret'])
            ->andReturn([
                'token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.fake.signature',
                'data' => ['id' => 1, 'role' => 'admin']
            ]);

        $hasilAktual = $authService->login('admin@farm.com', 'secret');

        $this->assertArrayHasKey('token', $hasilAktual);
        $this->assertSame(1, $hasilAktual['data']['id']);
        $this->assertSame('admin', $hasilAktual['data']['role']);
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

    public function test_store_session_menyimpan_token_dan_data_user_ke_session(): void
    {
        $apiServiceMock = Mockery::mock(ApiService::class);
        $authService = new AuthService($apiServiceMock);

        $authService->storeSession([
            'token' => 'token-123',
            'data' => [
                'id' => 1,
                'name' => 'QA Tester',
                'email' => 'qa@farm.com',
            ],
        ]);

        $this->assertSame('token-123', session('api_token'));
        $this->assertSame('QA Tester', session('user.name'));
        $this->assertSame('qa@farm.com', session('user.email'));
    }
}
