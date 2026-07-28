<?php

namespace App\Services\Health;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BarnHealthContextService
{
    private array $tableCache = [];

    private array $columnCache = [];

    public function forBarn(string $coopId, ?array $kpi = null, ?string $barnName = null, ?array $eggProductionDropContext = null): array
    {
        $today = now()->toDateString();
        $weekStart = now()->subDays(6)->toDateString();
        $monthStart = now()->startOfMonth()->toDateString();

        $deathToday = $this->deathCount($coopId, $today, $today);
        $deathWeek = $this->deathCount($coopId, $weekStart, $today);
        $deathMonth = $this->deathCount($coopId, $monthStart, $today);
        $sickToday = $this->sickReportCount($coopId, $today, $today);
        $sickWeek = $this->sickReportCount($coopId, $weekStart, $today);
        $feedToday = $this->feedTotal($coopId, $today);
        $latestSick = $this->latestSickReport($coopId);
        $latestSpk = $this->latestSpkLog($coopId);
        $production = $this->productionSnapshot($coopId, $kpi);

        $signals = collect()
            ->merge($this->deathSignals($deathToday, $deathWeek))
            ->merge($this->sickSignals($sickToday, $sickWeek, $latestSick))
            ->merge($this->productionSignals($production))
            ->merge($this->eggProductionDropSignals($eggProductionDropContext))
            ->merge($this->spkSignals($latestSpk))
            ->merge($this->feedSignals($feedToday))
            ->values()
            ->all();

        $status = $this->worstLevel($signals);
        $recommended = $status !== 'normal';

        $task = $this->buildTaskRecommendation($coopId, $barnName, $signals, $status, $recommended);

        return [
            'date' => Carbon::parse($today)->locale('id')->translatedFormat('d M Y'),
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'summary' => $this->summaryText($status, $signals),
            'signals' => $signals,
            'metrics' => array_values(array_filter([
                [
                    'label' => 'Kematian hari ini',
                    'value' => $deathToday > 0 ? number_format($deathToday, 0, ',', '.').' ekor' : 'Tidak ada',
                    'tone' => $deathToday > 0 ? 'red' : 'emerald',
                    'caption' => 'Dihitung dari record kematian pada laporan mobile.',
                ],
                [
                    'label' => 'Laporan sakit hari ini',
                    'value' => $sickToday > 0 ? number_format($sickToday, 0, ',', '.').' laporan' : 'Tidak ada',
                    'tone' => $sickToday > 0 ? 'amber' : 'emerald',
                    'caption' => 'Jumlah laporan sakit, bukan jumlah ayam sakit.',
                ],
                [
                    'label' => 'Kematian bulan ini',
                    'value' => $deathMonth > 0 ? number_format($deathMonth, 0, ',', '.').' ekor' : 'Tidak ada',
                    'tone' => $deathMonth > 0 ? 'amber' : 'emerald',
                    'caption' => 'Dipakai sebagai konteks mortalitas.',
                ],
                [
                    'label' => 'Pakan diberikan',
                    'value' => $feedToday > 0 ? number_format($feedToday, 1, ',', '.').' kg' : 'Belum ada',
                    'tone' => $feedToday > 0 ? 'sky' : 'gray',
                    'caption' => 'Bukan konsumsi aktual; sisa pakan perlu dicek petugas.',
                ],
                $eggProductionDropContext ? [
                    'label' => 'Tidak bertelur 7 hari',
                    'value' => number_format((float) data_get($eggProductionDropContext, 'nonLayingPercent', 0), 1, ',', '.').'%',
                    'tone' => data_get($eggProductionDropContext, 'isIndication') ? 'amber' : 'emerald',
                    'caption' => number_format((int) data_get($eggProductionDropContext, 'nonLayingChickenCount', 0), 0, ',', '.').' dari '.number_format((int) data_get($eggProductionDropContext, 'activeChickenCount', 0), 0, ',', '.').' ayam aktif.',
                ] : null,
            ])),
            'latest_sick' => $latestSick,
            'latest_spk' => $latestSpk,
            'production' => $production,
            'egg_production_drop' => $eggProductionDropContext,
            'mobile_checklist' => $this->mobileChecklist($signals),
            'task' => $task,
        ];
    }

    public function taskPlansForBarns(Collection $barns, Collection $users): Collection
    {
        if ($users->isEmpty() || ! $this->hasTable('spk_action_tasks')) {
            return collect();
        }

        $activeCoopIds = DB::table('spk_action_tasks')
            ->whereIn('status', ['todo', 'in_progress'])
            ->whereNotNull('unit_budidaya_id')
            ->pluck('unit_budidaya_id')
            ->all();

        $activeCounts = DB::table('spk_action_tasks')
            ->select('assigned_to', DB::raw('COUNT(*) as total'))
            ->whereIn('status', ['todo', 'in_progress'])
            ->whereNotNull('assigned_to')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to');

        return $barns
            ->reject(fn ($barn) => in_array($barn->id, $activeCoopIds, true))
            ->map(function ($barn) use ($users, $activeCounts) {
                $context = $this->forBarn($barn->id, null, $barn->nama);

                if (! ($context['task']['recommended'] ?? false)) {
                    return null;
                }

                $assignee = $users
                    ->sortBy(fn ($user) => (int) ($activeCounts[$user->id] ?? 0))
                    ->first();

                $dueDate = now()->addDays(match ($context['task']['priority']) {
                    'urgent' => 0,
                    'high' => 1,
                    'medium' => 2,
                    default => 3,
                })->toDateString();

                $params = [
                    'create_task' => 1,
                    'coop_id' => $barn->id,
                    'title' => $context['task']['title'],
                    'priority' => $context['task']['priority'],
                    'assigned_to' => $assignee?->id,
                    'due_date' => $dueDate,
                    'desc' => Str::limit($context['task']['description'], 500, ''),
                ];

                return [
                    'coop_id' => $barn->id,
                    'barn' => $barn->nama,
                    'status' => $context['status_label'],
                    'summary' => $context['summary'],
                    'priority' => $context['task']['priority'],
                    'priorityLabel' => $this->priorityLabel($context['task']['priority']),
                    'priorityClass' => $this->priorityClass($context['task']['priority']),
                    'assignee' => $assignee?->name ?? 'Belum ada petugas',
                    'due_date' => $dueDate,
                    'signals' => array_slice($context['signals'], 0, 3),
                    'url' => route('spk.tasks.index', array_filter($params, fn ($value) => filled($value))),
                ];
            })
            ->filter()
            ->take(6)
            ->values();
    }

    private function deathSignals(int $today, int $week): array
    {
        $signals = [];

        if ($today > 0) {
            $signals[] = [
                'level' => 'danger',
                'title' => 'Ada kematian ayam hari ini',
                'message' => number_format($today, 0, ',', '.').' ayam mati tercatat dari laporan mobile hari ini.',
                'source' => 'laporan + kematian',
                'action' => 'Investigasi penyebab kematian dan cek gejala pada ayam lain.',
            ];
        } elseif ($week >= 3) {
            $signals[] = [
                'level' => 'warning',
                'title' => 'Kematian berulang dalam 7 hari',
                'message' => number_format($week, 0, ',', '.').' ayam mati tercatat dalam 7 hari terakhir.',
                'source' => 'laporan + kematian',
                'action' => 'Pantau tren mortalitas dan lakukan pemeriksaan kandang.',
            ];
        }

        return $signals;
    }

    private function sickSignals(int $today, int $week, ?array $latestSick): array
    {
        $signals = [];

        if ($today > 0) {
            $signals[] = [
                'level' => 'warning',
                'title' => 'Ada laporan sakit hari ini',
                'message' => number_format($today, 0, ',', '.').' laporan sakit tercatat. Data ini menunjukkan kejadian pemeriksaan, bukan jumlah ayam sakit.',
                'source' => 'laporan + sakit',
                'action' => 'Validasi gejala dan status penanganan dari mobile.',
            ];
        } elseif ($week > 0) {
            $signals[] = [
                'level' => 'info',
                'title' => 'Ada riwayat sakit 7 hari terakhir',
                'message' => number_format($week, 0, ',', '.').' laporan sakit tercatat dalam 7 hari terakhir.',
                'source' => 'laporan + sakit',
                'action' => 'Lihat status pemantauan dan perkembangan gejala.',
            ];
        }

        if ($latestSick && in_array(Str::lower((string) ($latestSick['status'] ?? '')), ['mati', 'belum ditangani', 'pemantauan'], true)) {
            $signals[] = [
                'level' => $latestSick['status'] === 'Mati' ? 'danger' : 'warning',
                'title' => 'Status penyakit perlu tindak lanjut',
                'message' => 'Status terakhir: '.$latestSick['status'].($latestSick['diagnosis'] ? ' - '.$latestSick['diagnosis'] : '').'.',
                'source' => 'sakit + penyakit_ayam',
                'action' => 'Pastikan petugas mengirim perkembangan terbaru dari mobile.',
            ];
        }

        return $signals;
    }

    private function productionSignals(array $production): array
    {
        $signals = [];

        if (($production['hdp_drop_percent'] ?? 0) >= 15) {
            $signals[] = [
                'level' => 'warning',
                'title' => 'Produksi telur turun',
                'message' => 'HDP hari ini turun sekitar '.number_format($production['hdp_drop_percent'], 1, ',', '.').'% dibanding rata-rata 7 hari sebelumnya.',
                'source' => 'panen + unitBudidaya',
                'action' => 'Cek kondisi ayam, pakan tersisa, dan gejala yang terlihat.',
            ];
        }

        if (($production['reject_rate'] ?? 0) > 5) {
            $signals[] = [
                'level' => 'warning',
                'title' => 'Telur reject meningkat',
                'message' => 'Reject/rusak hari ini sekitar '.number_format($production['reject_rate'], 2, ',', '.').'%.',
                'source' => 'panenRincianGrade + grade',
                'action' => 'Cek kualitas telur, kondisi kandang, dan stres ayam.',
            ];
        }

        return $signals;
    }

    private function eggProductionDropSignals(?array $context): array
    {
        if (! $context) {
            return [];
        }

        if (! data_get($context, 'isIndividualHarvestReady')) {
            return [[
                'level' => 'info',
                'title' => 'Panen individu belum aktif',
                'message' => 'Kandang belum bertipe individu, sehingga sistem belum bisa menentukan ayam mana yang tidak bertelur.',
                'source' => 'unitBudidaya + detailPanen',
                'action' => 'Gunakan panen individu jika ingin diagnosis berbasis objek ayam.',
            ]];
        }

        $percent = (float) data_get($context, 'nonLayingPercent', 0);
        $count = (int) data_get($context, 'nonLayingChickenCount', 0);
        $active = (int) data_get($context, 'activeChickenCount', 0);
        $days = (int) data_get($context, 'period.days', 7);
        $threshold = (float) data_get($context, 'thresholdPercent', 40);

        if (data_get($context, 'isIndication')) {
            return [[
                'level' => 'warning',
                'title' => 'Indikasi ayam tidak bertelur tinggi',
                'message' => number_format($percent, 1, ',', '.')."% ayam ({$count}/{$active}) tidak bertelur selama {$days} hari, melewati ambang ".number_format($threshold, 0, ',', '.').'%.',
                'source' => 'detailPanen + objekBudidaya',
                'action' => 'Buat indikasi pemeriksaan kesehatan dan minta petugas menjalankan checklist kesehatan di mobile.',
            ]];
        }

        if ($count > 0) {
            return [[
                'level' => 'info',
                'title' => 'Ada ayam tidak bertelur',
                'message' => number_format($percent, 1, ',', '.')."% ayam ({$count}/{$active}) tidak bertelur dalam periode {$days} hari, belum melewati ambang ".number_format($threshold, 0, ',', '.').'%.',
                'source' => 'detailPanen + objekBudidaya',
                'action' => 'Pantau tren ini dan cek ulang jika persentase naik.',
            ]];
        }

        return [];
    }

    private function spkSignals(?array $latestSpk): array
    {
        if (! $latestSpk) {
            return [];
        }

        $statusText = Str::lower(trim(implode(' ', array_filter([
            $latestSpk['status_kesehatan'] ?? null,
            $latestSpk['diagnosis_kausalitas'] ?? null,
            $latestSpk['output_label'] ?? null,
        ]))));

        if (! Str::contains($statusText, ['waspada', 'buruk', 'kritis', 'anomali', 'wabah', 'gangguan'])) {
            return [];
        }

        return [[
            'level' => Str::contains($statusText, ['buruk', 'kritis', 'wabah']) ? 'danger' : 'warning',
            'title' => 'SPK produktivitas memberi peringatan',
            'message' => trim(($latestSpk['diagnosis_kausalitas'] ?: $latestSpk['status_kesehatan'] ?: 'Perlu pemeriksaan')).' pada evaluasi terakhir.',
            'source' => 'spk_fuzzy_logs',
            'action' => 'Buat tugas pemeriksaan kesehatan melalui mobile.',
        ]];
    }

    private function feedSignals(float $feedToday): array
    {
        if ($feedToday > 0) {
            return [[
                'level' => 'info',
                'title' => 'Pakan tercatat sebagai pemberian',
                'message' => 'Sistem belum mengetahui pakan benar-benar habis. Indikasi nafsu makan tetap perlu observasi sisa pakan oleh petugas.',
                'source' => 'harianTernak.pakan',
                'action' => 'Saat ada tugas kesehatan, minta petugas cek apakah pakan habis atau tersisa banyak.',
            ]];
        }

        return [];
    }

    private function productionSnapshot(string $coopId, ?array $kpi): array
    {
        $todayHdp = (float) ($kpi['hdp'] ?? $this->calculateHdpForDate($coopId, now()->toDateString()));
        $avgHdp = $this->averageHdpBeforeToday($coopId, 7);
        $dropPercent = $avgHdp > 0 && $todayHdp < $avgHdp
            ? (($avgHdp - $todayHdp) / $avgHdp) * 100
            : 0.0;

        return [
            'hdp_today' => round($todayHdp, 1),
            'hdp_avg_7d' => round($avgHdp, 1),
            'hdp_drop_percent' => round($dropPercent, 1),
            'reject_rate' => (float) ($kpi['afkir'] ?? 0),
            'mortalitas' => (float) ($kpi['mortalitas'] ?? 0),
        ];
    }

    private function averageHdpBeforeToday(string $coopId, int $days): float
    {
        $values = [];

        for ($i = 1; $i <= $days; $i++) {
            $date = now()->subDays($i)->toDateString();
            $hdp = $this->calculateHdpForDate($coopId, $date);
            if ($hdp > 0) {
                $values[] = $hdp;
            }
        }

        return empty($values) ? 0.0 : array_sum($values) / count($values);
    }

    private function calculateHdpForDate(string $coopId, string $date): float
    {
        if (! $this->hasTable('panen') || ! $this->hasTable('laporan') || ! $this->hasTable('unitBudidaya')) {
            return 0.0;
        }

        $population = (float) DB::table('unitBudidaya')->where('id', $coopId)->value('jumlah');
        if ($population <= 0) {
            return 0.0;
        }

        $eggs = (float) DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->whereDate('laporan.createdAt', $date)
            ->where('laporan.isDeleted', 0)
            ->where('panen.isDeleted', 0)
            ->sum('panen.jumlah');

        return ($eggs / $population) * 100;
    }

    private function deathCount(string $coopId, string $startDate, string $endDate): int
    {
        if (! $this->hasTable('kematian') || ! $this->hasTable('laporan')) {
            return 0;
        }

        return (int) DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate)
            ->where('laporan.isDeleted', 0)
            ->where('kematian.isDeleted', 0)
            ->count();
    }

    private function sickReportCount(string $coopId, string $startDate, string $endDate): int
    {
        if (! $this->hasTable('sakit') || ! $this->hasTable('laporan')) {
            return 0;
        }

        return (int) DB::table('sakit')
            ->join('laporan', 'sakit.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate)
            ->where('laporan.isDeleted', 0)
            ->where('sakit.isDeleted', 0)
            ->count();
    }

    private function feedTotal(string $coopId, string $date): float
    {
        if (! $this->hasTable('harianTernak') || ! $this->hasTable('laporan')) {
            return 0.0;
        }

        return (float) DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->whereDate('laporan.createdAt', $date)
            ->where('laporan.isDeleted', 0)
            ->where('harianTernak.isDeleted', 0)
            ->sum('harianTernak.pakan');
    }

    private function latestSickReport(string $coopId): ?array
    {
        if (! $this->hasTable('sakit') || ! $this->hasTable('laporan')) {
            return null;
        }

        $query = DB::table('sakit')
            ->join('laporan', 'sakit.laporanId', '=', 'laporan.id')
            ->where('laporan.unitBudidayaId', $coopId)
            ->where('laporan.isDeleted', 0)
            ->where('sakit.isDeleted', 0)
            ->orderByDesc('laporan.createdAt')
            ->select([
                'sakit.id',
                'sakit.status',
                'sakit.diagnosisPenyakit',
                'laporan.createdAt',
            ]);

        if ($this->hasTable('penyakit_ayam')) {
            $query->leftJoin('penyakit_ayam', 'sakit.diagnosisPenyakit', '=', 'penyakit_ayam.id')
                ->addSelect('penyakit_ayam.nama_penyakit as diagnosis_name');
        }

        $row = $query->first();
        if (! $row) {
            return null;
        }

        return [
            'id' => $row->id,
            'status' => $this->formatSickStatus($row->status),
            'diagnosis' => $row->diagnosis_name ?? null,
            'diagnosis_id' => $row->diagnosisPenyakit ?? null,
            'created_at' => Carbon::parse($row->createdAt)->locale('id')->translatedFormat('d M Y, H:i'),
        ];
    }

    private function latestSpkLog(string $coopId): ?array
    {
        if (! $this->hasTable('spk_fuzzy_logs')) {
            return null;
        }

        $row = DB::table('spk_fuzzy_logs')
            ->where('unit_budidaya_id', $coopId)
            ->orderByDesc('createdAt')
            ->first([
                'id',
                'status_lingkungan',
                'status_kesehatan',
                'diagnosis_kausalitas',
                'output_label',
                'output_value',
                'recommendation',
                'createdAt',
            ]);

        if (! $row) {
            return null;
        }

        return [
            'id' => $row->id,
            'status_lingkungan' => $row->status_lingkungan,
            'status_kesehatan' => $row->status_kesehatan,
            'diagnosis_kausalitas' => $row->diagnosis_kausalitas,
            'output_label' => $row->output_label,
            'output_value' => $row->output_value,
            'recommendation' => $row->recommendation,
            'created_at' => Carbon::parse($row->createdAt)->locale('id')->translatedFormat('d M Y, H:i'),
        ];
    }

    private function buildTaskRecommendation(string $coopId, ?string $barnName, array $signals, string $status, bool $recommended): array
    {
        $title = match ($status) {
            'danger' => 'Investigasi kesehatan - '.($barnName ?: 'Kandang'),
            'warning' => 'Pemeriksaan kesehatan - '.($barnName ?: 'Kandang'),
            default => 'Monitoring kesehatan - '.($barnName ?: 'Kandang'),
        };

        $priority = match ($status) {
            'danger' => 'urgent',
            'warning' => 'high',
            default => 'medium',
        };

        $reasons = collect($signals)
            ->whereIn('level', ['danger', 'warning'])
            ->take(4)
            ->map(fn ($signal) => '- '.$signal['title'].': '.$signal['message'])
            ->implode("\n");

        if (! $reasons) {
            $reasons = '- Tidak ada sinyal kritis. Lakukan monitoring rutin.';
        }

        $description = "Konteks kesehatan dari web:\n{$reasons}\n\nInstruksi mobile:\n- Cek kondisi ayam secara langsung.\n- Catat gejala yang terlihat dan jalankan SPK kesehatan jika perlu.\n- Cek apakah pakan habis, tersisa sedikit, atau tersisa banyak.\n- Lampirkan catatan dan foto bila ada temuan.";

        return [
            'recommended' => $recommended,
            'priority' => $priority,
            'title' => $title,
            'description' => $description,
            'url' => route('spk.tasks.index', [
                'create_task' => 1,
                'coop_id' => $coopId,
                'title' => $title,
                'priority' => $priority,
                'due_date' => now()->addDays($priority === 'urgent' ? 0 : 1)->toDateString(),
                'desc' => Str::limit($description, 500, ''),
            ]),
        ];
    }

    private function mobileChecklist(array $signals): array
    {
        $items = [
            'Cek apakah pakan habis, tersisa sedikit, atau tersisa banyak.',
            'Amati gejala umum: lemas, nafsu makan turun, diare, gangguan napas, atau perubahan perilaku.',
        ];

        foreach ($signals as $signal) {
            if (($signal['level'] ?? null) === 'danger') {
                $items[] = 'Jika ada kematian, cek ayam lain di sekitar lokasi kejadian dan catat dugaan penyebab.';
                break;
            }
        }

        foreach ($signals as $signal) {
            if (Str::contains(Str::lower($signal['title'] ?? ''), ['produksi', 'reject'])) {
                $items[] = 'Bandingkan kondisi telur dan kandang dengan hari normal.';
                break;
            }
        }

        return array_values(array_unique($items));
    }

    private function worstLevel(array $signals): string
    {
        $levels = collect($signals)->pluck('level')->all();

        if (in_array('danger', $levels, true)) {
            return 'danger';
        }

        if (in_array('warning', $levels, true)) {
            return 'warning';
        }

        return 'normal';
    }

    private function statusLabel(string $status): string
    {
        return [
            'danger' => 'Darurat',
            'warning' => 'Perlu Cek',
            'normal' => 'Terkendali',
        ][$status] ?? 'Terkendali';
    }

    private function summaryText(string $status, array $signals): string
    {
        if ($status === 'normal') {
            return 'Belum ada sinyal kesehatan penting dari laporan sakit, kematian, atau produktivitas kandang.';
        }

        $main = collect($signals)->first(fn ($signal) => in_array($signal['level'], ['danger', 'warning'], true));

        return $main
            ? $main['message']
            : 'Ada sinyal kesehatan yang perlu ditinjau petugas.';
    }

    private function formatSickStatus(mixed $status): string
    {
        if (is_numeric($status)) {
            return [
                0 => 'Belum Ditangani',
                1 => 'Pemantauan',
                2 => 'Sembuh',
                3 => 'Mati',
            ][(int) $status] ?? (string) $status;
        }

        return $status ? Str::title(str_replace('_', ' ', (string) $status)) : 'Belum Ditangani';
    }

    private function priorityLabel(string $priority): string
    {
        return [
            'urgent' => 'Urgent',
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            'low' => 'Rendah',
        ][$priority] ?? 'Sedang';
    }

    private function priorityClass(string $priority): string
    {
        return [
            'urgent' => 'bg-rose-50 text-rose-700 border-rose-200',
            'high' => 'bg-amber-50 text-amber-700 border-amber-200',
            'medium' => 'bg-sky-50 text-sky-700 border-sky-200',
            'low' => 'bg-slate-50 text-slate-600 border-slate-200',
        ][$priority] ?? 'bg-sky-50 text-sky-700 border-sky-200';
    }

    private function hasTable(string $table): bool
    {
        if (! array_key_exists($table, $this->tableCache)) {
            $this->tableCache[$table] = Schema::hasTable($table);
        }

        return $this->tableCache[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        if (! array_key_exists($key, $this->columnCache)) {
            $this->columnCache[$key] = $this->hasTable($table) && Schema::hasColumn($table, $column);
        }

        return $this->columnCache[$key];
    }
}
