<?php

namespace App\Http\Controllers\Peternakan;

use App\Http\Controllers\Controller;
use App\Models\SpkActionRecommendation;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use App\Models\SpkFuzzyProfile;
use App\Services\Fuzzy\FuzzySensorCardMapper;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\Health\BarnHealthContextService;
use App\Services\Health\NodeHealthIndicationClient;
use App\Services\LivestockMasterConfigService;
use App\Services\Notifications\LivestockCycleAlertService;
use App\Services\Notifications\SpkEnvironmentAlertService;
use App\Services\PeternakanService;
use App\Services\Spk\SpkActionRecommendationService;
use App\Services\Spk\SpkFuzzyEvaluationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PeternakanController extends Controller
{
    private ?array $activeAfkirCycleConfig = null;

    public function __construct(
        protected PeternakanService $peternakanService,
        protected InputResolver $inputResolver,
        protected MamdaniEngine $mamdaniEngine,
        protected FuzzySensorCardMapper $sensorCardMapper,
        protected NarrativeGenerator $narrativeGenerator,
        protected SpkFuzzyEvaluationService $fuzzyEvaluationService,
        protected SpkEnvironmentAlertService $environmentAlertService,
        protected LivestockCycleAlertService $livestockCycleAlertService,
        protected BarnHealthContextService $barnHealthContextService,
        protected NodeHealthIndicationClient $nodeHealthIndicationClient,
        protected LivestockMasterConfigService $livestockMasterConfigService,
        protected SpkActionRecommendationService $actionRecommendationService,
    ) {}

    private function applyLivestockContext(Request $request, bool $fromInput = false): void
    {
        $this->activeAfkirCycleConfig = null;
        $jenisTernakId = $fromInput ? $request->input('jenis_ternak_id') : $request->query('jenis_ternak');
        $komoditasId = $fromInput ? $request->input('komoditas_id') : $request->query('komoditas');

        if ($jenisTernakId) {
            $this->peternakanService->forJenisTernak($jenisTernakId, $komoditasId);

            return;
        }

        $this->peternakanService->forKomoditas($komoditasId);
    }

    /**
     * Dashboard utama peternakan - Decision Support & Operations.
     */
    public function index(Request $request)
    {
        $chartRange = in_array($request->query('chart_range'), ['30d', '90d', 'ytd'], true)
            ? $request->query('chart_range')
            : '30d';

        $this->applyLivestockContext($request);
        $activeKomoditasId = $this->peternakanService->getActiveKomoditasId();
        $activeJenisTernakId = $this->peternakanService->getActiveJenisBudidayaId();

        $komoditas = $this->livestockMasterConfigService->livestockCommodities();
        $activeKomoditas = $komoditas->firstWhere('id', $activeKomoditasId);
        $jenisTernakOptions = $this->livestockMasterConfigService->livestockTypeOptions();
        $activeJenisTernak = $jenisTernakOptions->firstWhere('id', $activeJenisTernakId);
        $activeJenisTernakNama = $activeJenisTernak?->nama
            ?? $activeKomoditas?->jenis_budidaya_nama
            ?? $activeKomoditas?->nama
            ?? 'Jenis ternak';
        $masterConfigStatus = $this->livestockMasterConfigService->summaryForCommodity($activeKomoditasId);
        $masterConfigStatus['data_master_url'] = route('data-master.index', array_filter([
            'jenis_budidaya_id' => $masterConfigStatus['jenis_budidaya_id'] ?? null,
        ]));
        $masterConfigStatus['spk_config_url'] = route('settings.fuzzy.index', array_filter([
            'profile_id' => data_get($masterConfigStatus, 'spk_profile.id'),
        ]));

        $barnEnvironment = $this->peternakanService->getBarnEnvironment();
        $barns = $barnEnvironment['barns'];
        $spkConfigured = (bool) ($masterConfigStatus['spk_configured'] ?? false);

        $fuzzyByBarn = $spkConfigured
            ? $this->buildFuzzyByBarn($barns)
            : $this->buildMasterBlockedFuzzyByBarn($barns, $masterConfigStatus);
        $defaultBarnId = $barns[0]['id'] ?? 'all';
        $activeFuzzy = $fuzzyByBarn[$defaultBarnId] ?? $fuzzyByBarn['all'] ?? $this->emptyFuzzyPayload();

        $lastFuzzyAt = $this->peternakanService->getLastFuzzyEvaluationAt();
        $evaluationTime = $lastFuzzyAt
            ? 'Auto evaluated terakhir: '.$lastFuzzyAt->locale('id')->translatedFormat('d M Y, H:i')
            : 'Belum ada evaluasi otomatis';

        $dailyReportStatus = $this->peternakanService->getDailyReportStatus();
        $spkDailySummary = $spkConfigured
            ? $this->buildDailySpkSummary($activeKomoditasId, $barns, $dailyReportStatus)
            : $this->masterBlockedSpkSummary($activeKomoditasId, $barns, $masterConfigStatus);

        return view('peternakan.dashboard', [
            'komoditas' => $komoditas,
            'jenisTernakOptions' => $jenisTernakOptions,
            'activeKomoditasId' => $activeKomoditasId,
            'activeJenisTernakId' => $activeJenisTernakId,
            'activeJenisTernakNama' => $activeJenisTernakNama,
            'activeKomoditasNama' => $activeJenisTernakNama,
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
            'spkDailySummary' => $spkDailySummary,
            'evaluationTime' => $evaluationTime,
            'hasKomoditas' => $komoditas->isNotEmpty(),
            'hasJenisTernak' => $jenisTernakOptions->isNotEmpty(),
            'masterConfigStatus' => $masterConfigStatus,
        ]);
    }

    /**
     * Detail halaman per-kandang.
     */
    public function show(Request $request, $id)
    {
        $this->applyLivestockContext($request);

        $barns = $this->peternakanService->getBarnEnvironment()['barns'];
        $barn = collect($barns)->first(fn ($b) => ($b['id'] ?? null) == $id);
        if (! $barn || ($barn['id'] ?? null) === 'no-data') {
            abort(404, 'Kandang tidak ditemukan untuk jenis ternak aktif.');
        }

        $iotDevices = $this->peternakanService->getBarnIotDevices($barn);

        $barnDetail = $this->peternakanService->getBarnDetail($barn);
        $kpi = $this->peternakanService->getBarnKpi($barn);
        $eggProductionDropResponse = $this->nodeHealthIndicationClient->eggProductionDropContext($barn['id'], [
            'days' => 7,
            'thresholdPercent' => 40,
        ]);
        $eggProductionDropContext = $eggProductionDropResponse['success'] ?? false
            ? ($eggProductionDropResponse['data'] ?? null)
            : null;

        return view('peternakan.show', [
            'barn' => $barnDetail,
            'sensors' => $this->peternakanService->getBarnSensors($barn),
            'sensorTrend' => $this->peternakanService->getBarnSensorTrend($barn['id'] ?? null),
            'kpi' => $kpi,
            'productionLog' => $this->peternakanService->getBarnProductionLog($barn),
            'iotDevice' => $iotDevices[0] ?? null,
            'spkMessages' => $this->peternakanService->getBarnSpkMessages($barn),
            'activityLog' => $this->peternakanService->getBarnActivityLog($barn),
            'productivityTrend' => $this->peternakanService->getProductivityTrend($barn['id']),
            'eggQuality' => $this->peternakanService->getEggQuality($barn),
            'dailyDataAudit' => $this->peternakanService->getBarnDailyDataAudit($barn),
            'healthContext' => $this->barnHealthContextService->forBarn($barn['id'], $kpi, $barnDetail['name'] ?? null, $eggProductionDropContext),
            'eggProductionDropError' => ($eggProductionDropResponse['success'] ?? false) ? null : ($eggProductionDropResponse['message'] ?? null),
            'activeKomoditasId' => $this->peternakanService->getActiveKomoditasId(),
            'activeJenisTernakId' => $this->peternakanService->getActiveJenisBudidayaId(),
            'activeProductivityCodes' => $this->peternakanService->activeProductivityFunctionCodes(),
        ]);
    }

    public function individualProductivity(Request $request, $id)
    {
        $this->applyLivestockContext($request);

        $barns = $this->peternakanService->getBarnEnvironment()['barns'];
        $barn = collect($barns)->first(fn ($b) => ($b['id'] ?? null) == $id);
        if (! $barn || ($barn['id'] ?? null) === 'no-data') {
            abort(404, 'Kandang tidak ditemukan untuk jenis ternak aktif.');
        }

        $allowedProductivityPeriods = [7, 14, 30, 90, 180, 365];
        $days = in_array((int) $request->query('days'), $allowedProductivityPeriods, true)
            ? (int) $request->query('days')
            : 7;
        $threshold = (float) $request->query('threshold', 40);
        $threshold = $threshold >= 1 && $threshold <= 100 ? $threshold : 40;
        $sort = in_array($request->query('sort'), ['drop', 'drop_points', 'current', 'previous', 'non_laying', 'name'], true)
            ? $request->query('sort')
            : 'drop';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $filter = in_array($request->query('filter'), ['all', 'indication'], true)
            ? $request->query('filter')
            : 'all';

        $response = $this->nodeHealthIndicationClient->individualEggProductivity($barn['id'], [
            'days' => $days,
            'thresholdPercent' => $threshold,
            'sort' => $sort,
            'direction' => $direction,
        ]);

        $productivity = ($response['success'] ?? false) ? ($response['data'] ?? []) : [];
        $enrichedRows = $this->enrichIndividualRowsWithLifecycle(
            (string) ($barn['id'] ?? ''),
            data_get($productivity, 'rows', []),
            now()
        );
        $this->dispatchLivestockCycleAlert($barn, $enrichedRows);

        $rows = collect($enrichedRows);

        if ($filter === 'indication') {
            $rows = $rows->filter(fn ($row) => (bool) data_get($row, 'isDropIndication'));
        }

        return view('peternakan.individual-productivity', [
            'barn' => $this->peternakanService->getBarnDetail($barn),
            'productivity' => $productivity,
            'rows' => $rows->values()->all(),
            'afkirConfig' => $this->activeAfkirCycleConfig(),
            'filters' => [
                'days' => $days,
                'threshold' => $threshold,
                'sort' => $sort,
                'direction' => $direction,
                'filter' => $filter,
            ],
            'error' => ($response['success'] ?? false) ? null : ($response['message'] ?? 'Data produktivitas individu belum bisa dibaca.'),
            'activeKomoditasId' => $this->peternakanService->getActiveKomoditasId(),
            'activeJenisTernakId' => $this->peternakanService->getActiveJenisBudidayaId(),
        ]);
    }

    public function storeHealthIndication(Request $request, $id)
    {
        abort_unless(
            in_array(session('user.role'), ['pjawab', 'owner', 'admin'], true),
            403,
            'Hanya penanggung jawab, owner, atau admin yang dapat membuat indikasi pemeriksaan kesehatan.'
        );

        $analysisMode = $request->input('analysis_mode') === 'individual_productivity_drop'
            ? 'individual_productivity_drop'
            : null;
        $days = in_array((int) $request->input('days'), [7, 14, 30, 90, 180, 365], true)
            ? (int) $request->input('days')
            : 7;
        $threshold = (float) $request->input('threshold', 40);
        $threshold = $threshold >= 1 && $threshold <= 100 ? $threshold : 40;

        $result = $this->nodeHealthIndicationClient->createAutomaticHealthIndication($id, [
            'days' => $days,
            'thresholdPercent' => $threshold,
            'analysisMode' => $analysisMode,
            'sort' => $request->input('sort'),
            'direction' => $request->input('direction'),
            'userId' => data_get(session('user'), 'id'),
            'source' => $analysisMode === 'individual_productivity_drop'
                ? 'laravel-spk-individual-productivity'
                : 'laravel-spk',
            'notify' => true,
            'targetRole' => 'petugas',
        ]);

        if (! ($result['success'] ?? false)) {
            return back()->with('health_indication_error', $result['message'] ?? 'Gagal membuat indikasi pemeriksaan kesehatan.');
        }

        $data = $result['data'] ?? [];
        if (($data['created'] ?? false) === true) {
            $affectedCount = (int) data_get($data, 'indication.affectedObjectCount', 0);
            $objectText = $affectedCount > 0
                ? " untuk {$affectedCount} ayam terindikasi"
                : '';

            return back()->with('health_indication_success', "Indikasi pemeriksaan kesehatan{$objectText} berhasil dibuat dan notifikasi dikirim ke mobile petugas.");
        }

        $reason = $data['reason'] ?? null;
        $message = match ($reason) {
            'BELOW_THRESHOLD' => 'Belum dibuat karena persentase ayam tidak bertelur belum melewati ambang 40%.',
            'DUPLICATE_PERIOD' => 'Indikasi untuk periode ini sudah pernah dibuat.',
            default => 'Request diproses, tetapi indikasi baru tidak dibuat.',
        };

        return back()->with('health_indication_warning', $message);
    }

    public function exportProductivity(Request $request, $id)
    {
        return redirect()->route('peternakan.settlement', array_filter([
            'id' => $id,
            'jenis_ternak' => $request->query('jenis_ternak'),
            'komoditas' => $request->query('komoditas'),
            'start_date' => $request->query('start_date'),
            'end_date' => $request->query('end_date'),
            'performance' => $request->query('performance'),
            'afkir' => $request->query('afkir'),
            'format' => $request->query('format'),
        ]));
    }

    public function settlement(Request $request, $id)
    {
        if (! in_array(session('user.role'), ['pjawab', 'owner', 'admin'], true)) {
            abort(403, 'Settlement laporan produktivitas hanya tersedia untuk owner/admin.');
        }

        $this->applyLivestockContext($request);
        $activeKomoditasId = $this->peternakanService->getActiveKomoditasId();

        $barns = $this->peternakanService->getBarnEnvironment()['barns'];
        $barn = collect($barns)->first(fn ($b) => ($b['id'] ?? null) == $id);
        if (! $barn || ($barn['id'] ?? null) === 'no-data') {
            abort(404, 'Kandang tidak ditemukan untuk jenis ternak aktif.');
        }

        $barn = $this->peternakanService->getBarnDetail($barn);
        $start = $this->parseReportDate($request->query('start_date'), now()->subDays(30));
        $end = $this->parseReportDate($request->query('end_date'), now());

        if ($start->gt($end)) {
            [$start, $end] = [$end, $start];
        }

        $settlementFilters = $this->normalizeSettlementFilters($request);
        $report = $this->peternakanService->getBarnProductivityHistoryReport(
            $barn,
            $start->toDateString(),
            $end->toDateString()
        );
        $individualReport = $this->individualSettlementReport($barn, $start, $end, $settlementFilters);

        if ($request->query('format') === 'csv') {
            return $this->downloadSettlementCsv($barn, $report, $individualReport, $start->toDateString(), $end->toDateString());
        }

        return view('peternakan.settlement', [
            'barn' => $barn,
            'report' => $report,
            'individualReport' => $individualReport,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'activeKomoditasId' => $activeKomoditasId,
            'activeJenisTernakId' => $this->peternakanService->getActiveJenisBudidayaId(),
            'settlementFilters' => $settlementFilters,
        ]);
    }

    private function normalizeSettlementFilters(Request $request): array
    {
        $performance = in_array($request->query('performance'), ['all', 'warning', 'attention', 'normal', 'low_hdp', 'high_hdp'], true)
            ? $request->query('performance')
            : 'all';
        $afkir = in_array($request->query('afkir'), ['all', 'normal', 'due_soon', 'overdue'], true)
            ? $request->query('afkir')
            : 'all';

        return compact('performance', 'afkir');
    }

    private function individualSettlementReport(array $barn, Carbon $start, Carbon $end, array $filters = []): ?array
    {
        if (strtolower((string) ($barn['type'] ?? '')) !== 'individu') {
            return null;
        }

        $days = max(1, $start->diffInDays($end) + 1);
        $response = $this->nodeHealthIndicationClient->individualEggProductivity($barn['id'], [
            'days' => $days,
            'thresholdPercent' => 40,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
            'sort' => 'name',
            'direction' => 'asc',
        ]);

        if (! ($response['success'] ?? false)) {
            return [
                'error' => $response['message'] ?? 'Data performa individu belum tersedia.',
                'rows' => [],
                'summary' => [],
                'afkir_config' => $this->activeAfkirCycleConfig(),
            ];
        }

        $rows = $this->enrichIndividualRowsWithLifecycle(
            (string) ($barn['id'] ?? ''),
            data_get($response, 'data.rows', []),
            $end
        );
        $this->dispatchLivestockCycleAlert($barn, $rows);
        $totalBeforeFilter = count($rows);
        $rows = $this->filterIndividualSettlementRows($rows, $filters);
        $rows = $this->sortIndividualSettlementRows($rows);

        return [
            'error' => null,
            'rows' => $rows,
            'summary' => $response['data'] ?? [],
            'filters' => $filters,
            'total_before_filter' => $totalBeforeFilter,
            'afkir_config' => $this->activeAfkirCycleConfig(),
        ];
    }

    private function sortIndividualSettlementRows(array $rows): array
    {
        return collect($rows)
            ->sort(function ($a, $b) {
                $labelA = $this->individualSettlementRowLabel($a);
                $labelB = $this->individualSettlementRowLabel($b);
                $labelCompare = strnatcasecmp($labelA, $labelB);

                if ($labelCompare !== 0) {
                    return $labelCompare;
                }

                return strnatcasecmp((string) data_get($a, 'id'), (string) data_get($b, 'id'));
            })
            ->values()
            ->all();
    }

    private function individualSettlementRowLabel(mixed $row): string
    {
        return trim((string) (data_get($row, 'namaId') ?: data_get($row, 'id')));
    }

    private function enrichIndividualRowsWithLifecycle(string $barnId, array $rows, Carbon $referenceDate): array
    {
        if ($rows === []) {
            return [];
        }

        $objects = collect();

        try {
            if ($barnId !== '' && Schema::hasTable('objekBudidaya')) {
                $ids = collect($rows)
                    ->map(fn ($row) => data_get($row, 'id'))
                    ->filter()
                    ->unique()
                    ->values();

                if ($ids->isNotEmpty()) {
                    $columns = collect(['namaId', 'createdAt', 'tanggalMasuk', 'umurMasukMinggu', 'targetAfkirAt', 'batchKode'])
                        ->filter(fn ($column) => Schema::hasColumn('objekBudidaya', $column))
                        ->prepend('id')
                        ->values()
                        ->all();
                    $unitColumn = Schema::hasColumn('objekBudidaya', 'unitBudidayaId')
                        ? 'unitBudidayaId'
                        : (Schema::hasColumn('objekBudidaya', 'UnitBudidayaId') ? 'UnitBudidayaId' : null);

                    $query = DB::table('objekBudidaya')
                        ->select($columns)
                        ->whereIn('id', $ids);

                    if ($unitColumn) {
                        $query->where($unitColumn, $barnId);
                    }

                    if (Schema::hasColumn('objekBudidaya', 'isDeleted')) {
                        $query->where('isDeleted', false);
                    }

                    $objects = $query->get()->keyBy('id');
                }
            }
        } catch (\Throwable) {
            $objects = collect();
        }

        return collect($rows)
            ->map(function ($row) use ($objects, $referenceDate) {
                $row = is_array($row) ? $row : (array) $row;
                $meta = $objects->get((string) data_get($row, 'id'));
                $row['lifecycle'] = $this->buildLifecycleMeta($row, $meta, $referenceDate);

                return $row;
            })
            ->values()
            ->all();
    }

    private function buildLifecycleMeta(array $row, mixed $meta, Carbon $referenceDate): array
    {
        $afkirConfig = $this->activeAfkirCycleConfig();
        $targetWeeks = is_numeric($afkirConfig['target_weeks'] ?? null)
            ? max(1, (int) $afkirConfig['target_weeks'])
            : null;
        $warningWeeks = is_numeric($afkirConfig['warning_weeks'] ?? null)
            ? max(0, (int) $afkirConfig['warning_weeks'])
            : 8;
        $afkirLabel = trim((string) ($afkirConfig['label'] ?? 'Afkir / akhir siklus'));
        $afkirLabel = $afkirLabel !== '' ? $afkirLabel : 'Afkir / akhir siklus';
        $existing = (array) data_get($row, 'lifecycle', []);
        $entryDate = $this->parseNullableCarbon(data_get($meta, 'tanggalMasuk'))
            ?? $this->parseNullableCarbon(data_get($existing, 'entryDate'))
            ?? $this->parseNullableCarbon(data_get($meta, 'createdAt'));
        $entryAgeWeeks = $this->nonNegativeInteger(data_get($meta, 'umurMasukMinggu'), data_get($existing, 'entryAgeWeeks', 0));
        $ageWeeks = data_get($existing, 'ageWeeks');

        if ($entryDate) {
            $ageWeeks = max(0, $entryAgeWeeks + (int) floor($entryDate->diffInDays($referenceDate, false) / 7));
        } else {
            $ageWeeks = $this->nonNegativeInteger($ageWeeks, 0);
        }

        $targetAfkirAt = $this->parseNullableCarbon(data_get($meta, 'targetAfkirAt'))
            ?? $this->parseNullableCarbon(data_get($existing, 'targetAfkirDate'));

        if (! $targetAfkirAt && $entryDate && $targetWeeks !== null) {
            $targetAfkirAt = $entryDate->copy()->addWeeks(max($targetWeeks - $entryAgeWeeks, 0));
        }

        $afkirWeeksRemaining = $targetAfkirAt
            ? (int) floor($referenceDate->diffInDays($targetAfkirAt, false) / 7)
            : data_get($existing, 'afkirWeeksRemaining');
        $afkirStatus = $this->afkirStatus($afkirWeeksRemaining, data_get($existing, 'afkirStatus', 'normal'), $warningWeeks);
        $batchCode = trim((string) (data_get($meta, 'batchKode') ?: data_get($existing, 'batchCode')));

        if ($batchCode === '') {
            $batchCode = $entryDate ? 'Masuk '.$entryDate->toDateString() : 'Batch belum dicatat';
        }

        return array_merge($existing, [
            'batchCode' => $batchCode,
            'entryDate' => $entryDate?->toDateString(),
            'entryDateLabel' => $this->formatLifecycleDate($entryDate),
            'entryAgeWeeks' => $entryAgeWeeks,
            'ageWeeks' => $ageWeeks,
            'ageLabel' => $ageWeeks.' minggu',
            'afkirLabel' => $afkirLabel,
            'afkirTargetWeeks' => $targetWeeks,
            'afkirWarningWeeks' => $warningWeeks,
            'targetAfkirDate' => $targetAfkirAt?->toDateString(),
            'targetAfkirLabel' => $this->formatLifecycleDate($targetAfkirAt),
            'afkirWeeksRemaining' => $afkirWeeksRemaining,
            'afkirStatus' => $afkirStatus,
            'afkirStatusLabel' => $this->afkirStatusLabel($afkirStatus, $afkirWeeksRemaining, $afkirLabel),
            'phase' => $this->productionPhase((int) $ageWeeks, $afkirStatus, $afkirLabel),
        ]);
    }

    private function filterIndividualSettlementRows(array $rows, array $filters): array
    {
        $performance = $filters['performance'] ?? 'all';
        $afkir = $filters['afkir'] ?? 'all';

        return collect($rows)
            ->filter(function ($row) use ($performance, $afkir) {
                $passesPerformance = match ($performance) {
                    'warning' => data_get($row, 'status') === 'warning' || (bool) data_get($row, 'isDropIndication'),
                    'attention' => data_get($row, 'status') === 'attention',
                    'normal' => data_get($row, 'status') === 'normal',
                    'low_hdp' => (float) data_get($row, 'current.layingPercent', 0) < 70,
                    'high_hdp' => (float) data_get($row, 'current.layingPercent', 0) >= 85,
                    default => true,
                };
                $passesAfkir = $afkir === 'all' || data_get($row, 'lifecycle.afkirStatus', 'normal') === $afkir;

                return $passesPerformance && $passesAfkir;
            })
            ->values()
            ->all();
    }

    private function parseNullableCarbon(mixed $value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function nonNegativeInteger(mixed $value, mixed $fallback = 0): int
    {
        $candidate = is_numeric($value) ? (int) $value : (is_numeric($fallback) ? (int) $fallback : 0);

        return max(0, $candidate);
    }

    private function afkirStatus(mixed $weeksRemaining, string $fallback = 'normal', int $warningWeeks = 8): string
    {
        if (! is_numeric($weeksRemaining)) {
            return in_array($fallback, ['normal', 'due_soon', 'overdue'], true) ? $fallback : 'normal';
        }

        if ((int) $weeksRemaining < 0) {
            return 'overdue';
        }

        return (int) $weeksRemaining <= $warningWeeks ? 'due_soon' : 'normal';
    }

    private function afkirStatusLabel(string $status, mixed $weeksRemaining, string $label): string
    {
        if ($status === 'overdue') {
            return is_numeric($weeksRemaining)
                ? 'Lewat '.abs((int) $weeksRemaining).' minggu'
                : 'Lewat target';
        }

        if ($status === 'due_soon') {
            return is_numeric($weeksRemaining)
                ? (int) $weeksRemaining.' minggu lagi'
                : 'Mendekati '.Str::lower($label);
        }

        return is_numeric($weeksRemaining)
            ? (int) $weeksRemaining.' minggu lagi'
            : $label.' aman';
    }

    private function productionPhase(int $ageWeeks, string $afkirStatus, string $label): string
    {
        $cycleConfig = $this->activeAfkirCycleConfig();
        $productionStart = is_numeric($cycleConfig['production_start_weeks'] ?? null)
            ? (int) $cycleConfig['production_start_weeks']
            : 18;
        $peakStart = is_numeric($cycleConfig['peak_start_weeks'] ?? null)
            ? (int) $cycleConfig['peak_start_weeks']
            : 25;
        $peakEnd = is_numeric($cycleConfig['peak_end_weeks'] ?? null)
            ? (int) $cycleConfig['peak_end_weeks']
            : 45;
        $declineStart = is_numeric($cycleConfig['production_decline_weeks'] ?? null)
            ? (int) $cycleConfig['production_decline_weeks']
            : max($peakEnd + 1, 46);

        if ($afkirStatus === 'overdue') {
            return 'Lewat target '.Str::lower($label);
        }

        if ($afkirStatus === 'due_soon') {
            return 'Mendekati '.Str::lower($label);
        }

        if ($ageWeeks < $productionStart) {
            return 'Grower';
        }

        if ($ageWeeks >= $peakStart && $ageWeeks <= $peakEnd) {
            return 'Puncak produksi';
        }

        if ($ageWeeks >= $declineStart) {
            return 'Produksi lanjut';
        }

        return 'Awal produksi';
    }

    private function activeAfkirCycleConfig(): array
    {
        return $this->activeAfkirCycleConfig ??= $this->livestockMasterConfigService
            ->afkirConfigForJenis($this->peternakanService->getActiveJenisBudidayaId());
    }

    private function dispatchLivestockCycleAlert(array $barn, array $rows): void
    {
        try {
            $this->livestockCycleAlertService->dispatchForBarnRows(
                $barn,
                $rows,
                $this->activeAfkirCycleConfig(),
                $this->peternakanService->getActiveKomoditasId(),
                $this->peternakanService->getActiveJenisBudidayaId()
            );
        } catch (\Throwable) {
            // Notifikasi tidak boleh menggagalkan halaman laporan produktivitas.
        }
    }

    private function formatLifecycleDate(?Carbon $date): ?string
    {
        return $date?->locale('id')->translatedFormat('d M Y');
    }

    private function downloadSettlementCsv(array $barn, array $report, ?array $individualReport, string $startDate, string $endDate)
    {
        $filename = 'settlement-'.$barn['id'].'-'.$startDate.'-'.$endDate.'.csv';

        return response()->streamDownload(function () use ($report, $individualReport) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Settlement Produktivitas Kandang']);
            fputcsv($output, ['Tanggal', 'Telur', 'Egg kg', 'Pakan kg', 'FI g/ekor', 'Mati', 'HDP %', 'HHEP %', 'FCR', 'Catatan']);

            foreach ($report['rows'] ?? [] as $row) {
                fputcsv($output, [
                    $row['date_iso'] ?? $row['date_label'] ?? '',
                    $row['eggs'] ?? 0,
                    $row['egg_mass_kg'] ?? 0,
                    $row['feed_kg'] ?? 0,
                    $row['feed_intake'] ?? 0,
                    $row['mortality'] ?? 0,
                    $row['hdp'] ?? 0,
                    $row['hhep'] ?? 0,
                    $row['fcr'] ?? 0,
                    $row['note'] ?? '',
                ]);
            }

            if ($individualReport !== null) {
                $afkirLabel = (string) data_get($individualReport, 'afkir_config.label', 'Afkir');
                fputcsv($output, []);
                fputcsv($output, ['Performa Individu Ternak']);
                fputcsv($output, ['ID', 'Nama', 'Batch', 'Tanggal Masuk', 'Umur', 'Target '.$afkirLabel, 'Status '.$afkirLabel, 'HDP Periode %', 'HDP Pembanding %', 'Hari Tidak Bertelur', 'Penurunan %', 'Penurunan Poin', 'Status']);

                foreach ($individualReport['rows'] ?? [] as $row) {
                    fputcsv($output, [
                        data_get($row, 'id'),
                        data_get($row, 'namaId'),
                        data_get($row, 'lifecycle.batchCode'),
                        data_get($row, 'lifecycle.entryDate'),
                        data_get($row, 'lifecycle.ageLabel'),
                        data_get($row, 'lifecycle.targetAfkirDate'),
                        data_get($row, 'lifecycle.afkirStatusLabel'),
                        data_get($row, 'current.layingPercent', 0),
                        data_get($row, 'previous.layingPercent', 0),
                        data_get($row, 'current.nonLayingDays', 0),
                        data_get($row, 'dropPercent', 0),
                        data_get($row, 'dropPoints', 0),
                        data_get($row, 'status'),
                    ]);
                }
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function parseReportDate(mixed $value, Carbon $fallback): Carbon
    {
        try {
            return $value ? Carbon::parse((string) $value) : $fallback->copy();
        } catch (\Throwable) {
            return $fallback->copy();
        }
    }

    /**
     * POST /peternakan/evaluate-all - jalankan fuzzy untuk semua kandang jenis ternak aktif.
     */
    public function evaluateAll(Request $request): JsonResponse
    {
        $this->applyLivestockContext($request, true);
        $masterConfigStatus = $this->livestockMasterConfigService->summaryForCommodity($this->peternakanService->getActiveKomoditasId());
        $masterConfigStatus['spk_config_url'] = route('settings.fuzzy.index', array_filter([
            'profile_id' => data_get($masterConfigStatus, 'spk_profile.id'),
        ]));

        if (! ($masterConfigStatus['spk_configured'] ?? false)) {
            return response()->json([
                'success' => false,
                'processed' => 0,
                'errors' => [[
                    'coop_id' => null,
                    'message' => $masterConfigStatus['spk_message'] ?? 'Konfigurasi SPK belum lengkap. Lengkapi Pengaturan Fuzzy sebelum menjalankan SPK.',
                ]],
                'evaluation_time' => null,
            ], 422);
        }

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

    private function buildMasterBlockedFuzzyByBarn(array $barns, array $masterConfigStatus): array
    {
        $payload = [];

        foreach ($barns as $barn) {
            if (($barn['id'] ?? null) === 'no-data') {
                continue;
            }

            $payload[$barn['id']] = $this->masterBlockedFuzzyPayload($masterConfigStatus);
        }

        $payload['all'] = $this->masterBlockedFuzzyPayload($masterConfigStatus);

        return $payload;
    }

    private function masterBlockedFuzzyPayload(array $masterConfigStatus): array
    {
        $message = $masterConfigStatus['spk_message'] ?? 'Konfigurasikan Pengaturan Fuzzy terlebih dahulu agar card SPK dapat menampilkan input yang valid.';
        $spkConfigUrl = $masterConfigStatus['spk_config_url'] ?? '#';

        return [
            'fuzzySensors' => [
                'lingkungan' => [],
                'produktivitas' => [],
            ],
            'spkResults' => [
                'lingkungan' => [
                    'status' => 'BELUM SIAP',
                    'statusColor' => 'gray',
                    'score' => 0,
                    'scoreColor' => 'gray',
                    'title' => 'Menunggu Konfigurasi SPK',
                    'description' => $message,
                    'link' => $spkConfigUrl,
                ],
                'produktivitas' => [
                    'status' => 'BELUM SIAP',
                    'statusColor' => 'gray',
                    'score' => 0,
                    'scoreColor' => 'gray',
                    'title' => 'Card produktivitas belum aktif',
                    'description' => 'Tambahkan variabel input produktivitas dan sumber datanya di Pengaturan Fuzzy.',
                    'link' => $spkConfigUrl,
                ],
                'gabungan' => [
                    'status' => 'SPK CONFIG',
                    'statusColor' => 'gray',
                    'score' => 0,
                    'scoreColor' => 'gray',
                    'title' => 'Konfigurasi diperlukan',
                    'description' => $message,
                    'link' => $spkConfigUrl,
                    'isMain' => true,
                ],
            ],
            'spider' => ['labels' => [], 'values' => []],
            'indicators' => [],
        ];
    }

    private function masterBlockedSpkSummary(?string $commodityId, array $barns, array $masterConfigStatus): array
    {
        return [
            'status' => 'Butuh Konfigurasi SPK',
            'tone' => 'amber',
            'score' => null,
            'analyses_today' => 0,
            'last_update' => null,
            'last_update_human' => null,
            'hints' => array_values(array_filter(array_unique(array_merge(
                [$masterConfigStatus['spk_message'] ?? 'Lengkapi Pengaturan Fuzzy sebelum menjalankan SPK.'],
                $masterConfigStatus['spk_hints'] ?? []
            )))),
            'active_tasks' => 0,
            'needs_action_count' => 0,
            'barn_count' => collect($barns)->where('id', '!=', 'no-data')->count(),
            'action_candidates' => [],
            'spk_url' => route('spk.dashboard', array_filter([
                'komoditas' => $commodityId,
            ])),
            'tasks_url' => route('spk.tasks.index', ['tab' => 'active']),
        ];
    }

    private function buildDailySpkSummary(?string $commodityId, array $barns, array $dailyReportStatus): array
    {
        $barnIds = collect($barns)
            ->pluck('id')
            ->filter(fn ($id) => filled($id) && $id !== 'no-data')
            ->map(fn ($id) => (string) $id)
            ->values();

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

        $candidateSourceLogs = $sourceLogs->filter(fn (SpkFuzzyLog $log) => filled($log->unit_budidaya_id));
        if ($candidateSourceLogs->isEmpty()) {
            $candidateSourceLogs = $sourceLogs;
        }
        if ($barnIds->isNotEmpty()) {
            $candidateSourceLogs = $candidateSourceLogs
                ->filter(fn (SpkFuzzyLog $log) => ! filled($log->unit_budidaya_id) || $barnIds->contains((string) $log->unit_budidaya_id))
                ->values();
        }

        $problemLogs = $candidateSourceLogs
            ->filter(fn (SpkFuzzyLog $log) => $this->spkLogNeedsAction($log))
            ->sortByDesc(fn (SpkFuzzyLog $log) => $log->createdAt?->timestamp ?? 0)
            ->unique(fn (SpkFuzzyLog $log) => $this->spkActionCandidateFingerprint($log))
            ->values();
        $allProblemLogs = $problemLogs;

        $openRecommendations = $this->actionRecommendationService
            ->syncOpenRecommendationsFromLogs($candidateSourceLogs, $commodityId, $barnIds->all(), false)
            ->when($commodityId, fn ($items) => $items->filter(function (SpkActionRecommendation $recommendation) use ($commodityId) {
                return ! $recommendation->commodity_id || (string) $recommendation->commodity_id === (string) $commodityId;
            }))
            ->values();

        $activeTaskCount = SpkActionTask::withoutGlobalScopes()
            ->whereIn('status', ['todo', 'in_progress'])
            ->when($barnIds->isNotEmpty(), fn ($query) => $query->whereIn('unit_budidaya_id', $barnIds->all()))
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

        if ($this->hasCompletedTaskWithNewerAlert($allProblemLogs)) {
            $hints[] = 'Ada hasil SPK terbaru yang masih bermasalah setelah tugas sebelumnya selesai. Lakukan evaluasi ulang jika kondisi lapangan belum membaik.';
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
            $openRecommendations->isNotEmpty() || $activeTaskCount > 0 => 'Perlu Tindakan',
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
            'needs_action_count' => $openRecommendations->count(),
            'barn_count' => collect($barns)->where('id', '!=', 'no-data')->count(),
            'action_candidates' => $openRecommendations
                ->sortBy(fn (SpkActionRecommendation $recommendation) => (float) ($recommendation->score ?? 0))
                ->take(4)
                ->map(fn (SpkActionRecommendation $recommendation) => $this->formatSpkActionRecommendation($recommendation, $commodityId))
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
            : ['suhu', 'kelembapan', 'amonia', 'hdp', 'feed_intake', 'mortalitas'];
    }

    private function spkLogNeedsAction(SpkFuzzyLog $log): bool
    {
        return in_array($log->status_lingkungan, ['Waspada', 'Buruk'], true)
            || in_array($log->status_kesehatan, ['Waspada', 'Buruk'], true)
            || in_array($log->diagnosis_kausalitas, ['Waspada', 'Buruk', 'Kritis', 'Tidak Optimal'], true)
            || (float) ($log->output_value ?? 0) < 70;
    }

    private function hasCompletedTaskWithNewerAlert(Collection $problemLogs): bool
    {
        $unitIds = $problemLogs
            ->pluck('unit_budidaya_id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values();

        if ($unitIds->isEmpty()) {
            return false;
        }

        $completedTasks = SpkActionTask::withoutGlobalScopes()
            ->where('status', 'done')
            ->whereNotNull('completed_at')
            ->whereIn('unit_budidaya_id', $unitIds->all())
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get(['unit_budidaya_id', 'completed_at']);

        if ($completedTasks->isEmpty()) {
            return false;
        }

        return $problemLogs->contains(function (SpkFuzzyLog $log) use ($completedTasks) {
            if (! $log->createdAt || ! $log->unit_budidaya_id) {
                return false;
            }

            return $completedTasks->contains(function (SpkActionTask $task) use ($log) {
                return (string) $task->unit_budidaya_id === (string) $log->unit_budidaya_id
                    && $task->completed_at
                    && $log->createdAt->gt($task->completed_at);
            });
        });
    }

    private function spkActionCandidateFingerprint(SpkFuzzyLog $log): string
    {
        return implode('|', [
            $log->unit_budidaya_id ?: 'global',
            $this->normalizeSpkActionToken($log->status_lingkungan),
            $this->normalizeSpkActionToken($log->status_kesehatan),
            $this->normalizeSpkActionToken($log->diagnosis_kausalitas),
        ]);
    }

    private function normalizeSpkActionToken(mixed $value): string
    {
        $token = Str::of((string) $value)
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->toString();

        return $token !== '' ? $token : '-';
    }

    private function hasMeaningfulSpkLabel(mixed $value): bool
    {
        return ! in_array($this->normalizeSpkActionToken($value), [
            '-',
            'n/a',
            'na',
            'unknown',
            'tidak diketahui',
        ], true);
    }

    private function formatSpkActionCandidate(SpkFuzzyLog $log, ?string $commodityId): array
    {
        $score = round((float) ($log->output_value ?? 0), 1);
        $title = collect([
            $log->diagnosis_kausalitas,
            $log->status_kesehatan,
            $log->status_lingkungan,
        ])->first(fn ($value) => $this->hasMeaningfulSpkLabel($value)) ?: 'Evaluasi SPK perlu dilengkapi';

        $description = NarrativeGenerator::sanitizePlainText($log->recommendation)
            ?: NarrativeGenerator::sanitizePlainText($log->narrative)
            ?: 'Tinjau hasil SPK dan tentukan tindak lanjut petugas.';

        $params = array_filter([
            'komoditas' => $commodityId,
            'coop_id' => $log->unit_budidaya_id,
            'history_id' => $log->id,
        ]);

        return [
            'id' => $log->id,
            'barn' => $log->unitBudidaya?->nama ?? 'Semua kandang',
            'title' => $title,
            'description' => Str::limit($description, 140),
            'score' => $score,
            'priority' => $score < 55 ? 'Urgent' : 'Tinggi',
            'time' => $log->createdAt?->format('H:i') ?? '-',
            'spk_url' => route('spk.dashboard', $params),
            'task_url' => route('spk.tasks.index', array_filter([
                'create_task' => 1,
                'spk_id' => $log->id,
                'coop_id' => $log->unit_budidaya_id,
                'desc' => Str::limit($description, 160),
            ])),
        ];
    }

    private function formatSpkActionRecommendation(SpkActionRecommendation $recommendation, ?string $commodityId): array
    {
        $log = $recommendation->spkFuzzyLog;
        $description = NarrativeGenerator::sanitizePlainText($recommendation->description)
            ?: NarrativeGenerator::sanitizePlainText($log?->recommendation)
            ?: NarrativeGenerator::sanitizePlainText($log?->narrative)
            ?: 'Tinjau hasil SPK dan tentukan tindak lanjut petugas.';
        $score = $recommendation->score !== null
            ? round((float) $recommendation->score, 1)
            : round((float) ($log?->output_value ?? 0), 1);

        $params = array_filter([
            'komoditas' => $commodityId ?: $recommendation->commodity_id,
            'coop_id' => $recommendation->unit_budidaya_id,
            'history_id' => $recommendation->spk_fuzzy_log_id,
        ]);

        return [
            'id' => $recommendation->id,
            'recommendation_id' => $recommendation->id,
            'spk_id' => $recommendation->spk_fuzzy_log_id,
            'barn' => $recommendation->unitBudidaya?->nama
                ?? $log?->unitBudidaya?->nama
                ?? 'Semua kandang',
            'title' => $recommendation->title ?: 'Evaluasi SPK perlu dilengkapi',
            'description' => Str::limit($description, 140),
            'score' => $score,
            'priority' => $this->priorityLabelForRecommendation($recommendation->priority),
            'time' => $log?->createdAt?->format('H:i') ?? $recommendation->createdAt?->format('H:i') ?? '-',
            'spk_url' => route('spk.dashboard', $params),
            'task_url' => route('spk.tasks.index', array_filter([
                'create_task' => 1,
                'recommendation_id' => $recommendation->id,
                'spk_id' => $recommendation->spk_fuzzy_log_id,
                'coop_id' => $recommendation->unit_budidaya_id,
                'title' => 'Tindak lanjut SPK - '.($recommendation->unitBudidaya?->nama ?? $log?->unitBudidaya?->nama ?? 'Kandang'),
                'priority' => $recommendation->priority,
                'desc' => Str::limit($description, 160),
            ])),
        ];
    }

    private function priorityLabelForRecommendation(?string $priority): string
    {
        return match ($priority) {
            'urgent' => 'Urgent',
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            'low' => 'Rendah',
            default => 'Tinggi',
        };
    }

    private function runFuzzyEngine(?string $coopId): array
    {
        $commodityId = $this->peternakanService->getActiveKomoditasId();

        return $this->fuzzyEvaluationService->evaluate($coopId, $commodityId);
    }

    private function runAndPersistFuzzy(?string $coopId): SpkFuzzyLog
    {
        $log = $this->fuzzyEvaluationService->evaluateAndPersist(
            $coopId,
            $this->peternakanService->getActiveKomoditasId()
        );

        Cache::forget($this->cacheKey('fuzzy', $coopId));
        $this->environmentAlertService->dispatchForLog($log);

        return $log;
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
        $environmentCards = $this->peternakanService->filterEnvironmentCardsByMaster($sensorCards['lingkungan'] ?? []);
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

        $productivityCards = $this->peternakanService->filterProductivityCardsByMaster($sensorCards['produktivitas'] ?? []);
        $productivityFallback = $this->cachedProduktivitasData($barn['id'] ?? null);
        $productivityIndicators = $productivityCards
            ? $this->sensorCardMapper->toIndicators($productivityCards)
            : ($productivityFallback['indicators'] ?? []);
        $productivitySpider = $productivityCards
            ? $this->sensorCardMapper->toSpider($productivityCards)
            : ($productivityFallback['spider'] ?? ['labels' => [], 'values' => []]);

        return [
            'fuzzySensors' => [
                'lingkungan' => $environmentCards,
                'produktivitas' => $productivityCards ?: ($productivityFallback['productivitySensors'] ?? []),
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
                    'title' => 'Diagnosis Kausalitas',
                    'description' => NarrativeGenerator::sanitizePlainText($result['narrative'] ?? ($kausalitas['diagnosis'] ?? '-')),
                    'link' => $spkLink,
                    'isMain' => true,
                ],
            ],
            'spider' => $productivitySpider,
            'indicators' => $productivityIndicators,
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
