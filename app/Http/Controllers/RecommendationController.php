<?php

namespace App\Http\Controllers;

use App\Services\SAWRecommenderService;
use App\Support\SpkDssActorId;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    protected $recommender;

    public function __construct(SAWRecommenderService $recommender)
    {
        $this->recommender = $recommender;
    }

    public function getRanking(Request $request, $produkId)
    {
        $userId = SpkDssActorId::resolve($request);
        if ($userId === null) {
            return response()->json([
                'message' => 'User DSS tidak dikenali. Pastikan id pengguna di session valid atau email Anda terdaftar di tabel users.',
            ], 401);
        }

        $rankings = $this->recommender->getRecommendations(
            $userId,
            (int) $produkId,
            $request->boolean('recalculate')
        );

        if ($rankings->isEmpty()) {
            return response()->json([
                'message' => 'No valid rankings available. Please ensure AHP weights are valid and product has suppliers.'
            ], 404);
        }

        $rankings->load(['supplier', 'produk']);

        return response()->json($rankings->map(fn ($r) => [
            'supplier' => $r->supplier->nama ?? '',
            'supplier_id' => $r->supplier_id,
            'score' => round($r->final_score, 4),
            'rank' => $r->ranking,
        ]));
    }
}
