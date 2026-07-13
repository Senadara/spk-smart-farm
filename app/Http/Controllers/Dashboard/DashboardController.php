<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\IotDeviceLog;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    public function index()
    {
        $livestock = $this->livestockProductivity();
        $crop = $this->cropProductivity();
        $spk = $this->spkProductivityStatus();

        return view('dashboard.index', [
            'user' => session('user', []),
            'productivityCards' => $this->productivityCards($livestock, $crop, $spk),
            'livestock' => $livestock,
            'crop' => $crop,
            'spk' => $spk,
            'trend' => $this->productivityTrend(),
            'alerts' => $this->alerts(),
            'quickLinks' => $this->quickLinks(),
        ]);
    }

    private function productivityCards(array $livestock, array $crop, array $spk): array
    {
        return [
            [
                'label' => 'Telur Hari Ini',
                'value' => $livestock['today_eggs_label'],
                'caption' => $livestock['active_units'].' kandang aktif',
                'tone' => $livestock['today_eggs'] > 0 ? 'emerald' : 'amber',
            ],
            [
                'label' => 'HDP Rata-rata',
                'value' => $livestock['hdp_label'],
                'caption' => 'Produktivitas ayam petelur',
                'tone' => $livestock['hdp'] >= 80 ? 'emerald' : ($livestock['hdp'] > 0 ? 'amber' : 'gray'),
            ],
            [
                'label' => 'Pakan Hari Ini',
                'value' => $livestock['feed_label'],
                'caption' => 'Konsumsi seluruh kandang',
                'tone' => $livestock['feed_kg'] > 0 ? 'sky' : 'gray',
            ],
            [
                'label' => 'Panen Kebun',
                'value' => $crop['harvest_label'],
                'caption' => $crop['active_units'].' blok aktif',
                'tone' => $crop['harvest_total'] > 0 ? 'emerald' : 'gray',
            ],
            [
                'label' => 'Kelengkapan Laporan',
                'value' => $livestock['report_coverage_label'],
                'caption' => 'Kelengkapan data hari ini',
                'tone' => $livestock['report_coverage'] >= 100 ? 'emerald' : ($livestock['report_coverage'] > 0 ? 'amber' : 'red'),
            ],
            [
                'label' => 'Status SPK',
                'value' => $spk['summary_label'],
                'caption' => $spk['summary_caption'],
                'tone' => $spk['tone'],
            ],
        ];
    }

    private function livestockProductivity(): array
    {
        $unitIds = $this->unitIdsByBudidayaType('hewan');
        $today = now()->toDateString();
        $population = $this->sumUnitPopulation($unitIds);
        $harvest = $this->harvestTotals($unitIds, $today, $today);
        $feedKg = $this->feedTotal($unitIds, $today, $today);
        $mortalityCount = $this->mortalityCount($unitIds, now()->startOfMonth()->toDateString(), $today);
        $reportCoverage = $this->reportCoverage($unitIds, $today);

        $hdp = $population > 0 ? round(($harvest['total'] / $population) * 100, 1) : 0.0;
        $fcr = $harvest['mass'] > 0 ? round($feedKg / $harvest['mass'], 2) : 0.0;
        $mortalityRate = $population > 0 ? round(($mortalityCount / $population) * 100, 2) : 0.0;

        return [
            'active_units' => count($unitIds),
            'population' => $population,
            'population_label' => $this->formatNumber($population).' ekor',
            'today_eggs' => $harvest['total'],
            'today_eggs_label' => $this->formatNumber($harvest['total']),
            'egg_mass_label' => $this->formatDecimal($harvest['mass']).' kg egg mass',
            'feed_kg' => $feedKg,
            'feed_label' => $this->formatDecimal($feedKg).' kg',
            'hdp' => $hdp,
            'hdp_label' => $hdp > 0 ? $this->formatDecimal($hdp).'%' : '-',
            'fcr' => $fcr,
            'fcr_label' => $fcr > 0 ? $this->formatDecimal($fcr, 2) : '-',
            'mortality_count' => $mortalityCount,
            'mortality_label' => $this->formatNumber($mortalityCount).' ekor',
            'mortality_rate_label' => $this->formatDecimal($mortalityRate).'%',
            'report_coverage' => $reportCoverage['percentage'],
            'report_coverage_label' => $reportCoverage['reported'].'/'.$reportCoverage['total'],
            'report_message' => $reportCoverage['message'],
        ];
    }

    private function cropProductivity(): array
    {
        $unitIds = $this->unitIdsByBudidayaType('tumbuhan');
        $today = now()->toDateString();
        $plants = $this->sumUnitPopulation($unitIds);
        $harvest = $this->harvestTotals($unitIds, $today, $today);
        $reportCoverage = $this->reportCoverage($unitIds, $today);
        $sensorRisks = $this->iotRiskCount($unitIds);

        return [
            'active_units' => count($unitIds),
            'plants' => $plants,
            'plants_label' => $plants > 0 ? $this->formatNumber($plants).' tanaman' : '-',
            'harvest_total' => $harvest['mass'] > 0 ? $harvest['mass'] : $harvest['total'],
            'harvest_label' => ($harvest['mass'] > 0)
                ? $this->formatDecimal($harvest['mass']).' kg'
                : ($harvest['total'] > 0 ? $this->formatNumber($harvest['total']) : '-'),
            'report_coverage' => $reportCoverage['percentage'],
            'report_coverage_label' => $reportCoverage['reported'].'/'.$reportCoverage['total'],
            'sensor_risks' => $sensorRisks,
            'status_label' => $unitIds
                ? ($sensorRisks > 0 ? 'Perlu cek sensor' : 'Terpantau')
                : 'Belum ada blok kebun',
            'note' => $unitIds
                ? 'Ringkasan memakai laporan panen dan sensor pada unit bertipe tumbuhan.'
                : 'Data perkebunan akan aktif setelah ada unit budidaya bertipe tumbuhan dan laporan panen.',
        ];
    }

    private function spkProductivityStatus(): array
    {
        if (! Schema::hasTable('spk_fuzzy_logs')) {
            return [
                'score' => null,
                'score_label' => '-',
                'urgent_count' => 0,
                'analyses_today' => 0,
                'latest_label' => 'Belum ada analisa',
                'latest_human' => null,
                'active_tasks' => 0,
                'summary_label' => 'Menunggu',
                'summary_caption' => 'Analisa SPK belum tersedia',
                'badge_label' => 'Menunggu Data',
                'tone' => 'amber',
                'message' => 'Data SPK belum tersedia. Jalankan analisa setelah laporan harian dan sensor masuk.',
                'action_hint' => 'Lengkapi laporan harian lalu buka Analisa SPK.',
                'warnings' => [],
            ];
        }

        $todayLogs = SpkFuzzyLog::query()
            ->whereDate('createdAt', now()->toDateString())
            ->get();

        $latest = SpkFuzzyLog::query()->latest('createdAt')->first();
        $urgentQuery = SpkFuzzyLog::query()
            ->where('createdAt', '>=', now()->subDay())
            ->where(function ($query) {
                $query->whereIn('status_lingkungan', ['Waspada', 'Buruk'])
                    ->orWhereIn('status_kesehatan', ['Waspada', 'Buruk'])
                    ->orWhere('output_value', '<', 70);
            });
        $urgent = (clone $urgentQuery)->count();
        $warnings = (clone $urgentQuery)
            ->latest('createdAt')
            ->take(3)
            ->get()
            ->map(fn (SpkFuzzyLog $log) => [
                'title' => $this->spkWarningTitle($log),
                'message' => Str::limit($log->recommendation ?: $log->narrative ?: 'Tinjau hasil analisa SPK terbaru untuk menentukan tindakan.', 120),
                'time' => $log->createdAt?->diffForHumans() ?? '-',
                'url' => route('spk.dashboard', array_filter([
                    'history_id' => $log->id,
                    'coop_id' => data_get($log, 'unit_budidaya_id'),
                ])),
            ])
            ->values()
            ->all();

        $activeTasks = Schema::hasTable('spk_action_tasks')
            ? SpkActionTask::withoutGlobalScopes()->whereIn('status', ['todo', 'in_progress'])->count()
            : 0;

        $score = $todayLogs->isNotEmpty()
            ? round((float) $todayLogs->avg('output_value'), 1)
            : ($latest ? round((float) $latest->output_value, 1) : null);

        $latestLabel = $latest?->createdAt?->locale('id')->translatedFormat('d M Y, H:i') ?? 'Belum ada analisa';

        if ($urgent > 0) {
            $tone = 'red';
            $summaryLabel = 'Perlu tindakan';
            $summaryCaption = $urgent.' peringatan SPK aktif';
            $badgeLabel = 'Perlu Tindakan';
            $message = 'Ada peringatan SPK yang perlu ditindaklanjuti hari ini.';
            $actionHint = 'Buka Analisa SPK, cek rekomendasi, lalu buat atau lanjutkan penugasan.';
        } elseif ($activeTasks > 0) {
            $tone = 'amber';
            $summaryLabel = 'Tugas aktif';
            $summaryCaption = $activeTasks.' penugasan belum selesai';
            $badgeLabel = 'Pantau Tugas';
            $message = 'Tidak ada peringatan SPK baru, tetapi masih ada penugasan aktif yang perlu dipantau.';
            $actionHint = 'Pantau progres penugasan agar rekomendasi SPK benar-benar selesai di lapangan.';
        } elseif ($todayLogs->isEmpty()) {
            $tone = 'amber';
            $summaryLabel = 'Menunggu data';
            $summaryCaption = 'Belum ada analisa hari ini';
            $badgeLabel = 'Menunggu Data';
            $message = 'Belum ada analisa SPK hari ini. Hasil akan lebih akurat setelah laporan harian dan data sensor tersedia.';
            $actionHint = 'Lengkapi laporan harian lalu jalankan Analisa SPK.';
        } else {
            $tone = 'emerald';
            $summaryLabel = 'Terkendali';
            $summaryCaption = 'Tidak ada peringatan SPK';
            $badgeLabel = 'Aman';
            $message = 'SPK tidak menemukan kondisi darurat yang membutuhkan tindakan segera.';
            $actionHint = 'Tetap pantau laporan harian dan sensor untuk menjaga produktivitas.';
        }

        return [
            'score' => $score,
            'score_label' => $score !== null ? $this->formatDecimal($score).'/100' : '-',
            'urgent_count' => $urgent,
            'analyses_today' => $todayLogs->count(),
            'latest_label' => $latestLabel,
            'latest_human' => $latest?->createdAt?->diffForHumans(),
            'active_tasks' => $activeTasks,
            'summary_label' => $summaryLabel,
            'summary_caption' => $summaryCaption,
            'badge_label' => $badgeLabel,
            'tone' => $tone,
            'message' => $message,
            'action_hint' => $actionHint,
            'warnings' => $warnings,
        ];
    }

    private function spkWarningTitle(SpkFuzzyLog $log): string
    {
        $status = collect([
            $log->status_lingkungan,
            $log->status_kesehatan,
            $log->output_label,
        ])->filter()->join(' / ');

        if ($status !== '') {
            return 'Peringatan SPK: '.$status;
        }

        return 'Peringatan SPK perlu ditinjau';
    }

    private function productivityTrend(int $days = 7): array
    {
        $livestockIds = $this->unitIdsByBudidayaType('hewan');
        $cropIds = $this->unitIdsByBudidayaType('tumbuhan');
        $start = now()->subDays($days - 1)->toDateString();
        $end = now()->toDateString();
        $population = max($this->sumUnitPopulation($livestockIds), 1);

        $livestockHarvest = $this->harvestByDate($livestockIds, $start, $end);
        $feed = $this->feedByDate($livestockIds, $start, $end);
        $cropHarvest = $this->harvestByDate($cropIds, $start, $end);

        $labels = [];
        $eggs = [];
        $hdp = [];
        $feedData = [];
        $crop = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $key = $date->toDateString();
            $eggTotal = (float) ($livestockHarvest[$key]->total ?? 0);

            $labels[] = $date->format('d/m');
            $eggs[] = round($eggTotal, 1);
            $hdp[] = round(($eggTotal / $population) * 100, 1);
            $feedData[] = round((float) ($feed[$key]->total ?? 0), 1);
            $crop[] = round((float) (($cropHarvest[$key]->mass ?? 0) ?: ($cropHarvest[$key]->total ?? 0)), 1);
        }

        return [
            'labels' => $labels,
            'eggs' => $eggs,
            'hdp' => $hdp,
            'feed' => $feedData,
            'crop' => $crop,
        ];
    }

    private function unitIdsByBudidayaType(string $type): array
    {
        if (! Schema::hasTable('unitBudidaya')) {
            return [];
        }

        $query = DB::table('unitBudidaya');

        if (Schema::hasColumn('unitBudidaya', 'isDeleted')) {
            $query->where('unitBudidaya.isDeleted', 0);
        }

        if (Schema::hasColumn('unitBudidaya', 'status')) {
            $query->where('unitBudidaya.status', 1);
        }

        if (Schema::hasTable('jenisBudidaya') && Schema::hasColumn('unitBudidaya', 'jenisBudidayaId')) {
            $query->leftJoin('jenisBudidaya', 'unitBudidaya.jenisBudidayaId', '=', 'jenisBudidaya.id');

            if (Schema::hasColumn('jenisBudidaya', 'isDeleted')) {
                $query->where(function ($inner) {
                    $inner->where('jenisBudidaya.isDeleted', 0)->orWhereNull('jenisBudidaya.id');
                });
            }

            if (Schema::hasColumn('jenisBudidaya', 'tipe')) {
                $query->whereRaw('LOWER(jenisBudidaya.tipe) = ?', [$type]);
            } elseif (Schema::hasColumn('jenisBudidaya', 'nama')) {
                $query->where(function ($inner) use ($type) {
                    if ($type === 'hewan') {
                        $inner->where('jenisBudidaya.nama', 'like', '%Ayam%')
                            ->orWhere('jenisBudidaya.nama', 'like', '%Ternak%');
                    } else {
                        $inner->where('jenisBudidaya.nama', 'like', '%Tanam%')
                            ->orWhere('jenisBudidaya.nama', 'like', '%Kebun%')
                            ->orWhere('jenisBudidaya.nama', 'like', '%Melon%');
                    }
                });
            }
        }

        return $query
            ->pluck('unitBudidaya.id')
            ->filter()
            ->values()
            ->all();
    }

    private function sumUnitPopulation(array $unitIds): float
    {
        if (empty($unitIds) || ! Schema::hasTable('unitBudidaya') || ! Schema::hasColumn('unitBudidaya', 'jumlah')) {
            return 0.0;
        }

        return (float) DB::table('unitBudidaya')
            ->whereIn('id', $unitIds)
            ->sum('jumlah');
    }

    private function harvestTotals(array $unitIds, string $startDate, string $endDate): array
    {
        $row = $this->harvestQuery($unitIds, $startDate, $endDate)
            ?->selectRaw($this->harvestSelectRaw())
            ->first();

        return [
            'total' => (float) ($row->total ?? 0),
            'mass' => (float) ($row->mass ?? 0),
        ];
    }

    private function harvestByDate(array $unitIds, string $startDate, string $endDate)
    {
        $query = $this->harvestQuery($unitIds, $startDate, $endDate);
        if (! $query) {
            return collect();
        }

        return $query
            ->selectRaw('DATE(laporan.createdAt) as dt, '.$this->harvestSelectRaw())
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');
    }

    private function harvestQuery(array $unitIds, string $startDate, string $endDate)
    {
        if (empty($unitIds) || ! Schema::hasTable('panen') || ! Schema::hasTable('laporan')) {
            return null;
        }

        $query = DB::table('panen')
            ->join('laporan', 'panen.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $unitIds)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('laporan.isDeleted', 0);
        }

        if (Schema::hasColumn('panen', 'isDeleted')) {
            $query->where('panen.isDeleted', 0);
        }

        return $query;
    }

    private function harvestSelectRaw(): string
    {
        $massExpression = Schema::hasColumn('panen', 'berat')
            ? 'SUM(COALESCE(panen.berat, panen.jumlah * 0.06))'
            : 'SUM(COALESCE(panen.jumlah, 0) * 0.06)';

        return 'SUM(COALESCE(panen.jumlah, 0)) as total, '.$massExpression.' as mass';
    }

    private function feedTotal(array $unitIds, string $startDate, string $endDate): float
    {
        $row = $this->feedQuery($unitIds, $startDate, $endDate)
            ?->selectRaw('SUM(COALESCE(harianTernak.pakan, 0)) as total')
            ->first();

        return (float) ($row->total ?? 0);
    }

    private function feedByDate(array $unitIds, string $startDate, string $endDate)
    {
        $query = $this->feedQuery($unitIds, $startDate, $endDate);
        if (! $query) {
            return collect();
        }

        return $query
            ->selectRaw('DATE(laporan.createdAt) as dt, SUM(COALESCE(harianTernak.pakan, 0)) as total')
            ->groupBy('dt')
            ->get()
            ->keyBy('dt');
    }

    private function feedQuery(array $unitIds, string $startDate, string $endDate)
    {
        if (empty($unitIds) || ! Schema::hasTable('harianTernak') || ! Schema::hasTable('laporan')) {
            return null;
        }

        $query = DB::table('harianTernak')
            ->join('laporan', 'harianTernak.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $unitIds)
            ->whereDate('laporan.createdAt', '>=', $startDate)
            ->whereDate('laporan.createdAt', '<=', $endDate);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('laporan.isDeleted', 0);
        }

        if (Schema::hasColumn('harianTernak', 'isDeleted')) {
            $query->where('harianTernak.isDeleted', 0);
        }

        return $query;
    }

    private function mortalityCount(array $unitIds, string $startDate, string $endDate): int
    {
        if (empty($unitIds) || ! Schema::hasTable('kematian') || ! Schema::hasTable('laporan')) {
            return 0;
        }

        $query = DB::table('kematian')
            ->join('laporan', 'kematian.laporanId', '=', 'laporan.id')
            ->whereIn('laporan.unitBudidayaId', $unitIds);

        $dateColumn = Schema::hasColumn('kematian', 'tanggal') ? 'kematian.tanggal' : 'laporan.createdAt';
        $query->whereDate($dateColumn, '>=', $startDate)
            ->whereDate($dateColumn, '<=', $endDate);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('laporan.isDeleted', 0);
        }

        if (Schema::hasColumn('kematian', 'isDeleted')) {
            $query->where('kematian.isDeleted', 0);
        }

        return (int) $query->count();
    }

    private function reportCoverage(array $unitIds, string $date): array
    {
        $total = count($unitIds);
        if ($total === 0 || ! Schema::hasTable('laporan')) {
            return [
                'reported' => 0,
                'total' => $total,
                'percentage' => 0,
                'message' => $total === 0 ? 'Belum ada unit aktif.' : 'Tabel laporan belum tersedia.',
            ];
        }

        $query = DB::table('laporan')
            ->whereIn('unitBudidayaId', $unitIds)
            ->whereDate('createdAt', $date);

        if (Schema::hasColumn('laporan', 'isDeleted')) {
            $query->where('isDeleted', 0);
        }

        $reported = $query->distinct()->count('unitBudidayaId');
        $percentage = $total > 0 ? (int) round(($reported / $total) * 100) : 0;

        return [
            'reported' => $reported,
            'total' => $total,
            'percentage' => $percentage,
            'message' => $percentage >= 100
                ? 'Data laporan harian lengkap.'
                : 'Data laporan belum lengkap, hasil produktivitas bisa belum mewakili seluruh unit.',
        ];
    }

    private function iotRiskCount(array $unitIds): int
    {
        if (empty($unitIds) || ! Schema::hasTable('iot_device') || ! Schema::hasTable('iot_device_log')) {
            return 0;
        }

        return (int) DB::table('iot_device_log')
            ->join('iot_device', 'iot_device_log.deviceId', '=', 'iot_device.id')
            ->whereIn('iot_device.unitBudidayaId', $unitIds)
            ->whereIn('iot_device_log.logType', ['WARNING', 'ERROR'])
            ->where('iot_device_log.createdAt', '>=', now()->subDay())
            ->count();
    }

    private function formatNumber(float|int $value): string
    {
        return number_format((float) $value, 0, ',', '.');
    }

    private function formatDecimal(float|int $value, int $decimals = 1): string
    {
        return number_format((float) $value, $decimals, ',', '.');
    }

    private function alerts(): array
    {
        $iotAlerts = Schema::hasTable('iot_device_log')
            ? IotDeviceLog::query()
                ->with('device')
                ->whereIn('logType', ['WARNING', 'ERROR'])
                ->latest('createdAt')
                ->take(4)
                ->get()
                ->map(fn (IotDeviceLog $log) => [
                    'sort_key' => $log->createdAt?->timestamp ?? 0,
                    'type' => $log->logType === 'ERROR' ? 'danger' : 'warning',
                    'title' => 'IoT: '.($log->device?->deviceName ?? $log->device?->deviceCode ?? 'Device'),
                    'message' => Str::limit($log->message, 110),
                    'time' => $log->createdAt?->diffForHumans() ?? '-',
                    'url' => route('iot.monitoring'),
                ])
            : collect();

        $spkAlerts = Schema::hasTable('spk_fuzzy_logs')
            ? SpkFuzzyLog::query()
                ->where('createdAt', '>=', now()->subDays(3))
                ->where(function ($query) {
                    $query->whereIn('status_lingkungan', ['Waspada', 'Buruk'])
                        ->orWhereIn('status_kesehatan', ['Waspada', 'Buruk'])
                        ->orWhere('output_value', '<', 70);
                })
                ->latest('createdAt')
                ->take(4)
                ->get()
                ->map(fn (SpkFuzzyLog $log) => [
                    'sort_key' => $log->createdAt?->timestamp ?? 0,
                    'type' => ((float) $log->output_value < 55 || $log->status_lingkungan === 'Buruk') ? 'danger' : 'warning',
                    'title' => 'SPK: '.($log->diagnosis_kausalitas ?: $log->status_lingkungan ?: 'Perlu tindakan'),
                    'message' => Str::limit($log->recommendation ?: $log->narrative ?: 'Tinjau hasil analisa SPK terbaru.', 110),
                    'time' => $log->createdAt?->diffForHumans() ?? '-',
                    'url' => route('spk.dashboard', array_filter(['history_id' => $log->id, 'coop_id' => $log->unit_budidaya_id])),
                ])
            : collect();

        return $iotAlerts
            ->merge($spkAlerts)
            ->sortByDesc('sort_key')
            ->take(6)
            ->map(function (array $alert) {
                unset($alert['sort_key']);

                return $alert;
            })
            ->values()
            ->all();
    }

    private function quickLinks(): array
    {
        return [
            ['label' => 'Peternakan', 'caption' => 'Ringkasan farm dan kandang', 'url' => route('peternakan'), 'tone' => 'emerald'],
            ['label' => 'Analisa SPK', 'caption' => 'Evaluasi dan tindakan', 'url' => route('spk.dashboard'), 'tone' => 'sky'],
            ['label' => 'Inventaris', 'caption' => 'Stok dan restock', 'url' => route('inventory'), 'tone' => 'amber'],
            ['label' => 'IoT', 'caption' => 'Device, mapping, monitoring', 'url' => route('iot.dashboard'), 'tone' => 'gray'],
        ];
    }
}
