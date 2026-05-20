<?php

namespace App\Services;

use App\Models\SpkRanking;
use App\Models\SpkAhpBobot;
use App\Models\SpkParameter;
use App\Models\SpkSupplierParameterValue;
use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class SAWRecommenderService
{
    public function __construct(
        private NormalizationService $normalizer,
        private SupplierInsightService $insightService,
    ) {}

    public function getRecommendations(int $userId, int $produkId, bool $forceRecalculate = false)
    {
        if (!$forceRecalculate) {
            $cached = SpkRanking::where('user_id', $userId)
                ->where('produk_id', $produkId)
                ->where('is_valid', true)
                ->orderBy('ranking', 'asc')
                ->get();

            if ($cached->isNotEmpty()) {
                return $cached;
            }
        }

        SpkRanking::where('user_id', $userId)->where('produk_id', $produkId)->delete();

        $bobots = SpkAhpBobot::where('user_id', $userId)->where('is_valid', true)->get();
        if ($bobots->isEmpty()) {
            return new EloquentCollection();
        }

        $bobotMap = $bobots->pluck('bobot', 'parameter_id');

        $produk = MasterProduk::with('suppliers')->find($produkId);
        if (!$produk || $produk->suppliers->isEmpty()) {
            return new EloquentCollection();
        }

        $supplierIds = $produk->suppliers->pluck('id');
        $parameters = SpkParameter::all();
        $paramTypes = $parameters->pluck('tipe', 'id')->toArray();

        $paramValues = SpkSupplierParameterValue::whereIn('supplier_id', $supplierIds)
            ->where('produk_id', $produkId)
            ->get();

        $minMax = $this->normalizer->computeMinMax($paramValues);

        $scores = [];
        foreach ($supplierIds as $sid) {
            $valuesByParameter = [];
            foreach ($parameters as $param) {
                $pv = $paramValues->where('supplier_id', $sid)->where('parameter_id', $param->id)->first();
                if ($pv) {
                    $valuesByParameter[$param->id] = $pv->value;
                }
            }

            $normalized = $this->normalizer->normalizeForEntity($valuesByParameter, $paramTypes, $minMax);

            $finalScore = 0;
            foreach ($normalized as $pid => $normVal) {
                $finalScore += $normVal * ($bobotMap->get($pid, 0));
            }

            $scores[] = [
                'supplier_id' => $sid,
                'final_score' => $finalScore,
            ];
        }

        usort($scores, fn ($a, $b) => $b['final_score'] <=> $a['final_score']);

        $rankingData = [];
        foreach ($scores as $index => $score) {
            $rank = $index + 1;
            $spkRanking = SpkRanking::create([
                'user_id' => $userId,
                'produk_id' => $produkId,
                'supplier_id' => $score['supplier_id'],
                'final_score' => $score['final_score'],
                'ranking' => $rank,
                'is_valid' => true,
                'last_calculated_at' => now(),
            ]);
            $rankingData[] = $spkRanking;

            if ($rank === 1) {
                $this->insightService->logSelection(
                    $userId,
                    $score['supplier_id'],
                    $produkId,
                    $score['final_score'],
                    $rank
                );
            }
        }

        return SpkRanking::where('user_id', $userId)
            ->where('produk_id', $produkId)
            ->where('is_valid', true)
            ->orderBy('ranking', 'asc')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEvaluationMatrix(int $produkId): array
    {
        $produk = MasterProduk::with('suppliers')->find($produkId);
        if (!$produk) {
            return [];
        }

        $parameters = SpkParameter::all()->keyBy('id');
        $paramValues = SpkSupplierParameterValue::where('produk_id', $produkId)
            ->whereIn('supplier_id', $produk->suppliers->pluck('id'))
            ->get();

        $minMax = $this->normalizer->computeMinMax($paramValues);
        $paramTypes = $parameters->pluck('tipe', 'id')->toArray();

        $result = [];
        foreach ($produk->suppliers as $supplier) {
            $row = [
                'id' => $supplier->id,
                'name' => $supplier->nama,
                'attributes' => [],
            ];

            $valuesByParameter = [];
            foreach ($parameters as $param) {
                $pv = $paramValues->where('supplier_id', $supplier->id)
                    ->where('parameter_id', $param->id)
                    ->first();
                $val = $pv?->value;
                $valuesByParameter[$param->id] = $val ?? 0;

                $key = $this->attributeKey($param->nama_parameter);
                $row['attributes'][$key] = $val;
            }

            $normalized = $this->normalizer->normalizeForEntity(
                array_filter($valuesByParameter, fn ($v) => $v !== null),
                $paramTypes,
                $minMax
            );

            $row['normalized'] = [];
            foreach ($parameters as $param) {
                $row['normalized'][$this->attributeKey($param->nama_parameter)] = $normalized[$param->id] ?? 0;
            }

            $result[] = $row;
        }

        return $result;
    }

    private function attributeKey(string $nama): string
    {
        $map = [
            'harga' => 'price',
            'kualitas' => 'quality',
            'kecepatan' => 'delivery_speed',
        ];

        $lower = strtolower($nama);
        foreach ($map as $needle => $key) {
            if (str_contains($lower, $needle)) {
                return $key;
            }
        }

        return str_replace(' ', '_', strtolower($nama));
    }
}
