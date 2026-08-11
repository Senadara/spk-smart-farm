<?php

namespace App\Services\Notifications;

use App\Models\SpkAlertEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class LivestockCycleAlertService
{
    public function __construct(
        private readonly NodeMobileNotificationClient $notificationClient,
    ) {}

    public function dispatchForBarnRows(array $barn, array $rows, array $afkirConfig, ?string $commodityId = null, ?string $jenisBudidayaId = null): ?SpkAlertEvent
    {
        if (! (bool) config('services.spk_notifications.enabled', true) || ! Schema::hasTable('spk_alert_events')) {
            return null;
        }

        $counts = collect($rows)
            ->map(fn ($row) => data_get($row, 'lifecycle.afkirStatus'))
            ->filter(fn ($status) => in_array($status, ['due_soon', 'overdue'], true))
            ->countBy();

        $overdueCount = (int) ($counts['overdue'] ?? 0);
        $dueSoonCount = (int) ($counts['due_soon'] ?? 0);
        if ($overdueCount === 0 && $dueSoonCount === 0) {
            return null;
        }

        $unitId = (string) ($barn['id'] ?? '');
        [$ownerId, $barnName] = $this->resolveOwnerTarget($unitId, (string) ($barn['name'] ?? $barn['nama'] ?? 'Kandang'));
        $status = $overdueCount > 0 ? 'overdue' : 'due_soon';
        $severity = $status === 'overdue' ? 'critical' : 'warning';
        $label = trim((string) ($afkirConfig['label'] ?? 'Afkir / akhir siklus'));
        $label = $label !== '' ? $label : 'Afkir / akhir siklus';
        $targetWeeks = data_get($afkirConfig, 'target_weeks');
        $warningWeeks = (int) data_get($afkirConfig, 'warning_weeks', 4);
        $fingerprint = $this->fingerprint($unitId, $ownerId, $status, $targetWeeks);

        $alreadySent = SpkAlertEvent::query()
            ->where('fingerprint', $fingerprint)
            ->where('send_status', 'sent')
            ->exists();

        if ($alreadySent) {
            return null;
        }

        $title = $status === 'overdue'
            ? "Target {$label} terlewati"
            : "{$label} mendekati target";
        $body = $this->bodyFor($barnName, $label, $status, $overdueCount, $dueSoonCount, $warningWeeks);
        $data = $this->dataPayloadFor($unitId, $commodityId, $jenisBudidayaId, $status, $label, $overdueCount, $dueSoonCount, $targetWeeks, $warningWeeks);

        $event = SpkAlertEvent::query()
            ->where('fingerprint', $fingerprint)
            ->whereIn('send_status', ['pending', 'failed', 'skipped'])
            ->latest('createdAt')
            ->first();

        $payload = [
            'unit_budidaya_id' => $unitId !== '' ? $unitId : null,
            'commodity_id' => $commodityId,
            'owner_id' => $ownerId,
            'alert_type' => 'livestock_cycle',
            'severity' => $severity,
            'title' => $title,
            'body' => $body,
            'data_json' => $data,
            'fingerprint' => $fingerprint,
            'send_status' => $ownerId ? 'pending' : 'skipped',
            'response_json' => $ownerId ? null : ['reason' => 'owner_not_found'],
        ];

        if ($event) {
            $event->forceFill($payload)->save();
        } else {
            $event = SpkAlertEvent::create($payload);
        }

        $event->data_json = array_merge($data, ['spk_alert_event_id' => $event->id]);
        $event->save();

        if (! $ownerId) {
            Log::info('[Livestock Cycle Notification] Alert event skipped because owner target was not found.', [
                'unit_budidaya_id' => $unitId,
                'alert_type' => 'livestock_cycle',
            ]);

            return $event;
        }

        $response = $this->notificationClient->sendToUser($ownerId, $title, $body, $event->data_json);

        $event->forceFill([
            'send_status' => ($response['success'] ?? false) ? 'sent' : 'failed',
            'response_json' => $response,
            'sent_at' => ($response['success'] ?? false) ? now() : null,
        ])->save();

        return $event;
    }

    private function resolveOwnerTarget(string $unitId, string $fallbackName): array
    {
        if ($unitId === '' || ! Schema::hasTable('unitBudidaya')) {
            return [null, $fallbackName ?: 'Kandang'];
        }

        $columns = ['nama'];
        $ownerColumn = null;
        if (Schema::hasColumn('unitBudidaya', 'owner_id')) {
            $ownerColumn = 'owner_id';
            $columns[] = 'owner_id';
        } elseif (Schema::hasColumn('unitBudidaya', 'ownerId')) {
            $ownerColumn = 'ownerId';
            $columns[] = 'ownerId';
        }

        $unit = DB::table('unitBudidaya')
            ->where('id', $unitId)
            ->first($columns);

        return [
            $ownerColumn ? ($unit->{$ownerColumn} ?? null) : null,
            $unit?->nama ?: ($fallbackName ?: 'Kandang'),
        ];
    }

    private function fingerprint(string $unitId, ?string $ownerId, string $status, mixed $targetWeeks): string
    {
        return implode('|', [
            'livestock-cycle',
            $ownerId ?: 'no-owner',
            $unitId ?: 'global',
            $status,
            is_numeric($targetWeeks) ? (string) (int) $targetWeeks : 'no-target',
        ]);
    }

    private function bodyFor(string $barnName, string $label, string $status, int $overdueCount, int $dueSoonCount, int $warningWeeks): string
    {
        if ($status === 'overdue') {
            return "{$barnName} memiliki {$overdueCount} individu yang sudah melewati target {$label}. Segera rencanakan tindakan operasional.";
        }

        return "{$barnName} memiliki {$dueSoonCount} individu yang masuk masa peringatan {$label} ({$warningWeeks} minggu sebelum target).";
    }

    private function dataPayloadFor(string $unitId, ?string $commodityId, ?string $jenisBudidayaId, string $status, string $label, int $overdueCount, int $dueSoonCount, mixed $targetWeeks, int $warningWeeks): array
    {
        $routeParams = array_filter([
            'id' => $unitId,
            'komoditas' => $commodityId,
            'jenis_ternak' => $jenisBudidayaId,
            'afkir' => $status,
        ]);

        return [
            'type' => 'LIVESTOCK_CYCLE_ALERT',
            'source' => 'laravel-spk',
            'action' => 'OPEN_BACKOFFICE_URL',
            'severity' => $status === 'overdue' ? 'critical' : 'warning',
            'unit_budidaya_id' => $unitId ?: null,
            'commodity_id' => $commodityId,
            'jenis_budidaya_id' => $jenisBudidayaId,
            'cycle_status' => $status,
            'cycle_label' => $label,
            'overdue_count' => $overdueCount,
            'due_soon_count' => $dueSoonCount,
            'target_weeks' => is_numeric($targetWeeks) ? (int) $targetWeeks : null,
            'warning_weeks' => $warningWeeks,
            'backoffice_url' => $unitId !== ''
                ? route('peternakan.individual-productivity', $routeParams, true)
                : route('peternakan', [], true),
            'message' => 'Buka backoffice Smart Farm untuk melihat daftar individu ternak yang perlu tindakan.',
        ];
    }
}
