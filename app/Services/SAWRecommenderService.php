<?php

namespace App\Services;

use App\Models\MasterProduk;
use App\Models\MasterSupplier;
use App\Models\SpkAhpBobot;
use App\Models\SpkParameter;
use App\Models\SpkRanking;
use App\Models\SpkSupplierParameterValue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use stdClass;

class SAWRecommenderService
{
    public function __construct(
        private NormalizationService $normalizer,
        private SupplierInsightService $insightService,
        private SupplierDistanceService $distanceService,
    ) {}

    public function getRecommendations(string $userId, int $produkId, bool $forceRecalculate = false)
    {
        if (! $forceRecalculate) {
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
            return new EloquentCollection;
        }

        $bobotMap = $bobots->pluck('bobot', 'parameter_id');

        $produk = MasterProduk::with('suppliers')->find($produkId);
        if (! $produk || $produk->suppliers->isEmpty()) {
            return new EloquentCollection;
        }

        $suppliers = $produk->suppliers->keyBy('id');
        $supplierIds = $suppliers->keys();
        $parameters = SpkParameter::all();
        $paramTypes = $parameters->pluck('tipe', 'id')->toArray();

        $paramValues = SpkSupplierParameterValue::whereIn('supplier_id', $supplierIds)
            ->where('produk_id', $produkId)
            ->get();

        $minMax = $this->normalizer->computeMinMax(
            $this->withRuntimeDistanceValues($paramValues, $parameters, $suppliers, $userId)
        );

        $scores = [];
        foreach ($supplierIds as $sid) {
            $valuesByParameter = [];
            foreach ($parameters as $param) {
                if ($this->isDistanceParameter($param)) {
                    $valuesByParameter[$param->id] = $this->distanceValue($suppliers[$sid], $userId);

                    continue;
                }

                $pv = $paramValues->where('supplier_id', $sid)->where('parameter_id', $param->id)->first();
                if ($pv) {
                    $valuesByParameter[$param->id] = (float) $pv->value;
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
    public function getEvaluationMatrix(int $produkId, ?string $userId = null): array
    {
        $produk = MasterProduk::with('suppliers')->find($produkId);
        if (! $produk) {
            return [];
        }

        $parameters = SpkParameter::all()->keyBy('id');
        $paramValues = SpkSupplierParameterValue::where('produk_id', $produkId)
            ->whereIn('supplier_id', $produk->suppliers->pluck('id'))
            ->get();

        $suppliers = $produk->suppliers->keyBy('id');
        $minMax = $this->normalizer->computeMinMax(
            $this->withRuntimeDistanceValues($paramValues, $parameters, $suppliers, $userId)
        );
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
                if ($this->isDistanceParameter($param)) {
                    $val = $this->distanceValue($supplier, $userId);
                } else {
                    $pv = $paramValues->where('supplier_id', $supplier->id)
                        ->where('parameter_id', $param->id)
                        ->first();
                    $val = $pv?->value;
                }

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
            'jarak' => 'distance',
        ];

        $lower = strtolower($nama);
        foreach ($map as $needle => $key) {
            if (str_contains($lower, $needle)) {
                return $key;
            }
        }

        return str_replace(' ', '_', strtolower($nama));
    }

    private function isDistanceParameter(SpkParameter $parameter): bool
    {
        return str_contains(strtolower($parameter->nama_parameter), 'jarak');
    }

    private function distanceValue(MasterSupplier $supplier, ?string $userId): float
    {
        return $this->distanceService->distanceToSupplier($supplier, $userId) ?? 9999.0;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, SpkSupplierParameterValue>  $storedValues
     * @param  \Illuminate\Support\Collection<int, SpkParameter>  $parameters
     * @param  \Illuminate\Support\Collection<int, MasterSupplier>  $suppliers
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function withRuntimeDistanceValues($storedValues, $parameters, $suppliers, ?string $userId)
    {
        $values = collect($storedValues);
        $distanceParameters = $parameters->filter(fn (SpkParameter $param) => $this->isDistanceParameter($param));

        foreach ($distanceParameters as $parameter) {
            foreach ($suppliers as $supplier) {
                $row = new stdClass;
                $row->supplier_id = $supplier->id;
                $row->parameter_id = $parameter->id;
                $row->value = $this->distanceValue($supplier, $userId);
                $values->push($row);
            }
        }

        return $values;
    }
}
