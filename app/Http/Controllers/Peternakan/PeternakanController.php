<?php

namespace App\Http\Controllers\Peternakan;

use App\Http\Controllers\Controller;
use App\Models\Komoditas;
use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\PeternakanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeternakanController extends Controller
{
    public function __construct(
        protected PeternakanService $peternakanService,
        protected InputResolver $inputResolver,
        protected MamdaniEngine $mamdaniEngine,
        protected NarrativeGenerator $narrativeGenerator,
    ) {}

    /**
     * Dashboard utama peternakan — Decision Support & Operations.
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
            ? 'Auto evaluated terakhir: ' . $lastFuzzyAt->locale('id')->translatedFormat('d M Y, H:i')
            : 'Belum ada evaluasi otomatis';

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
        $barn = collect($barns)->first(fn ($b) => $b['id'] == $id) ?? $barns[0];
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
            'productivityTrend' => $this->peternakanService->getProductivityTrend(),
            'eggQuality' => $this->peternakanService->getEggQuality($barn),
            'activeKomoditasId' => $this->peternakanService->getActiveKomoditasId(),
        ]);
    }

    /**
     * POST /peternakan/evaluate-all — jalankan fuzzy untuk semua kandang komoditas aktif.
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
                $result = $this->runFuzzyEngine($barn['id']);
                $payload[$barn['id']] = $this->mapFuzzyToView($result, $barn);
            } catch (\Throwable $e) {
                \Log::warning('[Peternakan] Fuzzy per barn failed: ' . $e->getMessage(), ['barn' => $barn['id']]);
                $payload[$barn['id']] = $this->emptyFuzzyPayload($barn);
            }
        }

        try {
            $global = $this->runFuzzyEngine(null);
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
            $data = $this->peternakanService->getProduktivitasData($barn['id']);
            $map[$barn['id']] = [
                'indicators' => $data['indicators'],
                'spider' => $data['spider'],
                'productivitySensors' => $data['productivitySensors'],
            ];
        }

        $global = $this->peternakanService->getProduktivitasData();
        $map['all'] = [
            'indicators' => $global['indicators'],
            'spider' => $global['spider'],
            'productivitySensors' => $global['productivitySensors'],
        ];

        return $map;
    }

    private function runFuzzyEngine(?string $coopId): array
    {
        $inputs = $this->inputResolver->resolve($coopId);
        $result = $this->mamdaniEngine->processCascaded($inputs);
        $barnName = $coopId ? DB::table('unitBudidaya')->where('id', $coopId)->value('nama') : null;
        $narrative = $this->narrativeGenerator->generate($result, $barnName);

        return array_merge($result, ['narrative' => $narrative, 'inputs' => $inputs]);
    }

    private function runAndPersistFuzzy(?string $coopId): SpkFuzzyLog
    {
        $result = $this->runFuzzyEngine($coopId);
        $barnName = $coopId ? DB::table('unitBudidaya')->where('id', $coopId)->value('nama') : null;

        return SpkFuzzyLog::create([
            'unit_budidaya_id' => $coopId,
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

    private function mapFuzzyToView(array $result, ?array $barn): array
    {
        $colorMap = ['Optimal' => 'emerald', 'Baik' => 'blue', 'Waspada' => 'amber', 'Buruk' => 'red'];
        $lingkungan = $result['lingkungan'] ?? [];
        $kesehatan = $result['kesehatan'] ?? [];
        $kausalitas = $result['kausalitas'] ?? [];
        $inputs = $result['inputs'] ?? [];

        $lingkLabel = $lingkungan['label'] ?? 'Tidak Diketahui';
        $kesehatLabel = $kesehatan['label'] ?? 'Tidak Diketahui';

        $fuzzLingk = $lingkungan['fuzzified'] ?? [];
        $suhuPct  = isset($inputs['suhu'])      ? min(($inputs['suhu'] / 50) * 100, 100) : 0;
        $humPct   = isset($inputs['kelembapan'])? min($inputs['kelembapan'], 100)         : 0;
        $ammoPct  = isset($inputs['amonia'])    ? min($inputs['amonia'] * 2, 100)         : 0;
        $suhu   = $inputs['suhu']       ?? 0;
        $humid  = $inputs['kelembapan'] ?? 0;
        $amonia = $inputs['amonia']     ?? 0;

        $envSensors = [
            ['label' => 'Suhu Udara',  'percent' => round($suhuPct),  'status' => $suhu > 30 ? 'warning' : 'normal', 'statusLabel' => round($suhu, 1) . '°C — ' . (isset($fuzzLingk['suhu']) && $fuzzLingk['suhu'] ? array_search(max($fuzzLingk['suhu']), $fuzzLingk['suhu']) : '-')],
            ['label' => 'Kelembapan', 'percent' => round($humPct),   'status' => $humid > 80 ? 'warning' : 'normal', 'statusLabel' => round($humid, 1) . '% — ' . (isset($fuzzLingk['kelembapan']) && $fuzzLingk['kelembapan'] ? array_search(max($fuzzLingk['kelembapan']), $fuzzLingk['kelembapan']) : '-')],
            ['label' => 'Amonia',     'percent' => round($ammoPct),  'status' => $amonia > 20 ? 'warning' : 'normal', 'statusLabel' => round($amonia, 1) . ' ppm — ' . (isset($fuzzLingk['amonia']) && $fuzzLingk['amonia'] ? array_search(max($fuzzLingk['amonia']), $fuzzLingk['amonia']) : '-')],
        ];

        $prodData = $this->peternakanService->getProduktivitasData($barn['id'] ?? null);

        return [
            'fuzzySensors' => [
                'lingkungan' => $envSensors,
                'produktivitas' => $prodData['productivitySensors'],
            ],
            'spkResults' => [
                'lingkungan' => [
                    'status' => strtoupper($lingkLabel),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'gray',
                    'title' => $lingkungan['dominant_rule']['diagnosis'] ?? 'Analisa Lingkungan',
                    'description' => 'Score: ' . round((float) ($lingkungan['value'] ?? 0), 1) . '/100. ' . ($lingkungan['dominant_rule']['diagnosis'] ?? ''),
                    'link' => '#',
                ],
                'produktivitas' => [
                    'status' => strtoupper($kesehatLabel),
                    'statusColor' => $colorMap[$kesehatLabel] ?? 'gray',
                    'title' => $kesehatan['dominant_rule']['diagnosis'] ?? 'Analisa Produktivitas',
                    'description' => 'Score: ' . round((float) ($kesehatan['value'] ?? 0), 1) . '/100. ' . ($kesehatan['dominant_rule']['diagnosis'] ?? ''),
                    'link' => '#',
                ],
                'gabungan' => [
                    'status' => strtoupper($kausalitas['label'] ?? 'N/A'),
                    'statusColor' => $colorMap[$lingkLabel] ?? 'emerald',
                    'title' => $kausalitas['label'] ?? 'Diagnosis Kausalitas',
                    'description' => $result['narrative'] ?? ($kausalitas['diagnosis'] ?? '-'),
                    'link' => route('spk.dashboard'),
                    'isMain' => true,
                ],
            ],
            'spider' => $prodData['spider'],
            'indicators' => $prodData['indicators'],
        ];
    }

    private function emptyFuzzyPayload(?array $barn = null): array
    {
        $fallback = $this->peternakanService->getSpkResults();
        $prod = $this->peternakanService->getProduktivitasData($barn['id'] ?? null);

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
}
