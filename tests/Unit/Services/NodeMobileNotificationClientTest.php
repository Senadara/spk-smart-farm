<?php

namespace Tests\Unit\Services;

use App\Services\Notifications\NodeMobileNotificationClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NodeMobileNotificationClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.node_notifications.base_url' => 'http://node.test/api',
            'services.node_notifications.internal_token' => 'internal-secret',
            'services.node_notifications.timeout' => 5,
        ]);
    }

    public function test_it_sends_user_notification_through_node_gateway(): void
    {
        Http::fake([
            'http://node.test/api/internal/notifications/mobile' => Http::response([
                'success' => true,
                'data' => ['success' => true, 'messageId' => 'message-1'],
            ], 200),
        ]);

        $result = (new NodeMobileNotificationClient())->sendToUser(
            'user-1',
            'Peringatan',
            'Kandang perlu dicek',
            ['type' => 'SPK_ENVIRONMENT_ALERT']
        );

        $this->assertTrue($result['success']);

        Http::assertSent(fn ($request) => $request->url() === 'http://node.test/api/internal/notifications/mobile'
            && $request->hasHeader('X-Internal-Token', 'internal-secret')
            && $request['target']['userId'] === 'user-1'
            && $request['title'] === 'Peringatan'
            && $request['data']['type'] === 'SPK_ENVIRONMENT_ALERT');
    }

    public function test_it_sends_role_notification_through_node_gateway(): void
    {
        Http::fake([
            'http://node.test/api/internal/notifications/mobile' => Http::response([
                'success' => true,
                'data' => ['success' => true, 'successCount' => 2],
            ], 200),
        ]);

        $result = (new NodeMobileNotificationClient())->sendToRole(
            'pjawab',
            'Pengingat',
            'Cek laporan harian'
        );

        $this->assertTrue($result['success']);

        Http::assertSent(fn ($request) => $request['target']['role'] === 'pjawab'
            && $request['title'] === 'Pengingat');
    }

    public function test_it_sends_broadcast_notification_through_node_gateway(): void
    {
        Http::fake([
            'http://node.test/api/internal/notifications/mobile' => Http::response([
                'success' => true,
                'data' => ['success' => true, 'successCount' => 3],
            ], 200),
        ]);

        $result = (new NodeMobileNotificationClient())->sendToAll(
            'Info',
            'Sinkronisasi selesai'
        );

        $this->assertTrue($result['success']);

        Http::assertSent(fn ($request) => $request['target']['all'] === true
            && $request['body'] === 'Sinkronisasi selesai');
    }
}
