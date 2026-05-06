<?php

namespace App\Http\Controllers;

use App\Models\SpkAhpPerbandingan;
use App\Services\AHPService;
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

        $userId = $request->user() ? $request->user()->id : 1; 

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
                'message' => 'Weights calculated but Consistency Ratio is invalid (> 0.1)',
                'cr' => $result['cr']
            ], 422);
        }

        return response()->json([
            'message' => 'AHP weights calculated successfully',
            'data' => $result
        ]);
    }
}
