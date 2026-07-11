<?php

namespace App\Http\Controllers\Spk;

use App\Http\Controllers\Controller;
use App\Models\MasterProduk;
use App\Models\SpkAhpBobot;
use App\Models\SpkAhpConfiguration;
use App\Models\SpkAhpPerbandingan;
use App\Models\SpkParameter;
use App\Services\AHPService;
use App\Services\SAWRecommenderService;
use App\Services\SupplierInsightService;
use App\Support\SpkDssActorId;
use Illuminate\Http\Request;

class SpkSupplierDssController extends Controller
{
    public function __construct(
        private AHPService $ahpService,
        private SAWRecommenderService $sawService,
        private SupplierInsightService $insightService,
    ) {}

    public function config(Request $request)
    {
        $parameters = SpkParameter::orderBy('id')->get();
        $pairs = $this->generatePairs($parameters);
        $userId = SpkDssActorId::resolve($request);
        $saved = $userId !== null
            ? SpkAhpPerbandingan::where('user_id', $userId)->get()->keyBy(
                fn ($p) => $p->parameter_1_id.'-'.$p->parameter_2_id
            )
            : collect();
        $bobots = $userId !== null
            ? SpkAhpBobot::where('user_id', $userId)->with('parameter')->get()
            : collect();
        $latestConfig = $userId !== null
            ? SpkAhpConfiguration::where('user_id', $userId)->where('is_valid', true)->orderByDesc('version')->first()
            : null;

        return view('spk.suppliers.dss-config', compact(
            'parameters', 'pairs', 'saved', 'bobots', 'latestConfig', 'userId'
        ));
    }

    public function dashboard(Request $request)
    {
        $userId = SpkDssActorId::resolve($request);
        $produks = MasterProduk::withCount('suppliers')->orderBy('nama')->get();
        $produkId = (int) $request->input('produk_id', $produks->first()?->id ?? 0);

        $bobots = $userId !== null
            ? SpkAhpBobot::where('user_id', $userId)->where('is_valid', true)->with('parameter')->get()
            : collect();

        $latestConfig = $userId !== null
            ? SpkAhpConfiguration::where('user_id', $userId)->where('is_valid', true)->orderByDesc('version')->first()
            : null;

        $rankings = collect();
        $evaluation = [];
        $insights = collect();

        if ($userId !== null && $produkId && $bobots->isNotEmpty()) {
            $rankings = $this->sawService->getRecommendations($userId, $produkId);
            $rankings->load('supplier', 'produk');
            $evaluation = $this->sawService->getEvaluationMatrix($produkId, $userId);
            $insights = $this->insightService->generateInsights($userId, $produkId);
        }

        $configHistory = $userId !== null
            ? SpkAhpConfiguration::where('user_id', $userId)->orderByDesc('version')->limit(5)->get()
            : collect();

        return view('spk.suppliers.dss-dashboard', compact(
            'produks', 'produkId', 'bobots', 'latestConfig', 'rankings',
            'evaluation', 'insights', 'configHistory', 'userId'
        ));
    }

    public function storePerbandingan(Request $request)
    {
        $validated = $request->validate([
            'perbandingans' => 'required|array|min:1',
            'perbandingans.*.parameter_1_id' => 'required|exists:spk_parameters,id',
            'perbandingans.*.parameter_2_id' => 'required|exists:spk_parameters,id',
            'perbandingans.*.nilai_skala' => 'required|numeric|min:0.111|max:9',
        ]);

        $userId = SpkDssActorId::resolve($request);
        if ($userId === null) {
            return back()->withInput()->with(
                'error',
                'Pengguna login tidak bisa dipetakan ke tabel pengguna aplikasi (`user`). '.
                    'Pastikan ID session atau email akun Anda ada di `user`. '.
                    'Konfigurasi AHP tidak disimpan.'
            );
        }

        foreach ($validated['perbandingans'] as $p) {
            SpkAhpPerbandingan::updateOrCreate(
                [
                    'user_id' => $userId,
                    'parameter_1_id' => $p['parameter_1_id'],
                    'parameter_2_id' => $p['parameter_2_id'],
                ],
                ['nilai_skala' => (float) $p['nilai_skala']]
            );
        }

        $result = $this->ahpService->calculateAndSaveWeights($userId);

        if (! $result) {
            return back()->with('error', 'Minimal 2 kriteria diperlukan untuk perhitungan AHP.');
        }

        if (! $result['is_valid']) {
            return back()
                ->with('error', 'Input penilaian mengandung inkonsistensi tinggi (CR > 0.1). Silakan tinjau ulang perbandingan berpasangan.')
                ->with('ahp_result', $result);
        }

        return redirect()
            ->route('spk.suppliers.dss.dashboard')
            ->with('success', 'Konfigurasi AHP berhasil disimpan. CR = '.$result['cr']);
    }

    public function apiEvaluation(Request $request, int $produkId)
    {
        return response()->json(
            $this->sawService->getEvaluationMatrix($produkId, SpkDssActorId::resolve($request))
        );
    }

    public function apiRankings(Request $request, int $produkId)
    {
        $userId = SpkDssActorId::resolve($request);
        if ($userId === null) {
            return response()->json([
                'message' => 'User tidak dikenali untuk DSS (tidak ada user.id atau email pemetaan).',
            ], 401);
        }
        $force = $request->boolean('recalculate');

        $rankings = $this->sawService->getRecommendations($userId, $produkId, $force);
        if ($rankings->isEmpty()) {
            return response()->json([
                'message' => 'Tidak ada peringkat. Pastikan bobot AHP valid (CR <= 0.1) dan produk memiliki supplier dengan nilai parameter.',
            ], 404);
        }

        $rankings->load('supplier');

        return response()->json($rankings->map(fn ($r) => [
            'supplier' => $r->supplier->nama,
            'supplier_id' => $r->supplier_id,
            'score' => round($r->final_score, 4),
            'rank' => $r->ranking,
        ]));
    }

    public function apiInsights(Request $request)
    {
        $userId = SpkDssActorId::resolve($request);
        if ($userId === null) {
            return response()->json(['message' => 'User tidak dikenali untuk DSS.'], 401);
        }
        $produkId = $request->input('produk_id');

        return response()->json(
            $this->insightService->generateInsights($userId, $produkId ? (int) $produkId : null)
        );
    }

    public function apiWeights()
    {
        $userId = SpkDssActorId::resolve(request());
        if ($userId === null) {
            return response()->json(['message' => 'User tidak dikenali untuk DSS.'], 401);
        }
        $bobots = SpkAhpBobot::where('user_id', $userId)
            ->where('is_valid', true)
            ->with('parameter')
            ->get();

        $config = SpkAhpConfiguration::where('user_id', $userId)
            ->where('is_valid', true)
            ->orderByDesc('version')
            ->first();

        return response()->json([
            'cr' => $config?->cr,
            'is_valid' => (bool) ($config?->is_valid ?? false),
            'version' => $config?->version,
            'weights' => $bobots->map(fn ($b) => [
                'criteria' => $b->parameter->nama_parameter,
                'type' => $b->parameter->tipe,
                'weight' => round($b->bobot, 4),
            ]),
        ]);
    }

    private function generatePairs($parameters): array
    {
        $pairs = [];
        $items = $parameters->values();
        $count = $items->count();

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $pairs[] = [
                    'parameter_1' => $items[$i],
                    'parameter_2' => $items[$j],
                ];
            }
        }

        return $pairs;
    }
}
