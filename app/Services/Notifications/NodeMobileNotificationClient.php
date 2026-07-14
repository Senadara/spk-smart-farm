<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NodeMobileNotificationClient
{
    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
    {
        return $this->send(['userId' => $userId], $title, $body, $data);
    }

    public function sendToRole(string $role, string $title, string $body, array $data = []): array
    {
        return $this->send(['role' => $role], $title, $body, $data);
    }

    public function sendToAll(string $title, string $body, array $data = []): array
    {
        return $this->send(['all' => true], $title, $body, $data);
    }

    public function sendSpkAlertToUser(string $userId, string $title, string $body, array $data = []): array
    {
        return $this->post('internal/notifications/spk-alert', [
            'userId' => $userId,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ], $userId);
    }

    private function send(array $target, string $title, string $body, array $data = []): array
    {
        return $this->post('internal/notifications/mobile', [
            'target' => $target,
            'title' => $title,
            'body' => $body,
            'data' => $data,
        ], (string) ($target['userId'] ?? $target['role'] ?? 'all'));
    }

    private function post(string $path, array $payload, string $targetLabel): array
    {
        $baseUrl = rtrim((string) config('services.node_notifications.base_url'), '/');
        $timeout = (int) config('services.node_notifications.timeout', 10);
        $token = (string) config('services.node_notifications.internal_token', '');

        if ($baseUrl === '') {
            return [
                'success' => false,
                'error' => 'Node notification base URL is not configured.',
            ];
        }

        try {
            $http = Http::baseUrl($baseUrl)
                ->acceptJson()
                ->timeout($timeout);

            if ($token !== '') {
                $http = $http->withHeaders(['X-Internal-Token' => $token]);
            }

            $response = $http->post($path, $payload);

            $payload = $response->json() ?? [];

            return [
                'success' => $response->successful() && (bool) data_get($payload, 'success', false),
                'status' => $response->status(),
                'response' => $payload,
            ];
        } catch (\Throwable $e) {
            Log::warning('[SPK Notification] Failed calling Node notification gateway.', [
                'target' => $targetLabel,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
