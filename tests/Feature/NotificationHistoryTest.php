<?php

namespace Tests\Feature;

use App\Models\IotDeviceLog;
use App\Models\SpkAlertEvent;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\Authenticate::class,
            \App\Http\Middleware\CheckRole::class,
            \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        ]);
    }

    public function test_notification_history_groups_read_and_unread_events(): void
    {
        Carbon::setTestNow(Carbon::parse('2030-07-23 10:00:00'));

        $today = $this->createAlertEvent('Notifikasi hari ini');
        $yesterday = $this->createAlertEvent('Notifikasi kemarin', now()->subDay(), now()->subHour());
        $iotLog = $this->createIotLog('Sensor gateway timeout', now()->subMinutes(30));

        $response = $this->withSession([
            'user' => ['id' => (string) Str::uuid(), 'role' => 'pjawab'],
        ])->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Hari ini');
        $response->assertSee('Kemarin');
        $response->assertSee($today->title);
        $response->assertSee($yesterday->title);
        $response->assertSee('IoT Error');
        $response->assertSee($iotLog->message);
        $response->assertSee('Belum dibaca');
        $response->assertSee('Sudah dibaca');

        Carbon::setTestNow();
    }

    public function test_notification_can_be_marked_read_and_unread(): void
    {
        $event = $this->createAlertEvent('Perlu dicek');

        $session = [
            '_token' => 'notification-test-token',
            'user' => ['id' => (string) Str::uuid(), 'role' => 'pjawab'],
        ];

        $this->withSession($session)
            ->patch(route('notifications.read', $event), ['_token' => 'notification-test-token'])
            ->assertRedirect();

        $this->assertNotNull($event->fresh()->read_at);

        $this->withSession($session)
            ->patch(route('notifications.unread', $event), ['_token' => 'notification-test-token'])
            ->assertRedirect();

        $this->assertNull($event->fresh()->read_at);
    }

    private function createAlertEvent(string $title, ?Carbon $createdAt = null, ?Carbon $readAt = null): SpkAlertEvent
    {
        $event = SpkAlertEvent::create([
            'alert_type' => 'environment',
            'severity' => 'warning',
            'title' => $title,
            'body' => 'Ada kondisi yang perlu ditinjau.',
            'fingerprint' => (string) Str::uuid(),
            'send_status' => 'sent',
            'read_at' => $readAt,
        ]);

        if ($createdAt) {
            $event->forceFill([
                'createdAt' => $createdAt,
                'updatedAt' => $createdAt,
            ])->save();
        }

        return $event->fresh();
    }

    private function createIotLog(string $message, ?Carbon $createdAt = null): IotDeviceLog
    {
        $livestockTypeId = (string) Str::uuid();
        $unitId = (string) Str::uuid();
        $protocolId = (string) Str::uuid();
        $connectionConfigId = (string) Str::uuid();
        $deviceId = (string) Str::uuid();
        $timestamp = ($createdAt ?: now())->toDateTimeString();

        DB::table('jenisBudidaya')->insert([
            'id' => $livestockTypeId,
            'nama' => 'Ternak Histori',
            'tipe' => 'hewan',
            'status' => 1,
            'isDeleted' => 0,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ]);

        DB::table('unitBudidaya')->insert([
            'id' => $unitId,
            'jenisBudidayaId' => $livestockTypeId,
            'nama' => 'Kandang histori',
            'lokasi' => 'Area test',
            'luas' => 1,
            'kapasitas' => 10,
            'jumlah' => 10,
            'status' => 1,
            'isDeleted' => 0,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ]);

        DB::table('iot_protocol')->insert([
            'id' => $protocolId,
            'protocolName' => 'MQTT-HISTORI',
            'description' => 'Protocol histori notifikasi',
            'createdAt' => $timestamp,
        ]);

        DB::table('iot_connection_config')->insert([
            'id' => $connectionConfigId,
            'protocolId' => $protocolId,
            'mqttBrokerUrl' => 'broker.test.local',
            'mqttTopic' => 'smartfarm/test/history',
            'createdAt' => $timestamp,
        ]);

        DB::table('iot_device')->insert([
            'id' => $deviceId,
            'unitBudidayaId' => $unitId,
            'connectionConfigId' => $connectionConfigId,
            'deviceCode' => 'DEV-HISTORI',
            'deviceName' => 'Sensor histori',
            'pollingInterval' => 60,
            'status' => 'offline',
            'installedAt' => $timestamp,
            'createdAt' => $timestamp,
            'updatedAt' => $timestamp,
        ]);

        $log = IotDeviceLog::create([
            'deviceId' => $deviceId,
            'logType' => 'ERROR',
            'message' => $message,
        ]);

        $log->forceFill(['createdAt' => $createdAt ?: now()])->save();

        return $log->fresh();
    }
}
