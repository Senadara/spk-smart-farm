<?php

namespace App\Services\Fuzzy;

use App\Models\SpkFuzzyVariable;
use App\Models\SpkFuzzyRule;
use Illuminate\Support\Facades\Cache;

/**
 * MamdaniEngine — Cascaded 3-Engine Fuzzy Inference System.
 *
 * Arsitektur:
 *   Engine 1 (group=lingkungan): Suhu+Kelembapan+Amonia → Status Lingkungan
 *   Engine 2 (group=kesehatan) : HDP+Pakan+Mortalitas  → Indeks Kesehatan
 *   Engine 3 (group=kausalitas): Label E1 + Label E2   → Diagnosis Kausalitas (lookup)
 */
class MamdaniEngine
{
    protected float $universeMin  = 0.0;
    protected float $universeMax  = 100.0;
    protected float $universeStep = 0.5;   // resolusi centroid (lebih kecil = lebih akurat)
    protected int   $cacheTtl     = 3600;  // 1 jam

    // ──────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ──────────────────────────────────────────────────────────────────

    /**
     * Jalankan 3 engine secara berurutan (cascaded).
     *
     * @param  array<string,float> $inputs Unified input dari InputResolver
     * @return array
     */
    public function processCascaded(array $inputs): array
    {
        $lingkungan = $this->processGroup('lingkungan', $inputs);
        $kesehatan  = $this->processGroup('kesehatan', $inputs);
        $kausalitas = $this->lookupKausalitas($lingkungan['label'], $kesehatan['label']);

        return [
            'inputs'     => $inputs,
            'lingkungan' => $lingkungan,
            'kesehatan'  => $kesehatan,
            'kausalitas' => $kausalitas,
        ];
    }

    /**
     * Jalankan satu engine group (Mamdani penuh).
     *
     * @param  string              $group  'lingkungan' | 'kesehatan'
     * @param  array<string,float> $inputs
     * @return array
     */
    public function processGroup(string $group, array $inputs): array
    {
        $variables = $this->loadVariables($group);
        $rules     = $this->loadRules($group);

        if (empty($rules)) {
            return $this->emptyResult("Tidak ada rules untuk group '{$group}'.");
        }

        // 1. Fuzzifikasi
        $fuzzified = $this->fuzzify($variables['input'], $inputs);

        // 2. Evaluasi rule → {set_id => max_alpha}
        [$aggregated, $dominantRule] = $this->evaluateRules($rules, $fuzzified);

        if (empty($aggregated)) {
            return $this->emptyResult("Tidak ada rule yang aktif untuk group '{$group}'.");
        }

        // 3. Defuzzifikasi (Centroid)
        [$crispValue, $label] = $this->defuzzify($variables['output'], $aggregated);

        return [
            'value'        => $crispValue,
            'label'        => $label,
            'fuzzified'    => $fuzzified,
            'rule_results' => $aggregated,
            'dominant_rule'=> $dominantRule,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // STEP 1 — FUZZIFIKASI
    // ──────────────────────────────────────────────────────────────────

    /**
     * Hitung µ(x) untuk setiap set dari setiap variabel input.
     *
     * @param  \Illuminate\Support\Collection $inputVars
     * @param  array<string,float>            $inputs
     * @return array<string, array<string, float>>
     *         ['suhu' => ['Dingin'=>0.6, 'Nyaman'=>0.4, 'Panas'=>0.0], ...]
     */
    private function fuzzify($inputVars, array $inputs): array
    {
        $result = [];

        foreach ($inputVars as $var) {
            $varName = $var->name;
            $x       = (float) ($inputs[$varName] ?? 0.0);
            $result[$varName] = [];

            foreach ($var->sets as $set) {
                $result[$varName][$set->name] = $set->membership($x);
            }
        }

        return $result;
    }

    // ──────────────────────────────────────────────────────────────────
    // STEP 2 — EVALUASI RULE
    // ──────────────────────────────────────────────────────────────────

    /**
     * Evaluasi semua rule. Kembalikan:
     *   - $aggregated : {set_id => max_alpha} untuk defuzzifikasi
     *   - $dominantRule: rule dengan alpha tertinggi
     */
    private function evaluateRules(array $rules, array $fuzzified): array
    {
        $aggregated    = [];  // set_id => max_alpha
        $dominantRule  = null;
        $dominantAlpha = -1.0;

        foreach ($rules as $rule) {
            $alpha = $this->computeAlpha($rule, $fuzzified);

            if ($alpha <= 0) {
                continue;
            }

            $setId = $rule['output_set_id'];

            // Aggregasi: ambil nilai alpha terbesar per output set (MAX)
            if (!isset($aggregated[$setId]) || $alpha > $aggregated[$setId]) {
                $aggregated[$setId] = $alpha;
            }

            // Simpan rule dengan alpha tertinggi sebagai dominant
            if ($alpha > $dominantAlpha) {
                $dominantAlpha = $alpha;
                $dominantRule  = array_merge($rule, ['alpha' => $alpha]);
            }
        }

        return [$aggregated, $dominantRule];
    }

    /**
     * Hitung alpha (firing strength) satu rule berdasarkan operator AND/OR.
     */
    private function computeAlpha(array $rule, array $fuzzified): float
    {
        $alphas = [];

        foreach ($rule['conditions'] as $cond) {
            $varName = $cond['variable_name'];
            $setName = $cond['set_name'];
            $mu      = $fuzzified[$varName][$setName] ?? 0.0;
            $alphas[] = $mu;
        }

        if (empty($alphas)) {
            return 0.0;
        }

        return $rule['operator'] === 'OR' ? max($alphas) : min($alphas);
    }

    // ──────────────────────────────────────────────────────────────────
    // STEP 3 — DEFUZZIFIKASI (Centroid)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Defuzzifikasi centroid atas fungsi keanggotaan output yang teragregasi.
     *
     * @param  \Illuminate\Support\Collection $outputVars  Variabel output group ini
     * @param  array<string,float>            $aggregated  {set_id => alpha}
     * @return array [float $crispValue, string $label]
     */
    private function defuzzify($outputVars, array $aggregated): array
    {
        // Ambil output variable (pertama yang ditemukan di group)
        $outputVar = $outputVars->first();

        if (!$outputVar) {
            return [0.0, 'Tidak Diketahui'];
        }

        // Indexkan set output berdasarkan ID
        $sets = $outputVar->sets->keyBy('id');

        $numerator   = 0.0;
        $denominator = 0.0;

        // Integrasi numerik (composite trapezoidal)
        for ($x = $this->universeMin; $x <= $this->universeMax; $x += $this->universeStep) {
            $maxMu = 0.0;

            // Agregasi: ambil maksimum membership dari semua set yang aktif di titik x
            foreach ($aggregated as $setId => $alpha) {
                $set = $sets[$setId] ?? null;
                if (!$set) {
                    continue;
                }
                // Clipping: µ_clipped(x) = min(alpha, µ_set(x))
                $mu    = $set->membership($x);
                $clipped = min($alpha, $mu);
                if ($clipped > $maxMu) {
                    $maxMu = $clipped;
                }
            }

            $numerator   += $x * $maxMu;
            $denominator += $maxMu;
        }

        $crispValue = $denominator > 0 ? round($numerator / $denominator, 2) : 0.0;

        // Tentukan label dari set dengan alpha tertinggi
        $label = $this->determineLabelFromCrisp($sets, $aggregated, $crispValue);

        return [$crispValue, $label];
    }

    /**
     * Tentukan label output: set mana yang paling aktif di posisi crisp value.
     */
    private function determineLabelFromCrisp($sets, array $aggregated, float $crispValue): string
    {
        // Prioritas 1: set dengan alpha tertinggi
        $maxAlpha  = -1;
        $bestLabel = 'Tidak Diketahui';

        foreach ($aggregated as $setId => $alpha) {
            if ($alpha > $maxAlpha) {
                $maxAlpha  = $alpha;
                $set       = $sets[$setId] ?? null;
                $bestLabel = $set ? $set->name : 'Tidak Diketahui';
            }
        }

        return $bestLabel;
    }

    // ──────────────────────────────────────────────────────────────────
    // ENGINE 3 — LOOKUP KAUSALITAS (non-Mamdani, label-based)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Lookup rule kausalitas berdasarkan kombinasi label lingkungan + kesehatan.
     *
     * @param  string $lingkLabel  e.g. 'Optimal', 'Waspada', 'Buruk'
     * @param  string $kesehatanLabel
     * @return array
     */
    public function lookupKausalitas(string $lingkLabel, string $kesehatanLabel): array
    {
        $rules = $this->loadRules('kausalitas');

        foreach ($rules as $rule) {
            $condLabels = array_column($rule['conditions'], 'set_name');

            // Rule cocok jika kondisi mengandung kedua label yang dicari
            $hasLingk    = in_array($lingkLabel, $condLabels, true);
            $hasKesehatan = in_array($kesehatanLabel, $condLabels, true);

            if ($hasLingk && $hasKesehatan) {
                return [
                    'label'          => $rule['output_set_name'] ?? 'Tidak Diketahui',
                    'diagnosis'      => $rule['diagnosis'] ?? '-',
                    'recommendation' => $rule['recommendation'] ?? '-',
                    'matched_rule'   => $rule['name'] ?? null,
                ];
            }
        }

        // Fallback jika tidak ada rule yang cocok
        return [
            'label'          => 'Tidak Diketahui',
            'diagnosis'      => 'Kombinasi kondisi belum terdefinisi',
            'recommendation' => 'Lakukan evaluasi manual',
            'matched_rule'   => null,
        ];
    }

    // ──────────────────────────────────────────────────────────────────
    // CACHE LOADERS
    // ──────────────────────────────────────────────────────────────────

    private function loadVariables(string $group): array
    {
        $cacheKey = "fuzzy_vars_{$group}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($group) {
            $vars = SpkFuzzyVariable::with('sets')
                ->where('group', $group)
                ->get();

            return [
                'input'  => $vars->where('type', 'input'),
                'output' => $vars->where('type', 'output'),
            ];
        });
    }

    private function loadRules(string $group): array
    {
        $cacheKey = "fuzzy_rules_{$group}";

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($group) {
            $rules = SpkFuzzyRule::with([
                'conditions.variable',
                'conditions.set',
                'outputSet',
            ])->where('group', $group)->get();

            return $rules->map(function ($rule) {
                return [
                    'id'              => $rule->id,
                    'name'            => $rule->name,
                    'operator'        => $rule->operator,
                    'output_set_id'   => $rule->output_set_id,
                    'output_set_name' => $rule->outputSet?->name,
                    'diagnosis'       => $rule->diagnosis,
                    'recommendation'  => $rule->diagnosis,
                    'conditions'      => $rule->conditions->map(fn($c) => [
                        'variable_name' => $c->variable?->name,
                        'set_name'      => $c->set?->name,
                        'set_id'        => $c->set_id,
                    ])->toArray(),
                ];
            })->toArray();
        });
    }

    /**
     * Bersihkan cache fuzzy config (dipanggil saat seeder/admin update config).
     */
    public static function clearCache(): void
    {
        foreach (['lingkungan', 'kesehatan', 'kausalitas'] as $group) {
            Cache::forget("fuzzy_vars_{$group}");
            Cache::forget("fuzzy_rules_{$group}");
        }
    }

    private function emptyResult(string $reason): array
    {
        return [
            'value'         => 0.0,
            'label'         => 'Tidak Diketahui',
            'fuzzified'     => [],
            'rule_results'  => [],
            'dominant_rule' => null,
            'reason'        => $reason,
        ];
    }
}
