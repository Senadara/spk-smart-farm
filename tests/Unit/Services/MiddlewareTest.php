<?php

namespace Tests\Unit;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\RedirectIfAuthenticated;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MiddlewareTest extends TestCase
{
    /**
     * Fitur: Middleware - Authenticate
     * Skenario: Guest diarahkan ke route login oleh middleware Authenticate
     * Given request tanpa session autentikasi
     * When middleware dijalankan pada route yang membutuhkan auth
     * Then respon mengarahkan ke route login
     */
    public function test_authenticate_middleware_mengarahkan_guest_ke_login(): void
    {
        $middleware = new Authenticate();
        $response = $middleware->handle(Request::create('/dashboard', 'GET'), fn() => response('next'));

        $this->assertSame(route('login'), $response->getTargetUrl());
    }

    /**
     * Fitur: Middleware - Authenticate
     * Skenario: Middleware meneruskan request jika session valid
     * Given session dengan api_token dan user
     * When middleware dijalankan
     * Then callback selanjutnya dieksekusi dan response diteruskan
     */
    public function test_authenticate_middleware_meneruskan_request_saat_session_valid(): void
    {
        session([
            'api_token' => 'token-123',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ]);

        $middleware = new Authenticate();
        $response = $middleware->handle(Request::create('/dashboard', 'GET'), fn() => response('next'));

        $this->assertSame('next', $response->getContent());
    }

    /**
     * Fitur: Middleware - RedirectIfAuthenticated
     * Skenario: User yang sudah login diarahkan dari /login ke dashboard
     * Given session user terautentikasi
     * When middleware RedirectIfAuthenticated dijalankan pada route /login
     * Then respon mengarahkan ke route dashboard
     */
    public function test_redirect_if_authenticated_mengarahkan_user_login_ke_dashboard(): void
    {
        session([
            'api_token' => 'token-123',
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ]);

        $middleware = new RedirectIfAuthenticated();
        $response = $middleware->handle(Request::create('/login', 'GET'), fn() => response('next'));

        $this->assertSame(route('dashboard'), $response->getTargetUrl());
    }

    /**
     * Fitur: Middleware - CheckRole
     * Skenario: Mengizinkan akses ketika role sesuai
     * Given session user dengan role yang sesuai
     * When middleware CheckRole dijalankan
     * Then request dilanjutkan dan hasil 'allowed' dikembalikan
     */
    public function test_check_role_mengizinkan_role_yang_sesuai(): void
    {
        session([
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ]);

        $middleware = new CheckRole();
        $response = $middleware->handle(Request::create('/admin', 'GET'), fn() => response('allowed'), 'admin');

        $this->assertSame('allowed', $response->getContent());
    }

    /**
     * Fitur: Middleware - CheckRole
     * Skenario: Menolak akses ketika role tidak sesuai
     * Given session user dengan role yang tidak sesuai
     * When middleware CheckRole dijalankan dengan role 'admin'
     * Then HttpException dilempar
     */
    public function test_check_role_menolak_role_yang_tidak_sesuai(): void
    {
        $this->expectException(HttpException::class);

        session([
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'user'],
        ]);

        $middleware = new CheckRole();
        $middleware->handle(Request::create('/admin', 'GET'), fn() => response('allowed'), 'admin');
    }
}