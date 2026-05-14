<?php

namespace App\Services;

use App\Models\SpkRanking;
use App\Models\SpkAhpBobot;
use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use App\Models\MasterProduk;
use Illuminate\Support\Facades\DB;

class SAWRecommenderService
{
    public function getRecommendations($userId, $produkId)
    {
        // Cache Check
        $cached = SpkRanking::where('user_id', $userId)
            ->where('produk_id', $produkId)
            ->where('is_valid', true)
            ->orderBy('ranking', 'asc')
            ->get();

        if ($cached->isNotEmpty()) {
            return $cached;
        }

        // Invalidating old caches manually just in case
        SpkRanking::where('user_id', $userId)->where('produk_id', $produkId)->delete();

        // 1. Fetch Valid Bobot
        $bobots = SpkAhpBobot::where('user_id', $userId)->where('is_valid', true)->get();
        if ($bobots->isEmpty()) {
            return collect([]); // User hasn't finished AHP or not valid
        }

        $bobotMap = $bobots->pluck('bobot', 'parameter_id');

        // 2. Get Suppliers representing this produk
        $produk = MasterProduk::with('suppliers')->find($produkId);
        if (!$produk || $produk->suppliers->isEmpty()) {
            return collect([]);
        }

        $supplierIds = $produk->suppliers->pluck('id');

        // 3. Get Parameters & their min/max for normalization
        $parameters = SpkParameter::all();
        $paramTypes = $parameters->pluck('tipe', 'id');
        
        $paramValues = SpkSupplierParameterValue::whereIn('supplier_id', $supplierIds)
            ->where('produk_id', $produkId)
            ->get();
            
        // Calculate min/max
        $minMax = [];
        foreach ($paramValues as $pv) {
            $pid = $pv->parameter_id;
            if (!isset($minMax[$pid])) {
                $minMax[$pid] = ['min' => 999999999, 'max' => -999999999];
            }
            if ($pv->value < $minMax[$pid]['min']) $minMax[$pid]['min'] = $pv->value;
            if ($pv->value > $minMax[$pid]['max']) $minMax[$pid]['max'] = $pv->value;
        }

        // 4. Normalize and calculate score
        $scores = [];
        foreach ($supplierIds as $sid) {
            $finalScore = 0;
            foreach ($parameters as $param) {
                $pid = $param->id;
                $pv = $paramValues->where('supplier_id', $sid)->where('parameter_id', $pid)->first();
                if (!$pv) continue; // Missing value

                $val = $pv->value;
                $normalized = 0;

                if ($paramTypes[$pid] === 'benefit') {
                    $normalized = $minMax[$pid]['max'] != 0 ? $val / $minMax[$pid]['max'] : 0;
                } else { // cost
                    $normalized = $val != 0 ? $minMax[$pid]['min'] / $val : 0;
                }

                $bobot = $bobotMap->get($pid, 0);
                $finalScore += $normalized * $bobot;
            }
            
            $scores[] = [
                'supplier_id' => $sid,
                'final_score' => $finalScore,
            ];
        }

        // 5. Sort to determine ranking
        usort($scores, function($a, $b) {
            return $b['final_score'] <=> $a['final_score'];
        });

        // 6. Save to cache
        $rankingData = [];
        foreach ($scores as $index => $score) {
            $rank = $index + 1;
            // update or create
            $spkRanking = SpkRanking::create([
                'user_id' => $userId,
                'produk_id' => $produkId,
                'supplier_id' => $score['supplier_id'],
                'final_score' => $score['final_score'],
                'ranking' => $rank,
                'is_valid' => true,
                'last_calculated_at' => now()
            ]);
            $rankingData[] = clone $spkRanking;
        }

        return collect($rankingData);
    }
}
