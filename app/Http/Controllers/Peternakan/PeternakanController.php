<?php

namespace App\Http\Controllers\Peternakan;

use App\Http\Controllers\Controller;
use App\Models\Komoditas;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use App\Models\SpkFuzzyProfile;
use App\Services\Fuzzy\FuzzySensorCardMapper;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\PeternakanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PeternakanController extends Controller
{
    public function __construct(
        protected PeternakanService $peternakanService,
        protected InputResolver $inputResolver,
        protected MamdaniEngine $mamdaniEngine,
        protected FuzzySensorCardMapper $sensorCardMapper,
        protected NarrativeGenerator $narrativeGenerator,
    ) {}

    /**
     * Dashboard utama peternakan - Decision Support & Operations.
     */
    public function index(Request $request)
    {
        $komoditasId = $request->query('komoditas');
        $chartRange = in_array($request->query('chart_range'), ['30d', '90d', 'ytd'], true)
            ? $request->query('chart_range')
            : '30d';

        $this->peternakanService->forKomoditas($komoditasId);
        $activeKomoditasId = $this->peternakanService->getActiveKomoditasId();

        $komoditas = Komoditas::where('isDeleted', 0)->orderBy('nama')->get();
        $activeKomoditas = $komoditas->firstWhere('id', $activeKomoditasId);

        $barnEnvironment = $this->peternakanService->getBarnEnvironment();
        $barns = $barnEnvironment['barns'];

        $fuzzyByBarn = $this->buildFuzzyByBarn($barns);
        $defaultBarnId = $barns[0]['id'] ?? 'all';
        $activeFuzzy = $fuzzyByBarn[$defaultBarnId] ?? $fuzzyByBarn['all'] ?? $this->emptyFuzzyPayload();

        $lastFuzzyAt = $this->peternakanService->getLastFuzzyEvaluationAt();
        $evaluationTime = $lastFuzzyAt
            ? 'Auto evaluated terakhir: '.$lastFuzzyAt->locale('id')->translatedFormat('d M Y, H:i')
            : 'Belum ada evaluasi otomatis';

        $dailyReportStatus = $this->peternakanService->getDailyReportStatus();

        return view('peternakan.dashboard', [
            'komoditas' => $komoditas,
            'activeKomoditasId' => $activeKomoditasId,
            'activeKomoditasNama' => $activeKomoditas?->nama ?? 'Komoditas',
            'chartRange' => $chartRange,
            'kpiMetrics' => $this->peternakanService->getKpiMetrics(),
            'chartData' => $this->peternakanService->getChartData($chartRange),
            'chartDataByRange' => $this->peternakanService->getChartDataByRange(),
            'barnEnvironment' => $barnEnvironment,
            'produktivitas' => $this->peternakanService->getProduktivitasData(),
            'spkResults' => $activeFuzzy['spkResults'],
            'fuzzySensors' => $activeFuzzy['fuzzySensors'],
            'fuzzyByBarn' => $fuzzyByBarn,
            'fuzzyProduktivitasByBarn' => $this->buildProduktivitasByBarn($barns),
            'productionLog' => $this->peternakanService->getProductionLog(),
            'listKandang' => $this->peternakanService->getListKandang(),
            'dailyReportStatus' => $dailyReportStatus,
            'spkDailySummary' => $this->buildDailySpkSummary($activeKomoditasId, $barns, $dailyReportStatus),
            'evaluationTime' => $evaluationTime,
            'hasKomoditas' => $komoditas->isNotEmpty(),
        ]);
    }

    /**
     * Detail halaman per-kandang.
     */
    public function show(Request $request, $id)
    {
        $this->peternakanService->forKomoditas($request->query('komoditas'));

        $barns = $this->peternakanService->getBarnEnvironment()['barns'];
        $barn = collect($barns)->first(fn ($b) => ($b['id'] ?? null) == $id);
        if (! $barn || ($barn['id'] ?? null) === 'no-data') {
            abort(404, 'Kandang tidak ditemukan untuk komoditas aktif.');
        }

        $iotDevices = $this->peternakanService->getBarnIotDevices($barn);

        return view('peternakan.show', [
            'barn' => $this->peternakanService->getBarnDetail($barn),
            'sensors' => $this->peternakanService->getBarnSensors($barn),
            'sensorTrend' => $this->peternakanService->getBarnSensorTrend($barn['id'] ?? null),
            'kpi' => $this->peternakanService->getBarnKpi($barn),
            'productionLog' => $this->peternakanService->getBarnProductionLog($barn),
            'iotDevice' => $iotDevices[0] ?? null,
            'spkMessages' => $this->peternakanService->getBarnSpkMessages($barn),
            'activityLog' => $this->peternakanService->getBarnActivityLog($barn),
            'productivityTrend' => $this->peternakanService->getProductivityTrend($barn['id']),
            'eggQuality' => $this->peternakanService->getEggQuality($barn),
            'dailyDataAudit' => $this->peternakanService->getBarnDailyDataAudit($barn),
            'activeKomoditasId' => $this->peternakanService->getActiveKomoditasId(),
        ]);
    }

    /**
     * POST /peternakan/evaluate-all - jalankan fuzzy untuk semua kandang komoditas aktif.
     */
    public function evaluateAll(Request $request): JsonResponse
    {
        $this->peternakanService->forKomoditas($request->input('komoditas_id'));

        $coopIds = $this->peternakanService->getActiveCoopIds();
        $processed = 0;
        $errors = [];

        foreach ($coopIds as $coopId) {
            try {
                $this->runAndPersistFuzzy($coopId);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = ['coop_id' => $coopId, 'message' => $e->getMessage()];
            }
        }

        try {
            $this->runAndPersistFuzzy(null);
        } catch (\Throwable $e) {
            $errors[] = ['coop_id' => null, 'message' => $e->getMessage()];
        }

        $lastAt = $this->peternakanService->getLastFuzzyEvaluationAt();

        return response()->json([
            'success' => $processed > 0 || empty($errors),
            'processed' => $processed,
            'errors' => $errors,
            'evaluation_time' => $lastAt
                ? $lastAt->locale('id')->translatedFormat('d M Y, H:i')
                : null,
        ]);
    }

    private function buildFuzzyByBarn(array $barns): array
    {
        $payload = [];

        foreach ($barns as $barn) {
            if (($barn['id'] ?? null) === 'no-data') {
                continue;
            }

            try {
                $result = $this->cachedFuzzyResult($barn['id']);
                $payload[$barn['id']] = $this->mapFuzzyToView($result, $barn);
            } catch (\Throwable $e) {
                \Log::warning('[Peternakan] Fuzzy per barn failed: '.$e->getMessage(), ['barn' => $barn['id']]);
                $payload[$barn['id']] = $this->emptyFuzzyPayload($barn);
            }
        }

        try {
            $global = $this->cachedFuzzyResult(null);
            $payload['all'] = $this->mapFuzzyToView($global, null);
        } catch (\Throwable $e) {
            $payload['all'] = $this->emptyFuzzyPayload();
        }

        return $payload;
    }

    private function buildProduktivitasByBarn(array $barns): array
    {
        $map = [];

        foreach ($barns as $barn) {
            if (($barn['id'] ?? null) === 'no-data') {
                continue;
            }
            $data = $this->cachedProduktivitasData($barn['id']);
            $map[$barn['id']] = [
                'indicators' => $data['indicators'],
                'spider' => $data['spider'],
                'productivitySensors' => $data['productivitySensors'],
            ];
        }

        $global = $this->cachedProduktivitasData(null);
        $map['all'] = [
            'indicators' => $global['indicators'],
            'spider' => $global['spider'],
            'productivitySensors' => $global['productivitySensors'],
        ];

        return $map;
    }

    private function buildDailySpkSummary(?string $commodityId, array $barns, array $dailyReportStatus): array
    {
        $query = SpkFuzzyLog::query()
            ->with('unitBudidaya')
            ->when($commodityId, function ($q) use ($commodityId) {
                $q->where(function ($inner) use ($commodityId) {
                    $inner->where('commodity_id', $commodityId)
                        ->orWhereNull('commodity_id');
                });
            });

        $todayLogs = (clone $query)
            ->whereDate('createdAt', now()->toDateString())
            ->orderByDesc('createdAt')
            ->get();

        $latestLog = (clone $query)->orderByDesc('createdAt')->first();
        $sourceLogs = $todayLogs->isNotEmpty()
            ? $todayLogs
            : collect($latestLog ? [$latestLog] : []);

        $problemLogs = $sourceLogs->filter(fn (SpkFuzzyLog $log) => $this->spkLogNeedsAction($log));

        $activeTaskCount = SpkActionTask::withoutGlobalScopes()
            ->whereIn('status', ['todo', 'in_progress'])
            ->count();

        $hints = [];
        if (! $latestLog) {
            $hints[] = 'SPK belum pernah dijalankan. Jalankan analisa SPK setelah laporan harian dan data sensor tersedia.';
        } elseif ($todayLogs->isEmpty()) {
            $hints[] = 'SPK belum menghasilkan log untuk hari ini. Ringkasan masih memakai hasil terakhir.';
        }

        if (! ($dailyReportStatus['isReady'] ?? false)) {
            $hints[] = 'Laporan harian belum lengkap, sehingga perhitungan HDP, FCR, mortalitas, dan rekomendasi tindakan bisa belum akurat.';
        }

        $requiredInputs = $this->requiredFuzzyInputs($commodityId);
        $latestInputs = is_array($latestLog?->input_json) ? $latestLog->input_json : [];
        $missingInputs = collect($requiredInputs)
            ->filter(fn (string $key) => ! array_key_exists($key, $latestInputs) || $latestInputs[$key] === null || $latestInputs[$key] === '')
            ->values();

        if ($latestLog && $missingInputs->isNotEmpty()) {
            $hints[] = 'Input SPK belum lengkap: '.$missingInputs->take(4)->implode(', ').($missingInputs->count() > 4 ? ', ...' : '').'.';
        }

        $avgScore = $sourceLogs->isNotEmpty()
            ? round((float) $sourceLogs->avg('output_value'), 1)
            : null;

        $status = match (true) {
            ! $latestLog => 'Belum Jalan',
            $problemLogs->isNotEmpty() || $activeTaskCount > 0 => 'Perlu Tindakan',
            $todayLogs->isEmpty() => 'Perlu Update',
            default => 'Terkendali',
        };

        $tone = match ($status) {
            'Perlu Tindakan' => 'red',
            'Perlu Update' => 'amber',
            'Belum Jalan' => 'gray',
            default => 'emerald',
        };

        return [
            'status' => $status,
            'tone' => $tone,
            'score' => $avgScore,
            'analyses_today' => $todayLogs->count(),
            'last_update' => $latestLog?->createdAt?->locale('id')->translatedFormat('d M Y, H:i') ?? null,
            'last_update_human' => $latestLog?->createdAt?->diffForHumans() ?? null,
            'hints' => array_values(array_unique($hints)),
            'active_tasks' => $activeTaskCount,
            'needs_action_count' => $problemLogs->count(),
            'barn_count' => collect($barns)->where('id', '!=', 'no-data')->count(),
            'action_candidates' => $problemLogs
                ->sortBy(fn (SpkFuzzyLog $log) => (float) $log->output_value)
                ->take(4)
                ->map(fn (SpkFuzzyLog $log) => $this->formatSpkActionCandidate($log, $commodityId))
                ->values()
                ->all(),
            'spk_url' => route('spk.dashboard', array_filter([
                'komoditas' => $commodityId,
            ])),
            'tasks_url' => route('spk.tasks.index', ['tab' => 'active']),
        ];
    }

    private function requiredFuzzyInputs(?string $commodityId): array
    {
        $profile = SpkFuzzyProfile::resolveForContext($commodityId);

        $inputs = DB::table('spk_fuzzy_variables')
            ->where('type', 'input')
            ->where('group', '!=', 'kausalitas')
            ->when($profile?->id, fn ($query) => $query->where('profile_id', $profile->id))
            ->pluck('name')
            ->unique()
            ->values()
            ->all();

        return ! empty($inputs)
            ? $inputs
            : ['suhu', 'kelembapan', 'amonia', 'hdp', 'mortalitas', 'pakan'];
    }

    private function spkLogNeedsAction(SpkFuzzyLog $log): bool
    {
        return in_array($log->status_lingkungan, ['Waspada', 'Buruk'], true)
            || in_array($log->status_kesehatan, ['Waspada', 'Buruk'], true)
            || in_array($log->diagnosis_kausalitas, ['Waspada', 'Buruk', 'Kritis', 'Tidak Optimal'], true)
            || (float) ($log->output_value ?? 0) < 70;
    }

    private function formatSpkActionCandidate(SpkFuzzyLog $log, ?string $commodityId): array
    {
        $score = round((float) ($log->output_value ?? 0), 1);
        $title = $log->diagnosis_kausalitas
            ?: $log->status_kesehatan
            ?: $log->status_lingkungan
            ?: 'Perlu pemeriksaan';

        $description = $log->recommendation
            ?: $log->narrative
            ?: 'Tinjau hasil SPK dan tentukan tindak lanjut petugas.';

        $params = array_filter([
            'komoditas' => $commodityId,
            'coop_id' => $log->unit_budidaya_id,
            'history_id' => $log->id,
        ]);

        return [
            'id' => $log->id,
            'barn' => $log->unitBudidaya?->nama ?? 'Global',
            'title' => $title,
            'description' => Str::limit(strip_tags($description), 140),
            'score' => $score,
            'priority' => $score < 55 ? 'Urgent' : 'Tinggi',
            'time' => $log->createdAt?->format('H:i') ?? '-',
            'spk_url' => route('spk.dashboard', $params),
            'task_url' => route('spk.tasks.index', array_filter([
                'create_task' => 1,
                'spk_id' => $log->id,
                'coop_id' => $log->unit_budidaya_id,
                'desc' => Str::limit(strip_tags($description), 160),
            ])),
        ];
    }

    private function runFuzzyEngine(?string $coopId): array
    {
        $commodityId = $this->peternakanService->getActiveKomoditasId();
        $profile = SpkFuzzyProfile::resolveForContext($commodityId, $coopId);
        $inputs = $this->inputResolver->resolve($coopId, $commodityId, $profile?->id);
        $result = $this->mamdaniEngine->processCascaded($inputs, $profile?->id, $commodityId, $coopId);
        $barnName = $coopId ? DB::table('unitBudidaya')->where('id', $coopId)->value('nama') : null;
        $narrative = $this->narrativeGenerator->generate($result, $barnName);

        return array_merge($result, ['narrative' => $narrative, 'inputs' => $inputs]);
    }

    private function runAndPersistFuzzy(?string $coopId): SpkFuzzyLog
    {
        $result = $this->runFuzzyEngine($coopId);
        $barnName = $coopId ? DB::table('unitBudidaya')->where('id', $coopId)->value('nama') : null;
        Cache::forget($this->cacheKey('fuzzy', $coopId));

        return SpkFuzzyLog::create([
            'unit_budidaya_id' => $coopId,
            'profile_id' => $result['profile']['id'] ?? null,
            'commodity_id' => $result['profile']['commodity_id'] ?? $this->peternakanService->getActiveKomoditasId(),
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
            'output_value' => min((float) ($result['lingkungan']['value'] ?? 0), (float) ($result['kesehatan']['value'] ?? 0)),
            'output_label' => $result['kausalitas']['label'] ?? null,
            'narrative' => $result['narrative'] ?? null,
            'recommendation' => $result['kausalitas']['recommendation'] ?? null,
        ]);
    }

    private function mapFuzzyToView(array $result, ?array $barn): array
    {
        $colorMap = ['Optimal' => 'emerald', 'Baik' => 'blue', 'Waspada' => 'amber', 'Buruk' => 'red'];
        $lingkungan = $result['lingkungan'] ?? [];
        $kesehatan = $result['kesehatan'] ?? [];
        $kausalitas = $result['kausalitas'] ?? [];
        $inputs = $result['inputs'] ?? [];

        $lingkLabel = $lingkungan['label'] ?? 'Tidak Diketahui';
        $kesehatLabel = $kesehatan['label'] ?? 'Tidak Diketahui';
        $lingkScore = round((float) ($lingkungan['value'] ?? 0), 1);
        $kesehatScore = round((float) ($kesehatan['value'] ?? 0), 1);
        $gabScore = round(min($lingkScore, $kesehatScore), 1);
        $sensorCards = $this->sensorCardMapper->fromResult($result);
        $spkLink = route('spk.dashboard', array_filter([
            'komoditas' => $this->peternakanService->getActiveKomoditasId(),
            'coop_id' => $barn['id'] ?? null,
        ]));

        $fuzzLingk = $lingkungan['fuzzified'] ?? [];
        $suhuPct = isset($inputs['suhu']) ? min(($inputs['suhu'] / 50) * 100, 100) : 0;
        $humPct = isset($inputs['kelembapan']) ? min($inputs['kelembapan'], 100) : 0;
        $ammoPct = isset($inputs['amonia']) ? min($inputs['amonia'] * 2, 100) : 0;
        $suhu = $inputs['suhu'] ?? 0;
        $humid = $inputs['kelembapan'] ?? 0;
        $amonia = $inputs['amonia'] ?? 0;

        $envSensors = [
            ['label' => 'Suhu Udara',  'percent' => round($suhuPct),  'status' => $suhu > 30 ? 'warning' : 'normal', 'statusLabel' => round($suhu, 1).'°C - '.(isset($fuzzLingk['suhu']) && $fuzzLingk['suhu'] ? array_search(max($fuzzLingk['suhu']), $fuzzLingk['suhu']) : '-')],
            ['label' => 'Kelembapan', 'percent' => round($humPct),   'status' => $humid > 80 ? 'warning' : 'normal', 'statusLabel' => round($humid, 1).'% - '.(isset($fuzzLingk['kelembapan']) && $fuzzLingk['kelembapan'] ? array_search(max($fuzzLingk['kelembapan']), $fuzzLingk['kelembapan']) : '-')],
            ['label' => 'Amonia',     'percent' => round($ammoPct),  'status' => $amonia > 20 ? 'warning' : 'normal', 'statusLabel' => round($amonia, 1).' ppm - '.(isset($fuzzLingk['amonia']) && $fuzzLingk['amonia'] ? array_search(max($fuzzLingk['amonia']), $fuzzLingk['amonia']) : '-')],
        ];

        $productivityCards = $sensorCards['produktivitas'] ?? [];

        return [
            'fuzzySensors' => [
                'lingkungan' => $sensorCards['lingkungan'] ?? [],
                'produktivitas' => $productivityCards,
            ],
            'spkResults' => [
                'lingkungan' => [
                    'status' => strtoupper($lingkLabel),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'gray',
                    'score' => $lingkScore,
                    'scoreColor' => $this->scoreColor($lingkScore),
                    'title' => $lingkungan['dominant_rule']['diagnosis'] ?? 'Analisa Lingkungan',
                    'description' => 'Score: '.$lingkScore.'/100. '.($lingkungan['dominant_rule']['diagnosis'] ?? ''),
                    'link' => '#',
                ],
                'produktivitas' => [
                    'status' => strtoupper($kesehatLabel),
                    'statusColor' => $colorMap[$kesehatLabel] ?? 'gray',
                    'score' => $kesehatScore,
                    'scoreColor' => $this->scoreColor($kesehatScore),
                    'title' => $kesehatan['dominant_rule']['diagnosis'] ?? 'Analisa Produktivitas',
                    'description' => 'Score: '.$kesehatScore.'/100. '.($kesehatan['dominant_rule']['diagnosis'] ?? ''),
                    'link' => '#',
                ],
                'gabungan' => [
                    'status' => strtoupper($kausalitas['label'] ?? 'N/A'),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'emerald',
                    'score' => $gabScore,
                    'scoreColor' => $this->scoreColor($gabScore),
                    'title' => $kausalitas['label'] ?? 'Diagnosis Kausalitas',
                    'description' => $result['narrative'] ?? ($kausalitas['diagnosis'] ?? '-'),
                    'link' => $spkLink,
                    'isMain' => true,
                ],
            ],
            'spider' => $this->sensorCardMapper->toSpider($productivityCards),
            'indicators' => $this->sensorCardMapper->toIndicators($productivityCards),
        ];
    }

    private function emptyFuzzyPayload(?array $barn = null): array
    {
        $fallback = $this->peternakanService->getSpkResults();
        $prod = $this->cachedProduktivitasData($barn['id'] ?? null);

        return [
            'fuzzySensors' => [
                'lingkungan' => $barn['sensors'] ?? [],
                'produktivitas' => $prod['productivitySensors'],
            ],
            'spkResults' => $fallback,
            'spider' => $prod['spider'],
            'indicators' => $prod['indicators'],
        ];
    }

    private function cachedFuzzyResult(?string $coopId): array
    {
        return Cache::remember(
            $this->cacheKey('fuzzy', $coopId),
            60,
            fn () => $this->runFuzzyEngine($coopId)
        );
    }

    private function cachedProduktivitasData(?string $coopId): array
    {
        return Cache::remember(
            $this->cacheKey('produktivitas', $coopId),
            60,
            fn () => $this->peternakanService->getProduktivitasData($coopId)
        );
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

    private function cacheKey(string $segment, ?string $coopId): string
    {
        $komoditas = $this->peternakanService->getActiveKomoditasId() ?? 'default';
        $scope = $coopId ?: 'all';

        return "peternakan:{$komoditas}:{$segment}:{$scope}";
    }
}
