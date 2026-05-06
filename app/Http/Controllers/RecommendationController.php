<?php

namespace App\Http\Controllers;

use App\Services\SAWRecommenderService;
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
        $userId = $request->user() ? $request->user()->id : 1; 

        $rankings = $this->recommender->getRecommendations($userId, $produkId);

        if ($rankings->isEmpty()) {
            return response()->json([
                'message' => 'No valid rankings available. Please ensure AHP weights are valid and product has suppliers.'
            ], 404);
        }

        $rankings->load(['supplier', 'produk']);

        return response()->json($rankings);
    }
}
