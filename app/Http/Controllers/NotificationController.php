<?php

namespace App\Http\Controllers;

use App\Models\IotDeviceLog;
use App\Models\SpkAlertEvent;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['all', 'unread', 'read'], true)
            ? $request->query('status')
            : 'all';
        $period = in_array($request->query('period'), ['all', 'today', 'yesterday', 'last_7_days', 'this_month', 'last_month', 'older'], true)
            ? $request->query('period')
            : 'all';
        $perPage = min(max((int) $request->query('per_page', 15), 10), 50);
        $queryLimit = min(max($perPage * 8, 80), 300);

        $items = $this->spkNotificationItems($status, $period, $queryLimit)
            ->merge($this->iotNotificationItems($status, $period, $queryLimit))
            ->sortByDesc('sort_key')
            ->values();

        $page = LengthAwarePaginator::resolveCurrentPage();
        $events = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => $request->url()]
        );
        $events->appends($request->query());

        $sections = $events->getCollection()
            ->groupBy('section_key')
            ->map(fn ($items, string $key) => [
                'meta' => $this->sectionMeta($key),
                'items' => $items->values(),
            ]);

        return view('notifications.index', [
            'events' => $events,
            'sections' => $sections,
            'filters' => [
                'status' => $status,
                'period' => $period,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function markRead(SpkAlertEvent $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        $event->forceFill([
            'read_at' => now(),
            'read_by' => data_get(session('user'), 'id'),
        ])->save();

        return back();
    }

    public function markUnread(SpkAlertEvent $event): RedirectResponse
    {
        $this->authorizeEvent($event);

        $event->forceFill([
            'read_at' => null,
            'read_by' => null,
        ])->save();

        return back();
    }

    private function authorizeEvent(SpkAlertEvent $event): void
    {
        $role = strtolower((string) session('user.role'));
        $userId = (string) data_get(session('user'), 'id', '');

        if (in_array($role, ['admin', 'pjawab'], true)) {
            return;
        }

        if ($event->owner_id && $userId !== '' && (string) $event->owner_id === $userId) {
            return;
        }

        abort(403);
    }

    private function notificationQuery(): Builder
    {
        $role = strtolower((string) session('user.role'));
        $userId = (string) data_get(session('user'), 'id', '');

        return SpkAlertEvent::query()
            ->when(! in_array($role, ['admin', 'pjawab'], true), function (Builder $query) use ($userId) {
                $query->where('owner_id', $userId ?: '__none__');
            });
    }

    private function spkNotificationItems(string $status, string $period, int $limit): Collection
    {
        if (! Schema::hasTable('spk_alert_events')) {
            return collect();
        }

        try {
            $hasReadAt = Schema::hasColumn('spk_alert_events', 'read_at');

            $query = $this->notificationQuery()
                ->with('unitBudidaya')
                ->when($hasReadAt && $status === 'unread', fn (Builder $builder) => $builder->whereNull('read_at'))
                ->when($hasReadAt && $status === 'read', fn (Builder $builder) => $builder->whereNotNull('read_at'));

            $this->applyPeriodFilter($query, $period);

            return $query
                ->latest('createdAt')
                ->take($limit)
                ->get()
                ->map(fn (SpkAlertEvent $event) => $this->formatSpkEvent($event));
        } catch (\Throwable $e) {
            report($e);
            return collect();
        }
    }

    private function iotNotificationItems(string $status, string $period, int $limit): Collection
    {
        $role = strtolower((string) session('user.role'));

        if (
            $status === 'unread'
            || ! in_array($role, ['pjawab', 'owner', 'admin', 'inventor'], true)
            || ! Schema::hasTable('iot_device_log')
        ) {
            return collect();
        }

        try {
            $query = IotDeviceLog::with('device')
                ->whereIn('logType', ['WARNING', 'ERROR']);

            $this->applyPeriodFilter($query, $period);

            return $query
                ->latest('createdAt')
                ->take($limit)
                ->get()
                ->map(fn (IotDeviceLog $log) => $this->formatIotLog($log));
        } catch (\Throwable $e) {
            report($e);
            return collect();
        }
    }

    private function applyPeriodFilter(Builder $query, string $period): void
    {
        $now = now();

        match ($period) {
            'today' => $query->whereDate('createdAt', $now->toDateString()),
            'yesterday' => $query
                ->where('createdAt', '>=', $now->copy()->subDay()->startOfDay())
                ->where('createdAt', '<=', $now->copy()->subDay()->endOfDay()),
            'last_7_days' => $query->where('createdAt', '>=', $now->copy()->subDays(6)->startOfDay()),
            'this_month' => $query->where('createdAt', '>=', $now->copy()->startOfMonth()),
            'last_month' => $query
                ->where('createdAt', '>=', $now->copy()->subMonthNoOverflow()->startOfMonth())
                ->where('createdAt', '<=', $now->copy()->subMonthNoOverflow()->endOfMonth()),
            'older' => $query->where('createdAt', '<', $now->copy()->subMonthNoOverflow()->startOfMonth()),
            default => null,
        };
    }

    private function formatSpkEvent(SpkAlertEvent $event): array
    {
        $createdAt = $event->createdAt ? Carbon::parse($event->createdAt) : now();
        $readAt = $event->read_at ? Carbon::parse($event->read_at) : null;
        $isUnread = $readAt === null;

        return [
            'id' => $event->id,
            'title' => $event->title ?: 'Notifikasi SPK',
            'message' => Str::limit($event->body ?: 'Ada kondisi yang perlu ditinjau.', 180),
            'source' => $this->sourceLabel((string) $event->alert_type),
            'severity' => $event->severity ?: 'warning',
            'severity_label' => $this->severityLabel((string) $event->severity),
            'tone' => $event->severity === 'critical' ? 'red' : 'amber',
            'status_label' => $isUnread ? 'Baru' : 'Dibaca',
            'status_caption' => $isUnread ? 'Belum dibaca' : 'Sudah dibaca',
            'status_tone' => $isUnread ? 'sky' : 'gray',
            'is_unread' => $isUnread,
            'created_at' => $createdAt->format('d M Y, H:i'),
            'created_at_human' => $createdAt->locale('id')->diffForHumans(),
            'read_at' => $readAt?->format('d M Y, H:i'),
            'section_key' => $this->sectionKey($createdAt),
            'unit_name' => $event->unitBudidaya?->nama,
            'url' => data_get($event->data_json, 'backoffice_url') ?: route('dashboard'),
            'mark_read_url' => route('notifications.read', $event->id),
            'mark_unread_url' => route('notifications.unread', $event->id),
            'sort_key' => $createdAt->timestamp,
        ];
    }

    private function formatIotLog(IotDeviceLog $log): array
    {
        $createdAt = $log->createdAt ? Carbon::parse($log->createdAt) : now();
        $isError = $log->logType === 'ERROR';
        $deviceName = $log->device?->deviceName ?: $log->device?->deviceCode ?: 'Device IoT';

        return [
            'id' => 'iot-'.$log->id,
            'title' => 'IoT '.($isError ? 'Error' : 'Warning'),
            'message' => $deviceName.' - '.Str::limit($log->message ?: 'Ada log perangkat yang perlu ditinjau.', 180),
            'source' => 'IoT device',
            'severity' => $isError ? 'critical' : 'warning',
            'severity_label' => $isError ? 'Error' : 'Warning',
            'tone' => $isError ? 'red' : 'amber',
            'status_label' => 'Dibaca',
            'status_caption' => 'Log sistem',
            'status_tone' => 'gray',
            'is_unread' => false,
            'created_at' => $createdAt->format('d M Y, H:i'),
            'created_at_human' => $createdAt->locale('id')->diffForHumans(),
            'read_at' => null,
            'section_key' => $this->sectionKey($createdAt),
            'unit_name' => $log->device?->unitBudidaya?->nama,
            'url' => route('iot.monitoring'),
            'mark_read_url' => null,
            'mark_unread_url' => null,
            'sort_key' => $createdAt->timestamp,
        ];
    }

    private function sectionKey(Carbon $createdAt): string
    {
        $now = now();

        if ($createdAt->isToday()) {
            return 'today';
        }

        if ($createdAt->isYesterday()) {
            return 'yesterday';
        }

        if ($createdAt->greaterThanOrEqualTo($now->copy()->subDays(6)->startOfDay())) {
            return 'last_7_days';
        }

        if ($createdAt->isSameMonth($now)) {
            return 'this_month';
        }

        if ($createdAt->isSameMonth($now->copy()->subMonthNoOverflow())) {
            return 'last_month';
        }

        return 'older';
    }

    private function sectionMeta(string $key): array
    {
        return [
            'today' => ['label' => 'Hari ini', 'caption' => 'Notifikasi yang masuk hari ini', 'tone' => 'emerald'],
            'yesterday' => ['label' => 'Kemarin', 'caption' => 'Notifikasi dari hari sebelumnya', 'tone' => 'sky'],
            'last_7_days' => ['label' => '7 hari terakhir', 'caption' => 'Masih relevan untuk ditinjau minggu ini', 'tone' => 'amber'],
            'this_month' => ['label' => 'Bulan ini', 'caption' => 'Histori notifikasi bulan berjalan', 'tone' => 'gray'],
            'last_month' => ['label' => 'Bulan lalu', 'caption' => 'Histori notifikasi bulan sebelumnya', 'tone' => 'gray'],
            'older' => ['label' => 'Lebih lama', 'caption' => 'Histori sebelum bulan lalu', 'tone' => 'gray'],
        ][$key] ?? ['label' => 'Lainnya', 'caption' => 'Histori notifikasi', 'tone' => 'gray'];
    }

    private function sourceLabel(string $type): string
    {
        return match ($type) {
            'livestock_cycle' => 'Siklus ternak',
            'environment' => 'SPK lingkungan',
            default => Str::of($type ?: 'SPK')->replace('_', ' ')->title()->toString(),
        };
    }

    private function severityLabel(string $severity): string
    {
        return match ($severity) {
            'critical' => 'Kritis',
            'warning' => 'Peringatan',
            'info' => 'Info',
            default => Str::of($severity ?: 'warning')->replace('_', ' ')->title()->toString(),
        };
    }
}
