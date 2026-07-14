<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NodeMobileNotificationClient
{
    public function sendToUser(string $userId, string $title, string $body, array $data = []): array
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

            $response = $http->post('internal/notifications/spk-alert', [
                'userId' => $userId,
                'title' => $title,
                'body' => $body,
                'data' => $data,
            ]);

            $payload = $response->json() ?? [];

            return [
                'success' => $response->successful() && (bool) data_get($payload, 'success', false),
                'status' => $response->status(),
                'response' => $payload,
            ];
        } catch (\Throwable $e) {
            Log::warning('[SPK Notification] Failed calling Node notification gateway.', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
