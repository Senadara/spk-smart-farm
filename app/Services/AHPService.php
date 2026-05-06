<?php

namespace App\Services;

use App\Models\SpkParameter;
use App\Models\SpkAhpPerbandingan;
use App\Models\SpkAhpBobot;

class AHPService
{
    // Random Index (RI) table for AHP (up to 10 parameters)
    private $riTable = [
        1 => 0.00, 2 => 0.00, 3 => 0.58, 4 => 0.90, 5 => 1.12,
        6 => 1.24, 7 => 1.32, 8 => 1.41, 9 => 1.45, 10 => 1.49
    ];

    public function calculateAndSaveWeights($userId)
    {
        $parameters = SpkParameter::all();
        $n = $parameters->count();
        if ($n < 2) return false;

        $matrix = $this->buildComparisonMatrix($userId, $parameters);
        
        // 1. Column sums
        $colSums = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $colSums[$j] += $matrix[$i][$j];
            }
        }

        // 2. Normalize matrix & find Eigen Vector (weights)
        $weights = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) {
            $rowSum = 0;
            for ($j = 0; $j < $n; $j++) {
                // Handle division by zero just in case
                $normalized = $colSums[$j] > 0 ? $matrix[$i][$j] / $colSums[$j] : 0;
                $rowSum += $normalized;
            }
            $weights[$i] = $rowSum / $n;
        }

        // 3. Consistency Index (CI) and Ratio (CR)
        $wsv = array_fill(0, $n, 0);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $wsv[$i] += $matrix[$i][$j] * $weights[$j];
            }
        }

        $lamdaMax = 0;
        for ($i = 0; $i < $n; $i++) {
            if ($weights[$i] > 0) {
                $lamdaMax += $wsv[$i] / $weights[$i];
            }
        }
        $lamdaMax = $lamdaMax / $n;

        $ci = ($lamdaMax - $n) / ($n - 1);
        $ri = $this->riTable[$n] ?? 1.49;
        
        $cr = $ri == 0 ? 0 : $ci / $ri;
        $isValid = $cr <= 0.1;

        // 4. Save to DB
        foreach ($parameters as $index => $param) {
            SpkAhpBobot::updateOrCreate(
                ['user_id' => $userId, 'parameter_id' => $param->id],
                ['bobot' => $weights[$index], 'is_valid' => $isValid]
            );
        }

        return ['cr' => $cr, 'is_valid' => $isValid, 'weights' => $weights];
    }

    private function buildComparisonMatrix($userId, $parameters)
    {
        $n = $parameters->count();
        $matrix = array_fill(0, $n, array_fill(0, $n, 1));
        
        $paramIds = $parameters->pluck('id')->toArray();
        $paramIndexMap = array_flip($paramIds);

        $perbandingans = SpkAhpPerbandingan::where('user_id', $userId)->get();

        foreach ($perbandingans as $p) {
            if (isset($paramIndexMap[$p->parameter_1_id]) && isset($paramIndexMap[$p->parameter_2_id])) {
                $i = $paramIndexMap[$p->parameter_1_id];
                $j = $paramIndexMap[$p->parameter_2_id];
                
                $matrix[$i][$j] = $p->nilai_skala;
                if ($p->nilai_skala > 0) {
                    $matrix[$j][$i] = 1 / $p->nilai_skala;
                }
            }
        }

        return $matrix;
    }
}
