<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NodeHealthIndicationClient
{
    public function eggProductionDropContext(string $unitBudidayaId, array $query = []): array
    {
        return $this->request('GET', 'internal/spk/egg-production-drop', [
            'unitBudidayaId' => $unitBudidayaId,
            'days' => $query['days'] ?? 7,
            'thresholdPercent' => $query['thresholdPercent'] ?? 40,
            'startDate' => $query['startDate'] ?? null,
            'endDate' => $query['endDate'] ?? null,
        ]);
    }

    public function individualEggProductivity(string $unitBudidayaId, array $query = []): array
    {
        return $this->request('GET', 'internal/spk/individual-egg-productivity', [
            'unitBudidayaId' => $unitBudidayaId,
            'days' => $query['days'] ?? 7,
            'thresholdPercent' => $query['thresholdPercent'] ?? 40,
            'startDate' => $query['startDate'] ?? null,
            'endDate' => $query['endDate'] ?? null,
            'sort' => $query['sort'] ?? 'drop',
            'direction' => $query['direction'] ?? 'desc',
        ]);
    }

    public function createAutomaticHealthIndication(string $unitBudidayaId, array $payload = []): array
    {
        return $this->request('POST', 'internal/spk/health-indications', [
            'unitBudidayaId' => $unitBudidayaId,
            'days' => $payload['days'] ?? 7,
            'thresholdPercent' => $payload['thresholdPercent'] ?? 40,
            'startDate' => $payload['startDate'] ?? null,
            'endDate' => $payload['endDate'] ?? null,
            'analysisMode' => $payload['analysisMode'] ?? null,
            'sort' => $payload['sort'] ?? null,
            'direction' => $payload['direction'] ?? null,
            'userId' => $payload['userId'] ?? null,
            'source' => $payload['source'] ?? 'laravel-spk',
            'notify' => $payload['notify'] ?? true,
            'targetRole' => $payload['targetRole'] ?? 'petugas',
            'force' => $payload['force'] ?? false,
        ]);
    }

    public function healthSchedulerStatus(): array
    {
        return $this->request('GET', 'internal/spk/health-scheduler', []);
    }

    public function runHealthScheduler(): array
    {
        return $this->request('POST', 'internal/spk/health-scheduler/run', [
            'source' => 'laravel-health-scheduler-manual',
        ]);
    }

    private function request(string $method, string $path, array $payload): array
    {
        $baseUrl = rtrim((string) config('services.node_notifications.base_url'), '/');
        $token = (string) config('services.node_notifications.internal_token', '');
        $timeout = (int) config('services.node_notifications.timeout', 10);

        if ($baseUrl === '') {
            return [
                'success' => false,
                'message' => 'Node API base URL belum dikonfigurasi.',
            ];
        }

        try {
            $http = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout($timeout);

            if ($token !== '') {
                $http = $http->withHeaders(['X-Internal-Token' => $token]);
            }

            $response = $method === 'GET'
                ? $http->get($path, array_filter($payload, fn ($value) => $value !== null && $value !== ''))
                : $http->post($path, array_filter($payload, fn ($value) => $value !== null && $value !== ''));

            $body = $response->json() ?? [];

            return [
                'success' => $response->successful() && (bool) data_get($body, 'success', false),
                'status' => $response->status(),
                'data' => data_get($body, 'data'),
                'message' => data_get($body, 'message'),
                'response' => $body,
            ];
        } catch (\Throwable $e) {
            Log::warning('[Health Indication] Failed calling Node API.', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
