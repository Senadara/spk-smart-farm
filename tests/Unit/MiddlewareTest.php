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
    public function test_authenticate_middleware_mengarahkan_guest_ke_login(): void
    {
        $middleware = new Authenticate();
        $response = $middleware->handle(Request::create('/dashboard', 'GET'), fn() => response('next'));

        $this->assertSame(route('login'), $response->getTargetUrl());
    }

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

    public function test_check_role_mengizinkan_role_yang_sesuai(): void
    {
        session([
            'user' => ['id' => 1, 'name' => 'QA Tester', 'email' => 'qa@farm.com', 'role' => 'admin'],
        ]);

        $middleware = new CheckRole();
        $response = $middleware->handle(Request::create('/admin', 'GET'), fn() => response('allowed'), 'admin');

        $this->assertSame('allowed', $response->getContent());
    }

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