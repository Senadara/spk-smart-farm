<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\SpkFuzzyLog;
use App\Models\SpkFuzzyProfile;
use App\Services\Fuzzy\InputResolver;
use App\Services\Fuzzy\MamdaniEngine;
use App\Services\Fuzzy\NarrativeGenerator;
use App\Services\Notifications\SpkEnvironmentAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FuzzyController extends Controller
{
    public function __construct(
        private readonly InputResolver     $resolver,
        private readonly MamdaniEngine     $engine,
        private readonly NarrativeGenerator $narrator,
        private readonly SpkEnvironmentAlertService $environmentAlertService,
    ) {}

    // ──────────────────────────────────────────────────────────────
    // POST /spk-fuzzy/process
    // POST /spk-fuzzy/process?coop_id={uuid}
    // ──────────────────────────────────────────────────────────────

    /**
     * Jalankan Fuzzy Mamdani engine dan simpan ke log.
     * Mendukung mode per-kandang (coop_id) dan global.
     */
    public function processFuzzy(Request $request): JsonResponse
    {
        $coopId   = $request->query('coop_id');  // null = global
        $commodityId = $request->query('commodity_id') ?: $request->query('komoditas');
        $profileId = $request->query('profile_id');
        $barnName = null;

        if ($coopId) {
            $coop = DB::table('unitBudidaya')->where('id', $coopId)->first(['nama']);
            $barnName = $coop?->nama;
        }

        try {
            // 1. Kumpulkan input dari semua sumber
            $profile = SpkFuzzyProfile::resolveForContext($commodityId, $coopId, $profileId);
            $commodityId = $commodityId ?: $profile?->commodity_id;
            $inputs = $this->resolver->resolve($coopId, $commodityId, $profile?->id);

            // 2. Jalankan 3-engine cascaded Mamdani
            $result = $this->engine->processCascaded($inputs, $profile?->id, $commodityId, $coopId);

            // 3. Generate narasi AI-like
            $narrative = $this->narrator->generate($result, $barnName);

            // 4. Simpan ke log
            $log = SpkFuzzyLog::create([
                'unit_budidaya_id'    => $coopId,
                'profile_id'          => $result['profile']['id'] ?? $profile?->id,
                'commodity_id'        => $result['profile']['commodity_id'] ?? $commodityId,
                'input_json'          => $inputs,
                'fuzzified_json'      => [
                    'lingkungan' => $result['lingkungan']['fuzzified'] ?? [],
                    'kesehatan'  => $result['kesehatan']['fuzzified'] ?? [],
                ],
                'rule_result_json'    => [
                    'lingkungan'     => $result['lingkungan']['dominant_rule'] ?? null,
                    'kesehatan'      => $result['kesehatan']['dominant_rule'] ?? null,
                    'kausalitas'     => $result['kausalitas'] ?? null,
                ],
                'status_lingkungan'   => $result['lingkungan']['label'] ?? null,
                'status_kesehatan'    => $result['kesehatan']['label'] ?? null,
                'diagnosis_kausalitas'=> $result['kausalitas']['label'] ?? null,
                'output_value'        => min((float) ($result['lingkungan']['value'] ?? 0), (float) ($result['kesehatan']['value'] ?? 0)),
                'output_label'        => $result['kausalitas']['label'] ?? null,
                'narrative'           => $narrative,
                'recommendation'      => $result['kausalitas']['recommendation'] ?? null,
            ]);

            $this->environmentAlertService->dispatchForLog($log);

            return response()->json([
                'success'    => true,
                'log_id'     => $log->id,
                'coop_id'    => $coopId,
                'profile'    => $result['profile'] ?? null,
                'commodity_id' => $result['profile']['commodity_id'] ?? $commodityId,
                'barn_name'  => $barnName,
                'inputs'     => $inputs,
                'result'     => [
                    'status_lingkungan'    => $result['lingkungan']['label'],
                    'score_lingkungan'     => $result['lingkungan']['value'],
                    'status_kesehatan'     => $result['kesehatan']['label'],
                    'score_kesehatan'      => $result['kesehatan']['value'],
                    'diagnosis_kausalitas' => $result['kausalitas']['label'],
                    'recommendation'       => $result['kausalitas']['recommendation'],
                    'narrative'            => $narrative,
                    'dominant_lingkungan'  => $result['lingkungan']['dominant_rule'],
                    'dominant_kesehatan'   => $result['kesehatan']['dominant_rule'],
                ],
            ]);

        } catch (\Throwable $e) {
            \Log::error('[FuzzyController] processFuzzy error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menjalankan analisa fuzzy: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // GET /spk-fuzzy/history
    // GET /spk-fuzzy/history?coop_id={uuid}&limit=10
    // ──────────────────────────────────────────────────────────────

    /**
     * Ambil daftar riwayat analisa fuzzy dari spk_fuzzy_logs.
     */
    public function getHistory(Request $request): JsonResponse
    {
        $coopId = $request->query('coop_id');
        $limit  = (int) $request->query('limit', 10);

        $query = SpkFuzzyLog::query()
            ->orderBy('createdAt', 'desc')
            ->limit(min($limit, 50));

        if ($coopId) {
            $query->where('unit_budidaya_id', $coopId);
        }

        $logs = $query->get([
            'id', 'unit_budidaya_id', 'status_lingkungan', 'status_kesehatan',
            'diagnosis_kausalitas', 'output_value', 'output_label',
            'narrative', 'recommendation', 'createdAt',
        ]);

        // Format untuk frontend (cocok dengan format spkHistory di SpkDashboardController)
        $formatted = $logs->map(function ($log) {
            $colorMap = [
                'Optimal' => 'emerald', 'Baik' => 'blue',
                'Waspada' => 'amber',   'Buruk' => 'red',
            ];

            $lingkLabel = $log->status_lingkungan ?? 'Tidak Diketahui';
            $color      = $colorMap[$lingkLabel] ?? 'gray';

            $barn = $log->unit_budidaya_id
                ? DB::table('unitBudidaya')->where('id', $log->unit_budidaya_id)->value('nama')
                : 'Global';

            $plainNarrative = NarrativeGenerator::sanitizePlainText($log->narrative);

            return [
                'id'       => $log->id,
                'date'     => \Carbon\Carbon::parse($log->createdAt)->locale('id')->diffForHumans(),
                'time'     => \Carbon\Carbon::parse($log->createdAt)->format('H:i') . ' WIB',
                'mode'     => 'Fuzzy Mamdani',
                'modeColor'=> 'purple',
                'barn'     => $barn,
                'status'   => $log->diagnosis_kausalitas ?? $lingkLabel,
                'color'    => $color,
                'verdict'  => $plainNarrative
                    ? \Str::limit($plainNarrative, 120)
                    : '-',
                'recommendation' => NarrativeGenerator::sanitizePlainText($log->recommendation),
                'scores'   => [
                    'lingkungan' => $log->status_lingkungan,
                    'kesehatan'  => $log->status_kesehatan,
                    'kausalitas' => $log->diagnosis_kausalitas,
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'total'   => $logs->count(),
            'data'    => $formatted,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /spk-fuzzy/history/{id}
    // ──────────────────────────────────────────────────────────────

    /**
     * Detail satu log fuzzy beserta fuzzified + rule result.
     */
    public function getHistoryDetail(string $id): JsonResponse
    {
        $log = SpkFuzzyLog::find($id);

        if (!$log) {
            return response()->json(['success' => false, 'message' => 'Log tidak ditemukan'], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => array_merge($log->toArray(), [
                'narrative' => NarrativeGenerator::sanitizePlainText($log->narrative),
                'recommendation' => NarrativeGenerator::sanitizePlainText($log->recommendation),
            ]),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // GET /spk-fuzzy/config
    // ──────────────────────────────────────────────────────────────

    /**
     * Tampilkan konfigurasi aktif (variabel, sets, rules) — untuk debugging/admin.
     */
    public function getConfig(Request $request): JsonResponse
    {
        $profile = SpkFuzzyProfile::resolveForContext(
            $request->query('commodity_id') ?: $request->query('komoditas'),
            $request->query('coop_id'),
            $request->query('profile_id')
        );

        $variables = \App\Models\SpkFuzzyVariable::with(['sets', 'inputSource'])
            ->when($profile, fn ($query) => $query->where('profile_id', $profile->id))
            ->get();
        $rules = \App\Models\SpkFuzzyRule::with(['conditions.variable', 'conditions.set', 'outputSet'])
            ->when($profile, fn ($query) => $query->where('profile_id', $profile->id))
            ->get();

        return response()->json([
            'success'   => true,
            'profile'   => $profile,
            'variables' => $variables,
            'rules'     => $rules,
        ]);
    }
}
