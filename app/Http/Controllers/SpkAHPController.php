<?php

namespace App\Http\Controllers;

use App\Models\SpkAhpPerbandingan;
use App\Services\AHPService;
use App\Support\SpkDssActorId;
use Illuminate\Http\Request;

class SpkAHPController extends Controller
{
    protected $ahpService;

    public function __construct(AHPService $ahpService)
    {
        $this->ahpService = $ahpService;
    }

    public function storePerbandingan(Request $request)
    {
        $validated = $request->validate([
            'perbandingans' => 'required|array',
            'perbandingans.*.parameter_1_id' => 'required|exists:spk_parameters,id',
            'perbandingans.*.parameter_2_id' => 'required|exists:spk_parameters,id',
            'perbandingans.*.nilai_skala' => 'required|numeric|min:0.1|max:9',
        ]);

        $userId = SpkDssActorId::resolve($request);
        if ($userId === null) {
            return response()->json([
                'message' => 'User tidak dikenali untuk DSS: tidak ada baris cocok di tabel users (id session tidak valid atau email tidak cocok).',
            ], 422);
        }

        foreach ($validated['perbandingans'] as $p) {
            SpkAhpPerbandingan::updateOrCreate(
                [
                    'user_id' => $userId,
                    'parameter_1_id' => $p['parameter_1_id'],
                    'parameter_2_id' => $p['parameter_2_id']
                ],
                [
                    'nilai_skala' => $p['nilai_skala']
                ]
            );
        }

        $result = $this->ahpService->calculateAndSaveWeights($userId);

        if ($result && !$result['is_valid']) {
            return response()->json([
                'message' => 'Input penilaian mengandung inkonsistensi tinggi (CR > 0.1). Silakan tinjau ulang perbandingan berpasangan.',
                'cr' => $result['cr'],
            ], 422);
        }

        return response()->json([
            'message' => 'AHP weights calculated successfully',
            'data' => $result
        ]);
    }
}
