<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NotificationSettingsController extends Controller
{
    private const DEFAULT_HEALTH_SETTING_ID = '9f0b1800-0000-4000-8000-000000000601';

    public function index(Request $request)
    {
        $activeTab = in_array($request->query('tab'), ['harvest', 'health'], true)
            ? $request->query('tab')
            : 'harvest';
        $harvestSchedules = $this->harvestSchedules();

        return view('settings.notifications', [
            'activeTab' => $activeTab,
            'harvestSchedules' => $harvestSchedules,
            'harvestSummary' => $this->harvestSummary($harvestSchedules),
            'otherScheduleCount' => $this->otherScheduleCount(),
            'healthSetting' => $this->healthSetting(),
            'mobileTables' => [
                'scheduledUnitNotification' => Schema::hasTable('scheduledUnitNotification'),
                'laporan' => Schema::hasTable('laporan'),
                'panen' => Schema::hasTable('panen'),
            ],
        ]);
    }

    private function harvestSchedules(): Collection
    {
        if (! Schema::hasTable('scheduledUnitNotification')) {
            return collect();
        }

        $now = Carbon::now('Asia/Jakarta');
        $unitTableReady = Schema::hasTable('unitBudidaya');
        $query = DB::table('scheduledUnitNotification as n');
        $columns = [
            'n.id',
            'n.unitBudidayaId',
            'n.title',
            'n.messageTemplate',
            'n.notificationType',
            'n.tipeLaporan',
            'n.dayOfWeek',
            'n.dayOfMonth',
            'n.scheduledTime',
            'n.isActive',
            'n.lastTriggered',
        ];

        if ($unitTableReady) {
            $query->leftJoin('unitBudidaya as u', 'u.id', '=', 'n.unitBudidayaId');
            $columns[] = 'u.nama as unit_name';
        }

        if (Schema::hasColumn('scheduledUnitNotification', 'isDeleted')) {
            $query->where('n.isDeleted', false);
        }

        if (Schema::hasColumn('scheduledUnitNotification', 'tipeLaporan')) {
            $query->where('n.tipeLaporan', 'panen');
        }

        return $query
            ->select($columns)
            ->orderBy('n.scheduledTime')
            ->orderBy('n.createdAt', 'desc')
            ->get()
            ->map(function ($row) use ($now) {
                $harvest = $this->todayHarvestSummary((string) $row->unitBudidayaId, $now);
                $time = $this->normalizeTime($row->scheduledTime);
                $runsToday = $this->runsToday($row, $now);
                $hasPassed = $runsToday && $time !== null && $time <= $now->format('H:i');
                $isActive = (bool) $row->isActive;
                $status = $this->harvestScheduleStatus($isActive, $runsToday, $hasPassed, $harvest['count']);

                return [
                    'id' => $row->id,
                    'unit_id' => $row->unitBudidayaId,
                    'unit_name' => $row->unit_name ?? 'Kandang tanpa nama',
                    'title' => $row->title,
                    'message' => $this->compactMessage($row->messageTemplate),
                    'frequency' => $this->frequencyLabel($row),
                    'time' => $time ?: '-',
                    'is_active' => $isActive,
                    'runs_today' => $runsToday,
                    'has_passed' => $hasPassed,
                    'harvest_count' => $harvest['count'],
                    'latest_harvest_at' => $harvest['latest_at'],
                    'latest_harvest_label' => $this->dateTimeLabel($harvest['latest_at']),
                    'last_triggered_label' => $this->dateTimeLabel($row->lastTriggered),
                    'status' => $status,
                ];
            });
    }

    private function harvestSummary(Collection $schedules): array
    {
        return [
            'total' => $schedules->count(),
            'active' => $schedules->where('is_active', true)->count(),
            'waiting' => $schedules->where('status.key', 'waiting')->count(),
            'reminder' => $schedules->where('status.key', 'reminder')->count(),
            'done' => $schedules->where('status.key', 'done')->count(),
        ];
    }

    private function otherScheduleCount(): int
    {
        if (! Schema::hasTable('scheduledUnitNotification') || ! Schema::hasColumn('scheduledUnitNotification', 'tipeLaporan')) {
            return 0;
        }

        $query = DB::table('scheduledUnitNotification')
            ->where('tipeLaporan', '!=', 'panen');

        if (Schema::hasColumn('scheduledUnitNotification', 'isDeleted')) {
            $query->where('isDeleted', false);
        }

        return (int) $query->count();
    }

    private function todayHarvestSummary(string $unitBudidayaId, Carbon $now): array
    {
        if ($unitBudidayaId === '' || ! Schema::hasTable('laporan') || ! Schema::hasTable('panen')) {
            return ['count' => 0, 'latest_at' => null];
        }

        $query = DB::table('panen as p')
            ->join('laporan as l', 'l.id', '=', 'p.laporanId')
            ->where('l.unitBudidayaId', $unitBudidayaId)
            ->whereDate('l.createdAt', $now->toDateString());

        if (Schema::hasColumn('laporan', 'tipe')) {
            $query->where('l.tipe', 'panen');
        }

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('l.isDeleted', false);
        }

        if (Schema::hasColumn('panen', 'isDeleted')) {
            $query->where('p.isDeleted', false);
        }

        $row = $query
            ->selectRaw('COUNT(DISTINCT p.id) as harvest_count, MAX(l.createdAt) as latest_at')
            ->first();

        return [
            'count' => (int) ($row->harvest_count ?? 0),
            'latest_at' => $row->latest_at ?? null,
        ];
    }

    private function harvestScheduleStatus(bool $isActive, bool $runsToday, bool $hasPassed, int $harvestCount): array
    {
        if (! $isActive) {
            return ['key' => 'inactive', 'label' => 'Nonaktif', 'tone' => 'gray'];
        }

        if (! $runsToday) {
            return ['key' => 'scheduled', 'label' => 'Tidak jalan hari ini', 'tone' => 'gray'];
        }

        if ($harvestCount > 0) {
            return ['key' => 'done', 'label' => 'Sudah panen', 'tone' => 'emerald'];
        }

        if ($hasPassed) {
            return ['key' => 'reminder', 'label' => 'Perlu reminder', 'tone' => 'amber'];
        }

        return ['key' => 'waiting', 'label' => 'Menunggu jam', 'tone' => 'sky'];
    }

    private function healthSetting(): array
    {
        if (! Schema::hasTable('spk_health_scheduler_settings')) {
            return [
                'available' => false,
                'is_enabled' => false,
                'schedule_times' => ['07:00', '16:00'],
                'days' => 7,
                'threshold_percent' => 40,
                'target_role' => 'petugas',
                'last_run_at' => null,
                'last_status' => null,
                'last_summary' => null,
            ];
        }

        $row = DB::table('spk_health_scheduler_settings')
            ->where('id', self::DEFAULT_HEALTH_SETTING_ID)
            ->first();

        if (! $row) {
            return [
                'available' => true,
                'is_enabled' => false,
                'schedule_times' => ['07:00', '16:00'],
                'days' => 7,
                'threshold_percent' => 40,
                'target_role' => 'petugas',
                'last_run_at' => null,
                'last_status' => null,
                'last_summary' => null,
            ];
        }

        $times = $this->parseScheduleTimes($this->decodeJson($row->schedule_times) ?: ['07:00', '16:00']);

        return [
            'available' => true,
            'is_enabled' => (bool) $row->is_enabled,
            'schedule_times' => count($times) >= 2 ? $times : ['07:00', '16:00'],
            'days' => (int) $row->days,
            'threshold_percent' => (float) $row->threshold_percent,
            'target_role' => $row->target_role ?: 'petugas',
            'last_run_at' => $row->last_run_at,
            'last_status' => $row->last_status,
            'last_summary' => $this->decodeJson($row->last_summary),
        ];
    }

    private function runsToday(object $row, Carbon $now): bool
    {
        return match ($row->notificationType) {
            'weekly' => (int) $row->dayOfWeek === $now->dayOfWeek,
            'monthly' => (int) $row->dayOfMonth === $now->day,
            default => true,
        };
    }

    private function frequencyLabel(object $row): string
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return match ($row->notificationType) {
            'weekly' => 'Mingguan - '.($days[(int) $row->dayOfWeek] ?? 'hari dipilih'),
            'monthly' => 'Bulanan - tanggal '.((int) $row->dayOfMonth ?: '-'),
            default => 'Harian',
        };
    }

    private function parseScheduleTimes(mixed $value): array
    {
        $items = is_array($value) ? $value : preg_split('/[\s,;]+/', trim((string) $value));

        return collect($items)
            ->map(fn ($item) => $this->normalizeTime($item))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalizeTime(mixed $value): ?string
    {
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)/', trim((string) $value), $matches)) {
            return str_pad((string) ((int) $matches[1]), 2, '0', STR_PAD_LEFT).':'.$matches[2];
        }

        return null;
    }

    private function compactMessage(?string $message): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $message));
    }

    private function dateTimeLabel(mixed $value): string
    {
        if (! $value) {
            return '-';
        }

        return Carbon::parse($value)->format('d M Y, H:i');
    }

    private function decodeJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
