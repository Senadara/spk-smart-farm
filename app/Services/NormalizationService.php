<?php

namespace App\Services;

class NormalizationService
{
    /**
     * @param  array<int, array{parameter_id: int, value: float}>  $valuesByParameter
     * @param  array<int, string>  $paramTypes  parameter_id => benefit|cost
     * @return array<int, float> parameter_id => normalized value
     */
    public function normalizeForEntity(array $valuesByParameter, array $paramTypes, array $minMaxByParameter): array
    {
        $normalized = [];

        foreach ($valuesByParameter as $parameterId => $value) {
            $type = $paramTypes[$parameterId] ?? 'benefit';
            $min = $minMaxByParameter[$parameterId]['min'] ?? 0;
            $max = $minMaxByParameter[$parameterId]['max'] ?? 0;

            if ($type === 'benefit') {
                $normalized[$parameterId] = $max != 0 ? $value / $max : 0;
            } else {
                $normalized[$parameterId] = $value != 0 ? $min / $value : 0;
            }
        }

        return $normalized;
    }

    /**
     * @param  iterable  $parameterValues  rows with parameter_id, value
     * @return array<int, array{min: float, max: float}>
     */
    public function computeMinMax(iterable $parameterValues): array
    {
        $minMax = [];

        foreach ($parameterValues as $pv) {
            $pid = $pv->parameter_id;
            if (!isset($minMax[$pid])) {
                $minMax[$pid] = ['min' => PHP_FLOAT_MAX, 'max' => -PHP_FLOAT_MAX];
            }
            if ($pv->value < $minMax[$pid]['min']) {
                $minMax[$pid]['min'] = $pv->value;
            }
            if ($pv->value > $minMax[$pid]['max']) {
                $minMax[$pid]['max'] = $pv->value;
            }
        }

        return $minMax;
    }
}
