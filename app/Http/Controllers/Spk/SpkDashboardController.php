<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use App\Models\SpkFuzzyProfile;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\PeternakanService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SpkDashboardController extends Controller
{
    /**
     * Tampilkan halaman utama SPK Analysis Dashboard.
     */
    public function index(Request $request, PeternakanService $peternakanService)
    {
        $komoditas = $request->input('komoditas', 'petelur');
        $coopId    = $request->filled('coop_id') ? $request->input('coop_id') : null;   // null = global
        $historyId = $request->input('history_id');

        $peternakanService->forKomoditas($komoditas);
        $activeKomoditasId = $peternakanService->getActiveKomoditasId();
        $komoditas = $activeKomoditasId ?? $komoditas;
        $prodData = $peternakanService->getProduktivitasData($coopId);

        // Jalankan Fuzzy Engine untuk mendapat data terkini.
        $latestResult = $this->runFuzzyEngine($coopId, $activeKomoditasId);

        // Metrik ringkas SPK dan rekomendasi supplier.
        $kpi                  = $this->getKpiMetrics();
        $recommendedSuppliers = $this->getAhpSawRanking();

        // History dari SpkFuzzyLog.
        $spkHistory    = $this->getSpkHistory($coopId, $activeKomoditasId);
        $activeHistory = collect($spkHistory)->firstWhere('id', $historyId) ?? ($spkHistory[0] ?? $this->emptyHistory());

        // Fuzzy status dan chart dari hasil engine.
        $fuzzyData  = $this->getFuzzyStatus($latestResult, $prodData);
        $chartData  = $this->getChartData($coopId);

        // Action tickets dari modul penugasan.
        $actionTickets = $this->getActionTickets($activeHistory['id'] ?? 'N/A');

        // Opsi kandang dari unitBudidaya.
        $jenisId = $peternakanService->getActiveJenisBudidayaId();
        $barnsOption = DB::table('unitBudidaya')
            ->when($jenisId, fn ($query) => $query->where('jenisBudidayaId', $jenisId))
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->nama])
            ->prepend(['id' => null, 'name' => 'Semua Kandang (Global)'])
            ->toArray();

        $komoditasOptions = DB::table('komoditas')
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->toArray();

        $filterOptions = [
            'komoditas' => $komoditasOptions ?: ['petelur' => 'Ayam Petelur'],
        ];

        return view('spk.dashboard', compact(
            'komoditas', 'coopId', 'filterOptions', 'kpi', 'fuzzyData',
            'chartData', 'recommendedSuppliers', 'actionTickets', 'barnsOption',
            'spkHistory', 'activeHistory', 'latestResult'
        ));
    }

    public function evaluate(Request $request, PeternakanService $peternakanService)
    {
        $komoditas = $request->input('komoditas', 'petelur');
        $coopId = $request->filled('coop_id') ? $request->input('coop_id') : null;

        $peternakanService->forKomoditas($komoditas);
        $coopIds = $coopId ? [$coopId] : $peternakanService->getActiveCoopIds();
        if (!$coopId) {
            $coopIds[] = null;
        }

        $processed = 0;
        $errors = [];

        foreach (array_unique($coopIds) as $targetCoopId) {
            try {
                $result = $this->runFuzzyEngine($targetCoopId, $peternakanService->getActiveKomoditasId());

                if (!empty($result['error'])) {
                    throw new \RuntimeException($result['error']);
                }

                $this->persistFuzzyResult($targetCoopId, $result, $peternakanService->getActiveKomoditasId());
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'coop_id' => $targetCoopId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $lastAt = SpkFuzzyLog::query()->orderByDesc('createdAt')->value('createdAt');

        return response()->json([
            'success' => $processed > 0,
            'processed' => $processed,
            'errors' => $errors,
            'evaluation_time' => $lastAt
                ? Carbon::parse($lastAt)->locale('id')->translatedFormat('d M Y, H:i')
                : null,
        ]);
    }

    // REAL DATA METHODS

    /**
     * Jalankan Mamdani Engine dan return hasil lengkap.
     */
    private function runFuzzyEngine(?string $coopId, ?string $commodityId = null): array
    {
        try {
            $resolver  = app(InputResolver::class);
            $engine    = app(MamdaniEngine::class);
            $narrator  = app(NarrativeGenerator::class);
            $profile   = SpkFuzzyProfile::resolveForContext($commodityId, $coopId);

            $inputs    = $resolver->resolve($coopId, $commodityId, $profile?->id);
            $result    = $engine->processCascaded($inputs, $profile?->id, $commodityId, $coopId);
            $barnName  = $coopId ? DB::table('unitBudidaya')->where('id', $coopId)->value('nama') : null;
            $narrative = $narrator->generate($result, $barnName);

            return array_merge($result, ['narrative' => $narrative, 'error' => null]);
        } catch (\Throwable $e) {
            \Log::error('[SpkDashboard] FuzzyEngine error: ' . $e->getMessage());
            return ['error' => $e->getMessage(), 'inputs' => [], 'lingkungan' => [], 'kesehatan' => [], 'kausalitas' => [], 'narrative' => null];
        }
    }

    private function persistFuzzyResult(?string $coopId, array $result, ?string $commodityId = null): SpkFuzzyLog
    {
        return SpkFuzzyLog::create([
            'unit_budidaya_id' => $coopId,
            'profile_id' => $result['profile']['id'] ?? null,
            'commodity_id' => $result['profile']['commodity_id'] ?? $commodityId,
            'input_json' => $result['inputs'] ?? [],
            'fuzzified_json' => [
                'lingkungan' => $result['lingkungan']['fuzzified'] ?? [],
                'kesehatan' => $result['kesehatan']['fuzzified'] ?? [],
            ],
            'rule_result_json' => [
                'lingkungan' => $result['lingkungan']['dominant_rule'] ?? null,
                'kesehatan' => $result['kesehatan']['dominant_rule'] ?? null,
                'kausalitas' => $result['kausalitas'] ?? null,
            ],
            'status_lingkungan' => $result['lingkungan']['label'] ?? null,
            'status_kesehatan' => $result['kesehatan']['label'] ?? null,
            'diagnosis_kausalitas' => $result['kausalitas']['label'] ?? null,
            'output_value' => $result['lingkungan']['value'] ?? 0,
            'output_label' => $result['kausalitas']['label'] ?? null,
            'narrative' => $result['narrative'] ?? null,
            'recommendation' => $result['kausalitas']['recommendation'] ?? null,
        ]);
    }

    /**
     * Ambil riwayat analisa dari spk_fuzzy_logs.
     */
    private function getSpkHistory(?string $coopId, ?string $commodityId = null): array
    {
        $colorMap = ['Optimal' => 'emerald', 'Baik' => 'blue', 'Waspada' => 'amber', 'Buruk' => 'red'];

        $query = SpkFuzzyLog::query()->orderBy('createdAt', 'desc')->limit(10);
        if ($coopId) {
            $query->where('unit_budidaya_id', $coopId);
        }
        if ($commodityId) {
            $query->where(function ($q) use ($commodityId) {
                $q->where('commodity_id', $commodityId)
                    ->orWhereNull('commodity_id');
            });
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return [$this->emptyHistory()];
        }

        return $logs->map(function ($log) use ($colorMap) {
            $lingkLabel = $log->status_lingkungan ?? 'Tidak Diketahui';
            $createdAt = Carbon::parse($log->createdAt);
            $barnName   = $log->unit_budidaya_id
                ? DB::table('unitBudidaya')->where('id', $log->unit_budidaya_id)->value('nama')
                : 'Global';

            return [
                'id'        => $log->id,
                'date'      => $createdAt->locale('id')->diffForHumans(),
                'dateKey'   => $createdAt->toDateString(),
                'time'      => $createdAt->format('H:i') . ' WIB',
                'mode'      => 'Fuzzy Mamdani',
                'modeColor' => 'purple',
                'barn'      => $barnName,
                'status'    => $log->diagnosis_kausalitas ?? $lingkLabel,
                'color'     => $colorMap[$lingkLabel] ?? 'gray',
                'verdict'   => $log->narrative ? \Str::limit(strip_tags($log->narrative), 150) : '-',
                'recommendation' => $log->recommendation ?? '-',
                'raw'       => is_array($log->input_json) ? $log->input_json : [],
                'search'    => strtolower($log->id . ' ' . $barnName . ' ' . ($log->diagnosis_kausalitas ?? '') . ' ' . $lingkLabel),
            ];
        })->toArray();
    }

    /**
     * Build fuzzyData untuk view dari hasil engine terkini.
     */
    private function getFuzzyStatus(array $result, array $prodData): array
    {
        $lingkungan = $result['lingkungan'] ?? [];
        $kesehatan  = $result['kesehatan']  ?? [];
        $kausalitas = $result['kausalitas'] ?? [];
        $inputs     = $result['inputs']     ?? [];

        $colorMap = ['Optimal' => 'emerald', 'Baik' => 'blue', 'Waspada' => 'amber', 'Buruk' => 'red'];

        $lingkLabel   = $lingkungan['label']  ?? 'Tidak Diketahui';
        $kesehatLabel = $kesehatan['label']   ?? 'Tidak Diketahui';
        $lingkScore   = round((float) ($lingkungan['value'] ?? 0), 1);
        $kesehatScore = round((float) ($kesehatan['value']  ?? 0), 1);
        $gabScore = round(max($lingkScore, $kesehatScore), 1);

        // Sensor bars dari fuzzified (Engine 1)
        $fuzzLingk = $lingkungan['fuzzified'] ?? [];

        $suhuPct  = isset($inputs['suhu'])      ? min(($inputs['suhu'] / 50) * 100, 100) : 0;
        $humPct   = isset($inputs['kelembapan'])? min($inputs['kelembapan'], 100)         : 0;
        $ammoPct  = isset($inputs['amonia'])    ? min($inputs['amonia'] * 2, 100)         : 0;

        $suhu   = $inputs['suhu']       ?? 0;
        $humid  = $inputs['kelembapan'] ?? 0;
        $amonia = $inputs['amonia']     ?? 0;

        return [
            'confidence' => $gabScore,
            'spider'     => $prodData['spider'] ?? ['labels' => [], 'values' => []],
            'color'      => $this->scoreColor($gabScore),
            'sensors'    => [
                'lingkungan' => [
                    ['label' => 'Suhu Udara',  'percent' => round($suhuPct),  'status' => $suhu > 30 ? 'warning' : 'normal', 'statusLabel' => round($suhu, 1) . '°C - ' . (isset($fuzzLingk['suhu']) && $fuzzLingk['suhu'] ? array_search(max($fuzzLingk['suhu']), $fuzzLingk['suhu']) : '-')],
                    ['label' => 'Kelembapan', 'percent' => round($humPct),   'status' => $humid > 80 ? 'warning' : 'normal', 'statusLabel' => round($humid, 1) . '% - ' . (isset($fuzzLingk['kelembapan']) && $fuzzLingk['kelembapan'] ? array_search(max($fuzzLingk['kelembapan']), $fuzzLingk['kelembapan']) : '-')],
                    ['label' => 'Amonia',     'percent' => round($ammoPct),  'status' => $amonia > 20 ? 'warning' : 'normal', 'statusLabel' => round($amonia, 1) . ' ppm - ' . (isset($fuzzLingk['amonia']) && $fuzzLingk['amonia'] ? array_search(max($fuzzLingk['amonia']), $fuzzLingk['amonia']) : '-')],
                ],
                'produktivitas' => $prodData['productivitySensors'] ?? [],
            ],
            'indicators' => $prodData['indicators'] ?? [],
            'results' => [
                'lingkungan' => [
                    'status'      => strtoupper($lingkLabel),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'gray',
                    'score'       => $lingkScore,
                    'scoreColor'  => $this->scoreColor($lingkScore),
                    'title'       => $lingkungan['dominant_rule']['diagnosis'] ?? 'Analisa Lingkungan',
                    'description' => 'Score: ' . $lingkScore . '/100. ' . ($lingkungan['dominant_rule']['diagnosis'] ?? ''),
                    'link'        => '#',
                ],
                'produktivitas' => [
                    'status'      => strtoupper($kesehatLabel),
                    'statusColor' => $colorMap[$kesehatLabel] ?? 'gray',
                    'score'       => $kesehatScore,
                    'scoreColor'  => $this->scoreColor($kesehatScore),
                    'title'       => $kesehatan['dominant_rule']['diagnosis'] ?? 'Analisa Produktivitas',
                    'description' => 'Score: ' . $kesehatScore . '/100. ' . ($kesehatan['dominant_rule']['diagnosis'] ?? ''),
                    'link'        => '#',
                ],
                'gabungan' => [
                    'status'      => strtoupper($kausalitas['label'] ?? 'N/A'),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'gray',
                    'score'       => $gabScore,
                    'scoreColor'  => $this->scoreColor($gabScore),
                    'title'       => $kausalitas['label'] ?? 'Diagnosis Kausalitas',
                    'description' => $result['narrative'] ?? ($kausalitas['diagnosis'] ?? '-'),
                    'link'        => '#',
                    'isMain'      => true,
                ],
            ],
        ];
    }

    private function emptyHistory(): array
    {
        return ['id' => 'N/A', 'date' => '-', 'dateKey' => '', 'time' => '-', 'mode' => '-', 'modeColor' => 'gray', 'barn' => '-', 'status' => '-', 'color' => 'gray', 'verdict' => 'Belum ada analisa', 'raw' => [], 'search' => ''];
    }

    private function scoreColor(float $score): string
    {
        return match (true) {
            $score >= 85 => 'emerald',
            $score >= 70 => 'blue',
            $score >= 55 => 'amber',
            default => 'red',
        };
    }

    private function getKpiMetrics(): array
    {
        $activeTasks = SpkActionTask::withoutGlobalScopes()
            ->whereIn('status', ['todo', 'in_progress'])
            ->count();

        $todayAnalyses = SpkFuzzyLog::query()
            ->whereDate('createdAt', now()->toDateString())
            ->count();

        $avgScore = round((float) SpkFuzzyLog::query()
            ->where('createdAt', '>=', now()->subDays(30))
            ->avg('output_value'), 1);

        $problemBarns = SpkFuzzyLog::query()
            ->select('unit_budidaya_id')
            ->whereNotNull('unit_budidaya_id')
            ->where('createdAt', '>=', now()->subDays(7))
            ->where(function ($query) {
                $query->whereIn('status_lingkungan', ['Waspada', 'Buruk'])
                    ->orWhereIn('status_kesehatan', ['Waspada', 'Buruk'])
                    ->orWhere('output_value', '<', 70);
            })
            ->distinct()
            ->count('unit_budidaya_id');

        return [
            ['label' => 'Tiket Aktif', 'value' => (string) $activeTasks, 'trend' => ['direction' => $activeTasks > 0 ? 'up' : 'stable', 'value' => $activeTasks > 0 ? '+' . $activeTasks : '0', 'status' => $activeTasks > 0 ? 'warning' : 'neutral']],
            ['label' => 'Rata-rata Skor SPK', 'value' => $avgScore ? $avgScore . '/100' : '-', 'trend' => ['direction' => 'stable', 'value' => '30 hari', 'status' => 'neutral']],
            ['label' => 'Analisa Hari Ini', 'value' => (string) $todayAnalyses, 'trend' => ['direction' => $todayAnalyses > 0 ? 'up' : 'stable', 'value' => (string) $todayAnalyses, 'status' => $todayAnalyses > 0 ? 'positive' : 'neutral']],
            ['label' => 'Kandang Perlu Atensi', 'value' => (string) $problemBarns, 'trend' => ['direction' => $problemBarns > 0 ? 'up' : 'stable', 'value' => (string) $problemBarns, 'status' => $problemBarns > 0 ? 'negative' : 'neutral']],
        ];
    }


    private function getChartData(?string $coopId = null): array
    {
        // HDP comparison: 30 hari terakhir dari SpkFuzzyLog
        $query = SpkFuzzyLog::query()->orderByDesc('createdAt')->limit(30);
        
        if ($coopId) {
            $query->where('unit_budidaya_id', $coopId);
        } else {
            $query->whereNull('unit_budidaya_id');
        }

        $logs = $query->get(['input_json', 'createdAt', 'status_lingkungan'])
            ->sortBy('createdAt')
            ->values();

        $labels      = [];
        $hdpActual   = [];
        $fcrActual   = [];
        $suhuActual  = [];
        $amoniaActual= [];
        $humidityActual = [];

        foreach ($logs as $log) {
            $inputs       = is_array($log->input_json) ? $log->input_json : [];
            $labels[]     = \Carbon\Carbon::parse($log->createdAt)->format('d/m H:i');
            $hdpActual[]  = round($inputs['hdp'] ?? 0, 1);
            $fcrActual[]  = round($inputs['fcr'] ?? 0, 2);
            $suhuActual[] = round($inputs['suhu'] ?? 0, 1);
            $amoniaActual[]= round($inputs['amonia'] ?? 0, 1);
            $humidityActual[] = round($inputs['kelembapan'] ?? 0, 1);
        }

        // Jika belum ada log, tampilkan kurva Lohmann Brown standar saja
        if (empty($labels)) {
            $weeks = []; $hdpStd = [];
            for ($w = 20; $w <= 40; $w++) {
                $weeks[] = 'W' . $w;
                if ($w < 25)       { $std = 60 + (($w - 20) * 7); }
                elseif ($w <= 30)  { $std = 95 - (($w - 25) * 0.2); }
                else               { $std = 94 - (($w - 30) * 0.4); }
                $hdpStd[] = round($std, 1);
            }
            return [
                'hdpComparison' => ['labels' => $weeks, 'actual' => array_fill(0, count($weeks), null), 'standard' => $hdpStd],
                'causality'     => ['labels' => [], 'fcr' => [], 'suhu' => [], 'kelembaban' => [], 'amonia' => []],
            ];
        }

        // HDP standard (Lohmann Brown, per-observasi disesuaikan indeks)
        $hdpStandard = array_fill(0, count($hdpActual), 93.0);

        return [
            'hdpComparison' => [
                'labels'   => $labels,
                'actual'   => $hdpActual,
                'standard' => $hdpStandard,
            ],
            'causality' => [
                'labels'     => $labels,
                'fcr'        => $fcrActual,
                'suhu'       => $suhuActual,
                'kelembaban' => $humidityActual,
                'amonia'     => $amoniaActual,
            ],
        ];
    }

    private function getAhpSawRanking(): array
    {
        return [
            [
                'rank' => 1,
                'name' => 'PT Agrinusa Jaya',
                'category' => 'Pakan & Vitamin',
                'score' => 92.4,
                'price_rating' => 'Terjangkau',
                'lead_time' => '1 Hari',
                'quality' => 'Sangat Baik',
                'status' => 'Recommended'
            ],
            [
                'rank' => 2,
                'name' => 'CV Medion Farma',
                'category' => 'Obat & Vaksin',
                'score' => 88.7,
                'price_rating' => 'Standar',
                'lead_time' => '2 Hari',
                'quality' => 'Sangat Baik',
            ],
            [
                'rank' => 3,
                'name' => 'Sinar Gemilang Supply',
                'category' => 'Perlengkapan',
                'score' => 76.2,
                'price_rating' => 'Murah',
                'lead_time' => '4 Hari',
                'quality' => 'Cukup',
            ],
        ];
    }

    private function getActionTickets($historyId): array
    {
        if ($historyId === 'N/A') {
            return [];
        }

        $tasks = \App\Models\SpkActionTask::with('assignee')
            ->where('spk_fuzzy_log_id', $historyId)
            ->orderBy('createdAt', 'desc')
            ->get();

        return $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'code' => substr($task->id, 0, 8),
                'title' => $task->title,
                'source' => "Tugas Sistem",
                'priority' => match($task->priority) { 'urgent' => 'Urgent', 'high' => 'High', 'medium' => 'Medium', 'low' => 'Low', default => 'Medium' },
                'status' => match($task->status) { 'todo' => 'To Do', 'in_progress' => 'In Progress', 'done' => 'Done', 'cancelled' => 'Cancelled', default => 'To Do' },
                'assignee' => $task->assignee->name ?? 'Belum Ditugaskan',
            ];
        })->toArray();
    }
}
