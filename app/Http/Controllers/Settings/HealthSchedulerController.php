<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Health\NodeHealthIndicationClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HealthSchedulerController extends Controller
{
    private const DEFAULT_SETTING_ID = '9f0b1800-0000-4000-8000-000000000601';

    public function __construct(
        protected NodeHealthIndicationClient $nodeHealthIndicationClient,
    ) {}

    public function index()
    {
        return view('settings.health-scheduler', [
            'setting' => $this->setting(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'morning_time' => ['required', 'date_format:H:i'],
            'afternoon_time' => ['required', 'date_format:H:i'],
            'days' => ['required', 'integer', 'in:7,14,30'],
            'threshold_percent' => ['required', 'numeric', 'min:1', 'max:100'],
            'target_role' => ['required', 'in:petugas,pjawab,owner,all'],
        ]);

        $scheduleTimes = $this->parseScheduleTimes($validated['morning_time'].','.$validated['afternoon_time']);

        if (count($scheduleTimes) !== 2) {
            return back()
                ->withInput()
                ->withErrors(['morning_time' => 'Isi jam pagi dan sore dengan format valid.']);
        }

        if ($scheduleTimes[0] >= $scheduleTimes[1]) {
            return back()
                ->withInput()
                ->withErrors(['afternoon_time' => 'Jam pengecekan sore harus lebih akhir dari jam pagi.']);
        }

        $exists = DB::table('spk_health_scheduler_settings')
            ->where('id', self::DEFAULT_SETTING_ID)
            ->exists();

        $payload = [
            'is_enabled' => $request->boolean('is_enabled'),
            'schedule_times' => json_encode($scheduleTimes),
            'days' => (int) $validated['days'],
            'threshold_percent' => (float) $validated['threshold_percent'],
            'target_role' => $validated['target_role'],
            'configured_by' => data_get(session('user'), 'id'),
            'updatedAt' => now(),
        ];

        if ($exists) {
            DB::table('spk_health_scheduler_settings')
                ->where('id', self::DEFAULT_SETTING_ID)
                ->update($payload);
        } else {
            DB::table('spk_health_scheduler_settings')->insert([
                'id' => self::DEFAULT_SETTING_ID,
                ...$payload,
                'createdAt' => now(),
            ]);
        }

        return redirect()
            ->route('settings.health-scheduler.index')
            ->with('success', 'Scheduler indikasi kesehatan berhasil disimpan.');
    }

    public function runNow()
    {
        $result = $this->nodeHealthIndicationClient->runHealthScheduler();

        if (! ($result['success'] ?? false)) {
            return back()->with('error', $result['message'] ?? 'Gagal menjalankan scheduler kesehatan.');
        }

        $summary = data_get($result, 'data.summary', []);
        $processed = (int) data_get($summary, 'processedUnitCount', 0);
        $created = (int) data_get($summary, 'createdIndicationCount', data_get($summary, 'createdReportCount', 0));
        $affected = (int) data_get($summary, 'affectedObjectCount', 0);
        $reminders = (int) data_get($summary, 'reminderNotificationCount', 0);
        $duplicateUnits = collect(data_get($summary, 'units', []))
            ->where('reason', 'DUPLICATE_PERIOD')
            ->count();
        $harvestNotReadyUnits = collect(data_get($summary, 'units', []))
            ->where('reason', 'HARVEST_NOT_READY')
            ->count();

        $message = "Uji scheduler selesai. {$processed} kandang dicek, {$created} indikasi dibuat untuk {$affected} ayam.";
        if ($reminders > 0) {
            $message .= " {$reminders} notifikasi pengingat dikirim untuk indikasi yang masih pending.";
        } elseif ($harvestNotReadyUnits > 0) {
            $message .= " {$harvestNotReadyUnits} kandang dilewati karena panen pagi/sore hari ini belum lengkap.";
        } elseif ($created === 0 && $duplicateUnits > 0) {
            $message .= " Ada {$duplicateUnits} kandang yang dilewati karena indikasi periode ini sudah pernah dibuat.";
        }

        return redirect()
            ->route('settings.health-scheduler.index')
            ->with('success', $message);
    }

    private function setting(): array
    {
        $row = DB::table('spk_health_scheduler_settings')
            ->where('id', self::DEFAULT_SETTING_ID)
            ->first();

        if (! $row) {
            return [
                'id' => self::DEFAULT_SETTING_ID,
                'is_enabled' => false,
                'schedule_times' => ['07:00', '16:00'],
                'schedule_times_text' => '07:00, 16:00',
                'morning_time' => '07:00',
                'afternoon_time' => '16:00',
                'days' => 7,
                'threshold_percent' => 40,
                'target_role' => 'petugas',
                'last_run_at' => null,
                'last_status' => null,
                'last_summary' => null,
            ];
        }

        $times = $this->decodeJson($row->schedule_times) ?: ['07:00', '16:00'];
        $times = $this->parseScheduleTimes(implode(',', $times));
        if (count($times) < 2) {
            $times = array_values(array_unique(array_merge($times, ['16:00'])));
            sort($times);
        }

        return [
            'id' => $row->id,
            'is_enabled' => (bool) $row->is_enabled,
            'schedule_times' => $times,
            'schedule_times_text' => implode(', ', $times),
            'morning_time' => $times[0] ?? '07:00',
            'afternoon_time' => $times[1] ?? '16:00',
            'days' => (int) $row->days,
            'threshold_percent' => (float) $row->threshold_percent,
            'target_role' => $row->target_role ?: 'petugas',
            'last_run_at' => $row->last_run_at,
            'last_status' => $row->last_status,
            'last_summary' => $this->decodeJson($row->last_summary),
        ];
    }

    private function parseScheduleTimes(string $value): array
    {
        $items = preg_split('/[\s,;]+/', trim($value)) ?: [];
        $times = [];

        foreach ($items as $item) {
            if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', trim($item), $matches)) {
                $times[] = str_pad((string) ((int) $matches[1]), 2, '0', STR_PAD_LEFT).':'.$matches[2];
            }
        }

        return array_values(array_unique($times));
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
