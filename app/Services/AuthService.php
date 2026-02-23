<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Log;

class AuthService
{
    protected ApiService $api;

    public function __construct(ApiService $api)
    {
        $this->api = $api;
    }

    /**
     * Login via API backend.
     *
     * @return array{token: string, data: array}
     * @throws ApiException
     */
    public function login(string $email, string $password): array
    {
        $response = $this->api->post('/auth/login', [
            'email' => $email,
            'password' => $password,
        ]);
        //[dev only]
        Log::debug("Login response:30 - \n", $response);
        return $response;
    }

    /**
     * Simpan data autentikasi ke session Laravel.
     */
    public function storeSession(array $loginResponse): void
    {
        session([
            'api_token' => $loginResponse['token'],
            'user' => $loginResponse['data'] ?? $loginResponse['user'] ?? [],
            'logged_in_at' => now()->format('d M Y, H:i'),
        ]);
    }

    /**
     * Hapus session autentikasi (logout).
     */
    public function clearSession(): void
    {
        session()->forget(['api_token', 'user', 'logged_in_at']);
        session()->invalidate();
        session()->regenerateToken();
    }

    /**
     * Cek apakah user sudah terautentikasi.
     */
    public function check(): bool
    {
        return session()->has('api_token') && session()->has('user');
    }

    /**
     * Ambil data user dari session.
     */
    public function user(): ?array
    {
        return session('user');
    }

    /**
     * Ambil token dari session.
     */
    public function token(): ?string
    {
        return session('api_token');
    }
}
