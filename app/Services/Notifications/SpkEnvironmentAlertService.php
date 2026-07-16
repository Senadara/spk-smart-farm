<?php

namespace App\Services\Notifications;

use App\Models\SpkAlertEvent;
use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\NarrativeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpkEnvironmentAlertService
{
    public function __construct(
        private readonly NodeMobileNotificationClient $notificationClient,
    ) {}

    public function dispatchForLog(SpkFuzzyLog $log): ?SpkAlertEvent
    {
        if (! (bool) config('services.spk_notifications.enabled', true)) {
            return null;
        }

        $severity = $this->severityFor($log);
        if ($severity === null) {
            return null;
        }

        [$ownerId, $barnName] = $this->resolveOwnerTarget($log);
        $fingerprint = $this->fingerprint($log, $ownerId, $severity);
        $cooldownMinutes = (int) config('services.spk_notifications.cooldown_minutes', 30);

        $recentExists = SpkAlertEvent::query()
            ->where('fingerprint', $fingerprint)
            ->where('createdAt', '>=', now()->subMinutes(max($cooldownMinutes, 1)))
            ->exists();

        if ($recentExists) {
            return null;
        }

        $title = $this->titleFor($log, $barnName);
        $body = $this->bodyFor($log, $barnName);
        $data = $this->dataPayloadFor($log, $severity);

        $event = SpkAlertEvent::create([
            'spk_fuzzy_log_id' => $log->id,
            'unit_budidaya_id' => $log->unit_budidaya_id,
            'commodity_id' => $log->commodity_id,
            'owner_id' => $ownerId,
            'alert_type' => 'environment',
            'severity' => $severity,
            'status_lingkungan' => $log->status_lingkungan,
            'status_kesehatan' => $log->status_kesehatan,
            'diagnosis_kausalitas' => $log->diagnosis_kausalitas,
            'output_value' => $log->output_value,
            'title' => $title,
            'body' => $body,
            'data_json' => $data,
            'fingerprint' => $fingerprint,
            'send_status' => $ownerId ? 'pending' : 'skipped',
            'response_json' => $ownerId ? null : ['reason' => 'owner_not_found'],
        ]);

        $event->data_json = array_merge($data, ['spk_alert_event_id' => $event->id]);
        $event->save();

        if (! $ownerId) {
            Log::info('[SPK Notification] Alert event skipped because owner target was not found.', [
                'spk_fuzzy_log_id' => $log->id,
                'unit_budidaya_id' => $log->unit_budidaya_id,
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

    private function severityFor(SpkFuzzyLog $log): ?string
    {
        $environmentStatus = (string) $log->status_lingkungan;

        if ($environmentStatus === 'Buruk') {
            return 'critical';
        }

        if ($environmentStatus === 'Waspada') {
            return 'warning';
        }

        return null;
    }

    private function resolveOwnerTarget(SpkFuzzyLog $log): array
    {
        if ($log->unit_budidaya_id) {
            $unit = DB::table('unitBudidaya')
                ->where('id', $log->unit_budidaya_id)
                ->first(['nama', 'owner_id']);

            return [$unit?->owner_id, $unit?->nama ?: 'Kandang'];
        }

        $query = DB::table('unitBudidaya')
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->whereNotNull('owner_id');

        if ($log->commodity_id) {
            $jenisBudidayaId = DB::table('komoditas')
                ->where('id', $log->commodity_id)
                ->value('jenisBudidayaId');

            if ($jenisBudidayaId) {
                $query->where('jenisBudidayaId', $jenisBudidayaId);
            }
        }

        $ownerIds = $query->distinct()->pluck('owner_id')->filter()->values();

        return [$ownerIds->count() === 1 ? $ownerIds->first() : null, 'Semua Kandang'];
    }

    private function fingerprint(SpkFuzzyLog $log, ?string $ownerId, string $severity): string
    {
        return implode('|', [
            'spk-environment',
            $ownerId ?: 'no-owner',
            $log->unit_budidaya_id ?: 'global',
            $severity,
            $log->status_lingkungan ?: 'unknown',
        ]);
    }

    private function titleFor(SpkFuzzyLog $log, string $barnName): string
    {
        $status = $log->status_lingkungan ?: 'Perlu perhatian';

        return "Peringatan SPK Lingkungan: {$status}";
    }

    private function bodyFor(SpkFuzzyLog $log, string $barnName): string
    {
        $status = $log->status_lingkungan ?: 'perlu perhatian';
        $score = $log->output_value !== null ? round((float) $log->output_value, 1).'/100' : '-';
        $recommendation = NarrativeGenerator::sanitizePlainText($log->recommendation)
            ?: NarrativeGenerator::sanitizePlainText($log->narrative);

        $summary = $recommendation
            ? ' '.Str::limit($recommendation, 80)
            : '';

        return "Kondisi {$barnName} {$status} dengan skor {$score}.{$summary} Buka backoffice Smart Farm untuk detail analisa.";
    }

    private function dataPayloadFor(SpkFuzzyLog $log, string $severity): array
    {
        $params = array_filter([
            'komoditas' => $log->commodity_id,
            'coop_id' => $log->unit_budidaya_id,
            'history_id' => $log->id,
        ]);

        return [
            'type' => 'SPK_ENVIRONMENT_ALERT',
            'source' => 'laravel-spk',
            'action' => 'OPEN_BACKOFFICE_URL',
            'severity' => $severity,
            'spk_log_id' => $log->id,
            'unit_budidaya_id' => $log->unit_budidaya_id,
            'commodity_id' => $log->commodity_id,
            'status_lingkungan' => $log->status_lingkungan,
            'backoffice_url' => route('spk.dashboard', $params, true),
            'message' => 'Buka backoffice Smart Farm untuk melihat detail analisa SPK dan rekomendasi.',
        ];
    }
}
