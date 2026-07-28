<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\SpkActionTask;
use App\Models\SpkFuzzyLog;
use App\Services\Fuzzy\FuzzySensorCardMapper;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\LivestockMasterConfigService;
use App\Services\Notifications\SpkEnvironmentAlertService;
use App\Services\PeternakanService;
use App\Services\Spk\SpkFuzzyEvaluationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpkDashboardController extends Controller
{
    public function index(
        Request $request,
        PeternakanService $peternakanService,
        LivestockMasterConfigService $livestockMasterConfigService
    ) {
        $requestedJenisId = $request->input('jenis_budidaya_id') ?: $request->input('jenis_ternak');
        $requestedKomoditas = $request->input('komoditas');
        $coopId = $request->filled('coop_id') ? $request->input('coop_id') : null;
        $historyId = $request->input('history_id');

        if ($requestedJenisId) {
            $peternakanService->forJenisTernak($requestedJenisId, $requestedKomoditas);
        } elseif ($requestedKomoditas && $livestockMasterConfigService->isLivestockCommodity($requestedKomoditas)) {
            $jenisFromKomoditas = $livestockMasterConfigService->livestockCommodities()
                ->firstWhere('id', $requestedKomoditas)
                ?->jenisBudidayaId;
            $peternakanService->forJenisTernak($jenisFromKomoditas, $requestedKomoditas);
        } else {
            $peternakanService->forJenisTernak(null, null);
        }

        $activeKomoditasId = $peternakanService->getActiveKomoditasId();
        $jenisBudidayaId = $peternakanService->getActiveJenisBudidayaId();

        $masterConfigStatus = $livestockMasterConfigService->summaryForCommodity($activeKomoditasId);
        $masterConfigStatus['data_master_url'] = route('data-master.index', array_filter([
            'jenis_budidaya_id' => $masterConfigStatus['jenis_budidaya_id'] ?? null,
        ]));
        $masterConfigStatus['spk_config_url'] = route('settings.fuzzy.index', array_filter([
            'profile_id' => data_get($masterConfigStatus, 'spk_profile.id'),
        ]));

        $barnsOption = DB::table('unitBudidaya')
            ->when($jenisBudidayaId, fn ($query) => $query->where('jenisBudidayaId', $jenisBudidayaId))
            ->where('status', 1)
            ->where('isDeleted', 0)
            ->orderBy('nama')
            ->get(['id', 'nama'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->nama])
            ->prepend(['id' => null, 'name' => 'Semua Kandang'])
            ->toArray();

        $jenisTernakOptions = $livestockMasterConfigService->livestockTypes()
            ->pluck('nama', 'id')
            ->toArray();

        $filterOptions = [
            'jenis_ternak' => $jenisTernakOptions ?: ['petelur' => 'Ayam Petelur'],
        ];

        $spkConfigured = (bool) ($masterConfigStatus['spk_configured'] ?? false);
        $selectedLog = $spkConfigured
            ? $this->selectedLog($historyId, $coopId, $activeKomoditasId)
            : null;

        $spkHistory = $spkConfigured
            ? $this->getSpkHistory($coopId, $activeKomoditasId)
            : [$this->emptyHistory()];
        $activeHistory = $selectedLog
            ? $this->historyItemFromLog($selectedLog)
            : $this->emptyHistory($coopId ? 'Belum ada analisa untuk kandang ini' : 'Pilih kandang atau riwayat untuk melihat detail');

        $prodData = $spkConfigured && $selectedLog
            ? $peternakanService->getProduktivitasData($selectedLog->unit_budidaya_id)
            : ['spider' => ['labels' => [], 'values' => []], 'indicators' => [], 'productivitySensors' => []];

        $fuzzyData = ! $spkConfigured
            ? $this->masterBlockedFuzzyData($masterConfigStatus)
            : ($selectedLog
                ? $this->getFuzzyStatus($this->resultFromLog($selectedLog), $prodData, $peternakanService)
                : $this->emptyFuzzyData(
                    'Belum ada hasil yang dipilih',
                    $coopId
                        ? 'Jalankan evaluasi SPK untuk kandang ini agar hasil inferensi dapat ditampilkan.'
                        : 'Mode semua kandang menampilkan overview. Pilih salah satu kandang atau riwayat untuk melihat diagnosis lengkap.',
                    route('settings.fuzzy.index')
                ));

        $chartData = $this->getChartData($selectedLog?->unit_budidaya_id ?? $coopId, $activeKomoditasId, $fuzzyData);
        $actionTickets = $selectedLog ? $this->getActionTickets($selectedLog->id) : [];
        $overviewCards = $spkConfigured && ! $coopId && ! $selectedLog
            ? $this->getSpkOverviewCards($activeKomoditasId, $barnsOption, $jenisBudidayaId)
            : [];
        $inputQuality = $selectedLog
            ? $this->inputQualitySummary($selectedLog)
            : $this->emptyInputQuality();
        $calculationDetail = $selectedLog
            ? $this->calculationDetailFromLog($selectedLog)
            : ['groups' => [], 'input_meta' => [], 'has_detail' => false];
        $canCreateTask = $selectedLog && $this->logNeedsAction($selectedLog) && data_get(session('user'), 'role') === 'pjawab';
        $kpi = $spkConfigured ? $this->getKpiMetrics($activeKomoditasId) : [];

        return view('spk.dashboard', compact(
            'activeKomoditasId',
            'jenisBudidayaId',
            'coopId',
            'filterOptions',
            'kpi',
            'fuzzyData',
            'chartData',
            'actionTickets',
            'barnsOption',
            'spkHistory',
            'activeHistory',
            'masterConfigStatus',
            'overviewCards',
            'inputQuality',
            'calculationDetail',
            'canCreateTask',
            'selectedLog'
        ));
    }

    public function evaluate(
        Request $request,
        PeternakanService $peternakanService,
        LivestockMasterConfigService $livestockMasterConfigService
    ) {
        $requestedJenisId = $request->input('jenis_budidaya_id') ?: $request->input('jenis_ternak');
        $requestedKomoditas = $request->input('komoditas');
        $coopId = $request->filled('coop_id') ? $request->input('coop_id') : null;

        if ($requestedJenisId) {
            $peternakanService->forJenisTernak($requestedJenisId, $requestedKomoditas);
        } elseif ($requestedKomoditas && $livestockMasterConfigService->isLivestockCommodity($requestedKomoditas)) {
            $jenisFromKomoditas = $livestockMasterConfigService->livestockCommodities()
                ->firstWhere('id', $requestedKomoditas)
                ?->jenisBudidayaId;
            $peternakanService->forJenisTernak($jenisFromKomoditas, $requestedKomoditas);
        } else {
            $peternakanService->forJenisTernak(null, null);
        }

        $activeCommodityId = $peternakanService->getActiveKomoditasId();
        $masterConfigStatus = $livestockMasterConfigService->summaryForCommodity($activeCommodityId);

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

        $coopIds = $coopId ? [$coopId] : $peternakanService->getActiveCoopIds();
        $processed = 0;
        $errors = [];

        foreach (array_unique(array_filter($coopIds)) as $targetCoopId) {
            try {
                $result = $this->runFuzzyEngine($targetCoopId, $activeCommodityId);

                if (! empty($result['error'])) {
                    throw new \RuntimeException($result['error']);
                }

                $this->persistFuzzyResult($targetCoopId, $result, $activeCommodityId);
                $processed++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'coop_id' => $targetCoopId,
                    'message' => $e->getMessage(),
                ];
            }
        }

        $lastAt = $this->baseLogQuery($activeCommodityId)
            ->when($coopId, fn ($query) => $query->where('unit_budidaya_id', $coopId))
            ->value('createdAt');

        return response()->json([
            'success' => $processed > 0,
            'processed' => $processed,
            'errors' => $errors,
            'evaluation_time' => $lastAt
                ? Carbon::parse($lastAt)->locale('id')->translatedFormat('d M Y, H:i')
                : null,
        ]);
    }

    private function runFuzzyEngine(?string $coopId, ?string $commodityId = null): array
    {
        try {
            $result = app(SpkFuzzyEvaluationService::class)->evaluate($coopId, $commodityId);

            return array_merge($result, ['error' => null]);
        } catch (\Throwable $e) {
            \Log::error('[SpkDashboard] FuzzyEngine error: ' . $e->getMessage());

            return [
                'error' => $e->getMessage(),
                'inputs' => [],
                'input_meta' => [],
                'lingkungan' => [],
                'kesehatan' => [],
                'kausalitas' => [],
                'narrative' => null,
            ];
        }
    }

    private function persistFuzzyResult(?string $coopId, array $result, ?string $commodityId = null): SpkFuzzyLog
    {
        $log = app(SpkFuzzyEvaluationService::class)->persist($coopId, $result, $commodityId);
        app(SpkEnvironmentAlertService::class)->dispatchForLog($log);

        return $log;
    }

    private function baseLogQuery(?string $commodityId = null)
    {
        return $this->scopedLogQuery($commodityId)
            ->with(['profile', 'unitBudidaya'])
            ->orderByDesc('createdAt');
    }

    private function scopedLogQuery(?string $commodityId = null)
    {
        return SpkFuzzyLog::query()
            ->when($commodityId, function ($query) use ($commodityId) {
                $query->where(function ($q) use ($commodityId) {
                    $q->where('commodity_id', $commodityId)
                        ->orWhereNull('commodity_id');
                });
            });
    }

    private function selectedLog(?string $historyId, ?string $coopId, ?string $commodityId): ?SpkFuzzyLog
    {
        if ($historyId) {
            return $this->baseLogQuery($commodityId)->where('id', $historyId)->first();
        }

        if (! $coopId) {
            return null;
        }

        return $this->baseLogQuery($commodityId)
            ->where('unit_budidaya_id', $coopId)
            ->first();
    }

    private function masterBlockedFuzzyData(array $masterConfigStatus): array
    {
        $message = $masterConfigStatus['spk_message'] ?? 'Lengkapi Pengaturan Fuzzy sebelum menjalankan SPK.';

        return $this->emptyFuzzyData(
            'Konfigurasi SPK belum lengkap',
            $message,
            $masterConfigStatus['spk_config_url'] ?? '#'
        );
    }

    private function emptyFuzzyData(string $title, string $message, string $link = '#'): array
    {
        return [
            'confidence' => 0,
            'spider' => ['labels' => [], 'values' => []],
            'color' => 'gray',
            'sensors' => [
                'lingkungan' => [],
                'produktivitas' => [],
            ],
            'indicators' => [],
            'results' => [
                'lingkungan' => [
                    'status' => 'BELUM SIAP',
                    'statusColor' => 'gray',
                    'score' => 0,
                    'scoreColor' => 'gray',
                    'title' => 'Parameter lingkungan belum tersedia',
                    'description' => $message,
                    'link' => $link,
                ],
                'produktivitas' => [
                    'status' => 'BELUM SIAP',
                    'statusColor' => 'gray',
                    'score' => 0,
                    'scoreColor' => 'gray',
                    'title' => 'Produktivitas belum tersedia',
                    'description' => $message,
                    'link' => $link,
                ],
                'gabungan' => [
                    'status' => 'MENUNGGU',
                    'statusColor' => 'gray',
                    'score' => 0,
                    'scoreColor' => 'gray',
                    'title' => $title,
                    'description' => $message,
                    'link' => $link,
                    'isMain' => true,
                ],
            ],
        ];
    }

    private function getSpkHistory(?string $coopId, ?string $commodityId = null): array
    {
        $query = $this->baseLogQuery($commodityId)->limit(16);

        if ($coopId) {
            $query->where('unit_budidaya_id', $coopId);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            return [$this->emptyHistory()];
        }

        return $logs->map(fn (SpkFuzzyLog $log) => $this->historyItemFromLog($log))->toArray();
    }

    private function historyItemFromLog(SpkFuzzyLog $log): array
    {
        $createdAt = Carbon::parse($log->createdAt);
        $barnName = $log->unitBudidaya?->nama
            ?: ($log->unit_budidaya_id ? DB::table('unitBudidaya')->where('id', $log->unit_budidaya_id)->value('nama') : 'Global');
        $plainNarrative = NarrativeGenerator::sanitizePlainText($log->narrative);
        $score = (float) ($log->output_value ?? 0);

        return [
            'id' => $log->id,
            'date' => $createdAt->locale('id')->diffForHumans(),
            'dateKey' => $createdAt->toDateString(),
            'time' => $createdAt->format('H:i') . ' WIB',
            'mode' => 'Fuzzy Mamdani',
            'modeColor' => 'purple',
            'barn' => $barnName ?: 'Global',
            'status' => $log->diagnosis_kausalitas ?? $log->status_lingkungan ?? '-',
            'color' => $this->scoreColor($score),
            'verdict' => $plainNarrative ? Str::limit($plainNarrative, 150) : '-',
            'recommendation' => NarrativeGenerator::sanitizePlainText($log->recommendation) ?: '-',
            'raw' => is_array($log->input_json) ? $log->input_json : [],
            'score' => $score,
            'search' => strtolower($log->id . ' ' . ($barnName ?: '') . ' ' . ($log->diagnosis_kausalitas ?? '') . ' ' . ($log->status_lingkungan ?? '')),
        ];
    }

    private function resultFromLog(SpkFuzzyLog $log): array
    {
        return [
            'inputs' => is_array($log->input_json) ? $log->input_json : [],
            'input_meta' => is_array($log->input_meta_json) ? $log->input_meta_json : [],
            'profile' => $log->profile ? [
                'id' => $log->profile->id,
                'commodity_id' => $log->profile->commodity_id,
                'jenis_budidaya_id' => $log->profile->jenis_budidaya_id,
                'name' => $log->profile->name,
                'version' => $log->profile->version,
                'status' => $log->profile->status,
            ] : [
                'id' => $log->profile_id,
                'commodity_id' => $log->commodity_id,
            ],
            'lingkungan' => $this->groupResultFromLog($log, 'lingkungan', $log->status_lingkungan),
            'kesehatan' => $this->groupResultFromLog($log, 'kesehatan', $log->status_kesehatan),
            'kausalitas' => $this->causalityFromLog($log),
            'narrative' => NarrativeGenerator::sanitizePlainText($log->narrative),
        ];
    }

    private function groupResultFromLog(SpkFuzzyLog $log, string $group, ?string $statusLabel): array
    {
        $stored = data_get($log->rule_result_json, $group);
        $dominantRule = data_get($stored, 'dominant_rule');

        if (! $dominantRule && is_array($stored) && array_key_exists('conditions', $stored)) {
            $dominantRule = $stored;
        }

        $value = data_get($stored, 'value');
        if (! is_numeric($value)) {
            $value = $log->output_value;
        }

        return [
            'value' => round((float) ($value ?? 0), 1),
            'label' => data_get($stored, 'label') ?: $statusLabel ?: 'Tidak Diketahui',
            'fuzzified' => data_get($log->fuzzified_json, $group, []),
            'rule_results' => data_get($stored, 'rule_results', []),
            'dominant_rule' => $dominantRule,
            'from_log' => true,
        ];
    }

    private function causalityFromLog(SpkFuzzyLog $log): array
    {
        $stored = data_get($log->rule_result_json, 'kausalitas', []);

        return [
            'label' => $log->diagnosis_kausalitas ?: data_get($stored, 'label', 'Tidak Diketahui'),
            'diagnosis' => data_get($stored, 'diagnosis', $log->diagnosis_kausalitas ?: '-'),
            'recommendation' => $log->recommendation ?: data_get($stored, 'recommendation', '-'),
            'matched_rule' => data_get($stored, 'matched_rule'),
            'lookup_labels' => data_get($stored, 'lookup_labels', []),
        ];
    }

    private function getFuzzyStatus(array $result, array $prodData, PeternakanService $peternakanService): array
    {
        $lingkungan = $result['lingkungan'] ?? [];
        $kesehatan = $result['kesehatan'] ?? [];
        $kausalitas = $result['kausalitas'] ?? [];

        $lingkLabel = $lingkungan['label'] ?? 'Tidak Diketahui';
        $kesehatLabel = $kesehatan['label'] ?? 'Tidak Diketahui';
        $lingkScore = round((float) ($lingkungan['value'] ?? 0), 1);
        $kesehatScore = round((float) ($kesehatan['value'] ?? 0), 1);
        $gabScore = round(min($lingkScore, $kesehatScore), 1);

        $sensorCardMapper = app(FuzzySensorCardMapper::class);
        $sensorCards = $sensorCardMapper->fromResult($result);
        $environmentCards = $peternakanService->filterEnvironmentCardsByMaster($sensorCards['lingkungan'] ?? []);
        $productivityCards = $peternakanService->filterProductivityCardsByMaster($sensorCards['produktivitas'] ?? []);
        $productivityIndicators = $productivityCards
            ? $sensorCardMapper->toIndicators($productivityCards)
            : ($prodData['indicators'] ?? []);
        $productivitySpider = $productivityCards
            ? $sensorCardMapper->toSpider($productivityCards)
            : ($prodData['spider'] ?? ['labels' => [], 'values' => []]);

        return [
            'confidence' => $gabScore,
            'spider' => $productivitySpider,
            'color' => $this->scoreColor($gabScore),
            'sensors' => [
                'lingkungan' => $environmentCards,
                'produktivitas' => $productivityCards ?: ($prodData['productivitySensors'] ?? []),
            ],
            'indicators' => $productivityIndicators,
            'results' => [
                'lingkungan' => [
                    'status' => strtoupper($lingkLabel),
                    'statusColor' => $this->scoreColor($lingkScore),
                    'score' => $lingkScore,
                    'scoreColor' => $this->scoreColor($lingkScore),
                    'title' => data_get($lingkungan, 'dominant_rule.diagnosis', 'Analisa Lingkungan'),
                    'description' => 'Skor ' . $lingkScore . '/100 berdasarkan parameter lingkungan aktif.',
                    'link' => '#',
                ],
                'produktivitas' => [
                    'status' => strtoupper($kesehatLabel),
                    'statusColor' => $this->scoreColor($kesehatScore),
                    'score' => $kesehatScore,
                    'scoreColor' => $this->scoreColor($kesehatScore),
                    'title' => data_get($kesehatan, 'dominant_rule.diagnosis', 'Analisa Produktivitas'),
                    'description' => 'Skor ' . $kesehatScore . '/100 berdasarkan parameter produktivitas aktif.',
                    'link' => '#',
                ],
                'gabungan' => [
                    'status' => strtoupper($kausalitas['label'] ?? 'N/A'),
                    'statusColor' => $this->scoreColor($gabScore),
                    'score' => $gabScore,
                    'scoreColor' => $this->scoreColor($gabScore),
                    'title' => 'Diagnosis Kausalitas',
                    'description' => NarrativeGenerator::sanitizePlainText($result['narrative'] ?? ($kausalitas['diagnosis'] ?? '-')),
                    'recommendation' => NarrativeGenerator::sanitizePlainText($kausalitas['recommendation'] ?? '-'),
                    'link' => '#',
                    'isMain' => true,
                ],
            ],
        ];
    }

    private function getSpkOverviewCards(?string $commodityId, array $barnsOption, ?string $jenisBudidayaId): array
    {
        return collect($barnsOption)
            ->filter(fn (array $barn) => filled($barn['id'] ?? null))
            ->map(function (array $barn) use ($commodityId, $jenisBudidayaId) {
                $log = $this->baseLogQuery($commodityId)
                    ->where('unit_budidaya_id', $barn['id'])
                    ->first();

                if (! $log) {
                    return [
                        'id' => $barn['id'],
                        'name' => $barn['name'],
                        'status' => 'Belum dievaluasi',
                        'score' => null,
                        'tone' => 'gray',
                        'time' => '-',
                        'description' => 'Belum ada log SPK untuk kandang ini.',
                        'url' => route('spk.dashboard', ['jenis_budidaya_id' => $jenisBudidayaId, 'coop_id' => $barn['id']]),
                    ];
                }

                return [
                    'id' => $barn['id'],
                    'name' => $barn['name'],
                    'status' => $log->diagnosis_kausalitas ?: $log->status_lingkungan ?: '-',
                    'score' => round((float) ($log->output_value ?? 0), 1),
                    'tone' => $this->scoreColor((float) ($log->output_value ?? 0)),
                    'time' => Carbon::parse($log->createdAt)->locale('id')->diffForHumans(),
                    'description' => NarrativeGenerator::sanitizePlainText(Str::limit($log->recommendation ?: $log->narrative ?: '-', 120)),
                    'url' => route('spk.dashboard', [
                        'jenis_budidaya_id' => $jenisBudidayaId,
                        'coop_id' => $barn['id'],
                        'history_id' => $log->id,
                    ]),
                ];
            })
            ->values()
            ->toArray();
    }

    private function inputQualitySummary(SpkFuzzyLog $log): array
    {
        $inputs = is_array($log->input_json) ? $log->input_json : [];
        $meta = is_array($log->input_meta_json) ? $log->input_meta_json : [];

        $items = collect($meta ?: $inputs)
            ->map(function ($item, $key) use ($inputs) {
                $source = is_array($item) ? ($item['source_type'] ?? null) : null;
                $status = is_array($item) ? ($item['status'] ?? 'unknown') : 'unknown';
                $value = is_array($item) ? ($item['value'] ?? ($inputs[$key] ?? null)) : $item;

                return [
                    'key' => (string) $key,
                    'name' => Str::of((string) $key)->replace('_', ' ')->title()->toString(),
                    'value' => is_numeric($value) ? round((float) $value, 2) : $value,
                    'source' => $this->sourceTypeLabel($source),
                    'status' => $status,
                    'statusLabel' => $this->inputStatusLabel($status),
                    'tone' => $this->inputStatusTone($status),
                    'message' => is_array($item) ? ($item['message'] ?? '') : 'Log lama belum menyimpan metadata sumber input.',
                    'fallback' => is_array($item) ? (bool) ($item['fallback'] ?? false) : false,
                ];
            })
            ->values()
            ->toArray();

        $fallbackCount = collect($items)->where('fallback', true)->count();
        $okCount = collect($items)->where('status', 'ok')->count();

        return [
            'total' => count($items),
            'ok' => $okCount,
            'fallback' => $fallbackCount,
            'items' => $items,
            'tone' => $fallbackCount > 0 ? 'amber' : ($okCount > 0 ? 'emerald' : 'gray'),
            'message' => $fallbackCount > 0
                ? "{$fallbackCount} input memakai fallback atau belum tersedia."
                : ($okCount > 0 ? 'Semua input utama tersedia.' : 'Metadata sumber input belum tersedia.'),
        ];
    }

    private function emptyInputQuality(): array
    {
        return [
            'total' => 0,
            'ok' => 0,
            'fallback' => 0,
            'items' => [],
            'tone' => 'gray',
            'message' => 'Belum ada log SPK yang dipilih.',
        ];
    }

    private function calculationDetailFromLog(SpkFuzzyLog $log): array
    {
        $result = $this->resultFromLog($log);

        return [
            'has_detail' => true,
            'groups' => [
                $this->calculationGroup('Lingkungan', $result['lingkungan']),
                $this->calculationGroup('Produktivitas & Kesehatan', $result['kesehatan']),
                [
                    'label' => 'Kausalitas',
                    'score' => null,
                    'status' => $result['kausalitas']['label'] ?? '-',
                    'dominant_rule' => $result['kausalitas']['matched_rule'] ?? '-',
                    'fuzzified' => [],
                    'recommendation' => $result['kausalitas']['recommendation'] ?? '-',
                ],
            ],
            'input_meta' => $this->inputQualitySummary($log)['items'],
        ];
    }

    private function calculationGroup(string $label, array $group): array
    {
        return [
            'label' => $label,
            'score' => $group['value'] ?? null,
            'status' => $group['label'] ?? '-',
            'dominant_rule' => $this->ruleSummary($group['dominant_rule'] ?? null),
            'alpha' => data_get($group, 'dominant_rule.alpha'),
            'fuzzified' => $group['fuzzified'] ?? [],
        ];
    }

    private function ruleSummary(?array $rule): string
    {
        if (! $rule) {
            return '-';
        }

        $conditions = collect($rule['conditions'] ?? [])
            ->map(fn ($condition) => ($condition['variable_name'] ?? '-') . ' = ' . ($condition['set_name'] ?? '-'))
            ->implode(', ');

        return trim(($rule['name'] ?? 'Rule dominan') . ($conditions ? ': ' . $conditions : ''));
    }

    private function getKpiMetrics(?string $commodityId): array
    {
        $activeTasks = SpkActionTask::withoutGlobalScopes()
            ->whereIn('status', ['todo', 'in_progress'])
            ->count();

        $todayAnalyses = $this->scopedLogQuery($commodityId)
            ->whereDate('createdAt', now()->toDateString())
            ->count();

        $avgScore = round((float) $this->scopedLogQuery($commodityId)
            ->where('createdAt', '>=', now()->subDays(30))
            ->avg('output_value'), 1);

        $problemBarns = $this->scopedLogQuery($commodityId)
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
            ['label' => 'Tugas Aktif', 'value' => (string) $activeTasks, 'tone' => $activeTasks > 0 ? 'amber' : 'gray'],
            ['label' => 'Rata-rata Skor', 'value' => $avgScore ? $avgScore . '/100' : '-', 'tone' => $this->scoreColor((float) $avgScore)],
            ['label' => 'Analisa Hari Ini', 'value' => (string) $todayAnalyses, 'tone' => $todayAnalyses > 0 ? 'emerald' : 'gray'],
            ['label' => 'Kandang Atensi', 'value' => (string) $problemBarns, 'tone' => $problemBarns > 0 ? 'red' : 'gray'],
        ];
    }

    private function getChartData(?string $coopId, ?string $commodityId, array $fuzzyData): array
    {
        if (! $coopId) {
            return [
                'hasHdp' => false,
                'hasEnvironment' => false,
                'hdpComparison' => ['labels' => [], 'actual' => [], 'standard' => []],
                'environment' => ['labels' => [], 'series' => []],
            ];
        }

        $logs = $this->baseLogQuery($commodityId)
            ->where('unit_budidaya_id', $coopId)
            ->limit(30)
            ->get(['input_json', 'createdAt'])
            ->sortBy('createdAt')
            ->values();

        $labels = $logs
            ->map(fn ($log) => Carbon::parse($log->createdAt)->format('d/m H:i'))
            ->toArray();

        $environmentCards = collect(data_get($fuzzyData, 'sensors.lingkungan', []));
        $environmentKeys = $environmentCards
            ->pluck('label', 'key')
            ->filter()
            ->take(4);

        if ($environmentKeys->isEmpty()) {
            $environmentKeys = collect($logs->first()?->input_json ?? [])
                ->keys()
                ->reject(fn ($key) => in_array($key, ['hdp', 'hhep', 'fcr', 'pakan', 'feed', 'mortalitas', 'umur_biologis'], true))
                ->mapWithKeys(fn ($key) => [$key => Str::of($key)->replace('_', ' ')->title()->toString()])
                ->take(4);
        }

        $colors = ['#059669', '#2563EB', '#D97706', '#DC2626'];
        $series = $environmentKeys
            ->values()
            ->map(function ($label, $idx) use ($environmentKeys, $logs, $colors) {
                $key = $environmentKeys->keys()[$idx];

                return [
                    'key' => $key,
                    'label' => $label,
                    'color' => $colors[$idx] ?? '#64748B',
                    'data' => $logs->map(fn ($log) => round((float) data_get($log->input_json, $key, 0), 2))->toArray(),
                ];
            })
            ->toArray();

        $hasHdp = $logs->contains(fn ($log) => array_key_exists('hdp', is_array($log->input_json) ? $log->input_json : []));

        return [
            'hasHdp' => $hasHdp,
            'hasEnvironment' => ! empty($series),
            'hdpComparison' => [
                'labels' => $labels,
                'actual' => $logs->map(fn ($log) => data_get($log->input_json, 'hdp') !== null ? round((float) data_get($log->input_json, 'hdp'), 1) : null)->toArray(),
                'standard' => array_fill(0, count($labels), 93.0),
            ],
            'environment' => [
                'labels' => $labels,
                'series' => $series,
            ],
        ];
    }

    private function getActionTickets(string $historyId): array
    {
        $tasks = SpkActionTask::with('assignee')
            ->where('spk_fuzzy_log_id', $historyId)
            ->orderBy('createdAt', 'desc')
            ->get();

        return $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'code' => substr($task->id, 0, 8),
                'title' => $task->title,
                'source' => 'Tugas SPK',
                'priority' => match ($task->priority) {
                    'urgent' => 'Urgent',
                    'high' => 'High',
                    'medium' => 'Medium',
                    'low' => 'Low',
                    default => 'Medium',
                },
                'status' => match ($task->status) {
                    'todo' => 'To Do',
                    'in_progress' => 'In Progress',
                    'done' => 'Done',
                    'cancelled' => 'Cancelled',
                    default => 'To Do',
                },
                'assignee' => $task->assignee->name ?? 'Belum Ditugaskan',
            ];
        })->toArray();
    }

    private function logNeedsAction(SpkFuzzyLog $log): bool
    {
        return (float) ($log->output_value ?? 0) < 70
            || in_array($log->status_lingkungan, ['Waspada', 'Buruk'], true)
            || in_array($log->status_kesehatan, ['Waspada', 'Buruk'], true);
    }

    private function emptyHistory(string $message = 'Belum ada analisa'): array
    {
        return [
            'id' => 'N/A',
            'date' => '-',
            'dateKey' => '',
            'time' => '-',
            'mode' => '-',
            'modeColor' => 'gray',
            'barn' => '-',
            'status' => '-',
            'color' => 'gray',
            'verdict' => $message,
            'recommendation' => '-',
            'raw' => [],
            'score' => 0,
            'search' => '',
        ];
    }

    private function scoreColor(float $score): string
    {
        return match (true) {
            $score >= 85 => 'emerald',
            $score >= 70 => 'blue',
            $score >= 55 => 'amber',
            $score > 0 => 'red',
            default => 'gray',
        };
    }

    private function sourceTypeLabel(?string $sourceType): string
    {
        return match ($sourceType) {
            'iot' => 'IoT',
            'report_metric' => 'Laporan',
            'database' => 'Database',
            'function' => 'Kalkulasi',
            default => 'Tidak diketahui',
        };
    }

    private function inputStatusLabel(string $status): string
    {
        return match ($status) {
            'ok' => 'Tersedia',
            'stale' => 'Terlambat',
            'missing' => 'Kosong',
            'error' => 'Error',
            default => 'Legacy',
        };
    }

    private function inputStatusTone(string $status): string
    {
        return match ($status) {
            'ok' => 'emerald',
            'stale' => 'amber',
            'missing', 'error' => 'red',
            default => 'gray',
        };
    }
}
