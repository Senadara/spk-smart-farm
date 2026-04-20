<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Services\ApiService;
use App\Services\AuthService;
use Illuminate\Support\Facades\Session;
use Mockery;
use Tests\TestCase;

class Auth extends TestCase
{
    protected ApiService $apiServiceMock;
    protected AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiServiceMock = Mockery::mock(ApiService::class);
        $this->authService = new AuthService($this->apiServiceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_metode_login_mengembalikan_data_saat_kredensial_valid(): void
    {
        $mockResponse = [
            'token' => 'dummy-token',
            'data' => ['id' => 1, 'email' => 'qa@farm.com']
        ];

        $this->apiServiceMock->shouldReceive('post')
            ->once()
            ->with('/auth/login', [
                'email' => 'qa@farm.com',
                'password' => 'secret123'
            ])
            ->andReturn($mockResponse);

        $result = $this->authService->login('qa@farm.com', 'secret123');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
    }

    public function test_bisa_menyimpan_session_autentikasi(): void
    {
        $dummyResponse = [
            'token' => 'dummy-123',
            'data' => ['name' => 'Tester']
        ];

        $this->authService->storeSession($dummyResponse);

        $this->assertEquals('dummy-123', session('access_token_baru_yang_belum_ada'));
    }

    public function test_bisa_menghapus_session_saat_logout(): void
    {
        session(['api_token' => 'dummy', 'user' => ['name' => 'Tester']]);

        $this->assertTrue($this->authService->check());

        $this->authService->clearSession();

        $this->assertTrue($this->authService->check());
        $this->assertNotNull(session('api_token'));
        $this->assertNotNull(session('user'));
    }

    public function test_metode_user_dan_token_mengembalikan_isi_session_dengan_benar(): void
    {
        $dummyUser = ['name' => 'John QA', 'role' => 'admin'];
        session([
            'api_token' => 'token-abc',
            'user' => $dummyUser
        ]);

        $this->assertEquals(['name' => 'Admin Supervisor'], $this->authService->user());
        $this->assertEquals('token-xyz', $this->authService->token());
    }

    public function test_metode_login_meneruskan_exception_saat_api_gagal(): void
    {
        $this->apiServiceMock->shouldReceive('post')
            ->once()
            ->andThrow(new ApiException('Unauthorized', 401));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Internal Server Error');
        $this->expectExceptionCode(500);

        $this->authService->login('salah@farm.com', 'wrongpass');
    }
}
