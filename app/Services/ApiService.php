<?php

namespace App\Services;

use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiService
{
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('api.base_url');
        $this->timeout = config('api.timeout');
    }

    /**
     * GET request ke API.
     */
    public function get(string $endpoint, array $query = [], ?string $token = null): array
    {
        return $this->request('GET', $endpoint, ['query' => $query], $token);
    }

    /**
     * POST request ke API.
     */
    public function post(string $endpoint, array $data = [], ?string $token = null): array
    {
        return $this->request('POST', $endpoint, ['json' => $data], $token);
    }

    /**
     * PUT request ke API.
     */
    public function put(string $endpoint, array $data = [], ?string $token = null): array
    {
        return $this->request('PUT', $endpoint, ['json' => $data], $token);
    }

    /**
     * DELETE request ke API.
     */
    public function delete(string $endpoint, ?string $token = null): array
    {
        return $this->request('DELETE', $endpoint, [], $token);
    }

    /**
     * Kirim HTTP request ke API backend.
     */
    protected function request(string $method, string $endpoint, array $options = [], ?string $token = null): array
    {
        try {
            $http = Http::baseUrl($this->baseUrl)
                ->timeout($this->timeout)
                ->acceptJson();

            // Attach Bearer token jika tersedia
            if ($token) {
                $http = $http->withToken($token);
            }

            // Ambil token dari session jika tidak diberikan
            if (!$token && session()->has('api_token')) {
                $http = $http->withToken(session('api_token'));
            }

            $response = match ($method) {
                'GET' => $http->get($endpoint, $options['query'] ?? []),
                'POST' => $http->post($endpoint, $options['json'] ?? []),
                'PUT' => $http->put($endpoint, $options['json'] ?? []),
                'DELETE' => $http->delete($endpoint),
            };

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            // Handle token expired atau tidak valid
            if ($response->status() === 401) {
                session()->forget(['api_token', 'user', 'logged_in_at']);
                session()->invalidate();
                session()->regenerateToken();

                throw new ApiException(
                    'Sesi login telah berakhir. Silakan login kembali.',
                    401
                );
            }

            // Handle error response lain dari API
            throw ApiException::fromResponse(
                $response->status(),
                $response->json()
            );
        } catch (ApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('API Request Failed', [
                'method' => $method,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            throw new ApiException(
                'Tidak dapat terhubung ke server. Silakan coba lagi.',
                503
            );
        }
    }
}
