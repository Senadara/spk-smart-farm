<?php

namespace App\Services\SPKMelon;

/**
 * Utility statis untuk konversi Skala Saaty ke Triangular Fuzzy Number (TFN).
 *
 * Implementasi mengikuti spesifikasi di algorithm-implementation.md:
 * - Formula umum: Saaty n → TFN (n-1, n, n+1)
 * - Pengecualian: Saaty 1 → (1,1,1), Saaty 9 → (8,9,9)
 * - Resiprokal: TFN (1/u, 1/m, 1/l) — urutan dibalik
 *
 * @see docs/references/algorithm-implementation.md Stage 2
 */
class TfnHelper
{
    /**
     * Mapping Skala Saaty (1-9) ke Triangular Fuzzy Number (l, m, u).
     */
    private const TFN_MAP = [
        1 => [1, 1, 1],
        2 => [1, 2, 3],
        3 => [2, 3, 4],
        4 => [3, 4, 5],
        5 => [4, 5, 6],
        6 => [5, 6, 7],
        7 => [6, 7, 8],
        8 => [7, 8, 9],
        9 => [8, 9, 9],
    ];

    /**
     * Daftar nilai Saaty valid untuk dropdown UI.
     * Termasuk nilai utuh (1-9) dan resiprokal (1/2 - 1/9).
     */
    public const VALID_SAATY_VALUES = [
        1.0,
        2.0, 3.0, 4.0, 5.0, 6.0, 7.0, 8.0, 9.0,
        0.5, 0.333333, 0.25, 0.2, 0.166667, 0.142857, 0.125, 0.111111,
    ];

    /**
     * Konversi nilai Saaty ke TFN [l, m, u].
     *
     * @param float $saaty Nilai Saaty (1-9 atau resiprokal 1/2-1/9)
     * @return array [l, m, u]
     */
    public static function saatyToTfn(float $saaty): array
    {
        // Kasus resiprokal: nilai 0 < saaty < 1
        if ($saaty < 1 && $saaty > 0) {
            $inverse = (int) round(1 / $saaty);
            $tfn = self::TFN_MAP[$inverse] ?? null;

            if ($tfn === null) {
                return [1, 1, 1];
            }

            // Resiprokal TFN: (1/u, 1/m, 1/l) — urutan dibalik
            return [
                round(1 / $tfn[2], 6),
                round(1 / $tfn[1], 6),
                round(1 / $tfn[0], 6),
            ];
        }

        // Kasus nilai utuh
        $key = (int) $saaty;
        return self::TFN_MAP[$key] ?? [1, 1, 1];
    }

    /**
     * Konversi pasangan integer Saaty ke nilai pecahan resiprokal.
     * Contoh: saatyReciprocal(3) → 1/3 ≈ 0.333333
     */
    public static function saatyReciprocal(float $saaty): float
    {
        if ($saaty == 0) {
            return 0;
        }
        return round(1 / $saaty, 6);
    }

    /**
     * Label verbal sesuai Skala Saaty asli (untuk dropdown UI).
     */
    public static function labelVerbal(float $saaty): string
    {
        $absSaaty = $saaty >= 1 ? $saaty : (1 / $saaty);
        $direction = $saaty >= 1 ? '' : ' (kebalikan)';

        $labels = [
            1 => 'Sama Penting',
            2 => 'Sama hingga Sedikit Lebih Penting',
            3 => 'Sedikit Lebih Penting',
            4 => 'Sedikit hingga Lebih Penting',
            5 => 'Lebih Penting',
            6 => 'Lebih hingga Sangat Lebih Penting',
            7 => 'Sangat Lebih Penting',
            8 => 'Sangat Lebih hingga Mutlak Lebih Penting',
            9 => 'Mutlak Lebih Penting',
        ];

        $key = (int) round($absSaaty);
        return ($labels[$key] ?? '') . $direction;
    }
}
