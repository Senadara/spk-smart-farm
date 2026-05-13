<?php

namespace App\Services\SPKMelon;

use App\Models\SPKMelon\SpkMelonKriteria;
use App\Models\SPKMelon\SpkMelonPerbandingan;
use App\Models\SPKMelon\SpkMelonSesiPenilaian;

/**
 * Service untuk kalkulasi Fuzzy AHP.
 *
 * Stage 2: Pairwise comparison input (dilakukan di PerbandinganService — SPK-03)
 * Stage 3: Consistency Ratio validation (SPK-04 — implementasi saat ini)
 * Stage 4: Weight calculation (SPK-05 — belum diimplementasi)
 *
 * @see docs/references/algorithm-implementation.md Stage 3
 */
class FuzzyAhpService
{
    /**
     * Tabel Random Index (RI) dari Saaty (1980).
     * Indeks 1-2 bernilai 0 karena matriks dengan kurang dari 3 kriteria
     * selalu konsisten secara matematis.
     */
    private const RI_TABLE = [
        1  => 0.00, 2  => 0.00, 3  => 0.58, 4  => 0.90, 5  => 1.12,
        6  => 1.24, 7  => 1.32, 8  => 1.41, 9  => 1.45, 10 => 1.49,
    ];

    private const CR_THRESHOLD = 0.10;

    /**
     * Menghitung Consistency Ratio dari matriks perbandingan untuk sesi tertentu.
     *
     * Kalkulasi menggunakan nilai crisp (tfnM) — bukan nilaiSaaty maupun
     * nilai fuzzy (tfnL/tfnU). Lihat algorithm-implementation.md Stage 3.
     *
     * Output array memuat seluruh data yang dibutuhkan halaman validasi,
     * termasuk breakdown perhitungan untuk transparansi (ditampilkan via
     * expandable section di view).
     *
     * @param SpkMelonSesiPenilaian $sesi
     * @param array $kriteriaList  Array of SpkMelonKriteria objects, ordered (dari PerbandinganService)
     * @return array{
     *   n: int,
     *   kriteriaList: array,
     *   crispMatrix: array,
     *   columnSums: array,
     *   normalizedMatrix: array,
     *   priorityVector: array,
     *   weightedSumVector: array,
     *   consistencyVector: array,
     *   lambdaMax: float,
     *   ci: float,
     *   ri: float,
     *   cr: float,
     *   isConsistent: bool,
     *   isShortCircuit: bool,
     *   shortCircuitReason: ?string
     * }
     */
    public function calculateConsistencyRatio(SpkMelonSesiPenilaian $sesi, array $kriteriaList): array
    {
        $n = count($kriteriaList);

        // ============================================================
        // EDGE CASE: Matriks dengan kurang dari 3 kriteria.
        // ============================================================
        // Tabel Random Index (RI) Saaty mendefinisikan RI(1) = RI(2) = 0.
        // Konsekuensi matematis: jika n < 3, formula CR = CI / RI menghasilkan
        // pembagian dengan nol (undefined).
        //
        // Secara teori AHP, matriks berukuran 1x1 trivial konsisten, dan
        // matriks 2x2 dengan pasangan resiprokal (a, 1/a) SELALU konsisten
        // karena hanya ada satu derajat kebebasan dalam pengisian nilai.
        //
        // Maka untuk n < 3, sistem secara konvensional menetapkan CR = 0
        // dan isConsistent = true, sambil menandai bahwa hasil ini berasal
        // dari short-circuit (bukan perhitungan formula penuh) sehingga
        // view dapat menampilkan informasi tambahan kepada user.
        // ============================================================
        if ($n < 3) {
            return [
                'n'                  => $n,
                'kriteriaList'       => $kriteriaList,
                'crispMatrix'        => $this->buildCrispMatrix($sesi, $kriteriaList),
                'columnSums'         => [],
                'normalizedMatrix'   => [],
                'priorityVector'     => [],
                'weightedSumVector'  => [],
                'consistencyVector'  => [],
                'lambdaMax'          => 0.0,
                'ci'                 => 0.0,
                'ri'                 => 0.0,
                'cr'                 => 0.0,
                'isConsistent'       => true,
                'isShortCircuit'     => true,
                'shortCircuitReason' => 'Matriks dengan kurang dari 3 kriteria (n = ' . $n . ') selalu konsisten secara matematis. '
                                       . 'Nilai CR ditetapkan 0 karena RI(n < 3) = 0, sehingga formula CR = CI / RI tidak dapat dihitung (pembagian dengan nol).',
            ];
        }

        // Langkah 1: Bangun crisp matrix A dari kolom tfnM
        $crispMatrix = $this->buildCrispMatrix($sesi, $kriteriaList);

        // Langkah 2: Hitung column sums
        $columnSums = $this->calculateColumnSums($crispMatrix, $n);

        // Langkah 3: Normalisasi matriks (tiap elemen / column sum)
        $normalizedMatrix = $this->normalizeMatrix($crispMatrix, $columnSums, $n);

        // Langkah 4: Hitung priority vector (rata-rata baris dari normalized matrix)
        $priorityVector = $this->calculatePriorityVector($normalizedMatrix, $n);

        // Langkah 5: Hitung weighted sum vector (A × priority vector)
        $weightedSumVector = $this->multiplyMatrixByVector($crispMatrix, $priorityVector, $n);

        // Langkah 6: Hitung consistency vector (weighted sum / priority vector, elemen per elemen)
        $consistencyVector = [];
        for ($i = 0; $i < $n; $i++) {
            $pv = $priorityVector[$i];
            $consistencyVector[$i] = ($pv > 0)
                ? round($weightedSumVector[$i] / $pv, 6)
                : 0.0;
        }

        // Langkah 7: λmax = rata-rata consistency vector
        $lambdaMax = round(array_sum($consistencyVector) / $n, 6);

        // Langkah 8: CI = (λmax - n) / (n - 1)
        $ci = round(($lambdaMax - $n) / ($n - 1), 6);

        // Langkah 9: RI dari tabel Saaty
        $ri = self::RI_TABLE[$n] ?? 1.49; // fallback ke RI(10) jika n > 10

        // Langkah 10: CR = CI / RI
        $cr = ($ri > 0) ? round($ci / $ri, 6) : 0.0;

        // Langkah 11: isConsistent = (CR < 0.10)
        $isConsistent = $cr < self::CR_THRESHOLD;

        return [
            'n'                  => $n,
            'kriteriaList'       => $kriteriaList,
            'crispMatrix'        => $crispMatrix,
            'columnSums'         => $columnSums,
            'normalizedMatrix'   => $normalizedMatrix,
            'priorityVector'     => $priorityVector,
            'weightedSumVector'  => $weightedSumVector,
            'consistencyVector'  => $consistencyVector,
            'lambdaMax'          => $lambdaMax,
            'ci'                 => $ci,
            'ri'                 => $ri,
            'cr'                 => $cr,
            'isConsistent'       => $isConsistent,
            'isShortCircuit'     => false,
            'shortCircuitReason' => null,
        ];
    }

    /**
     * Menyimpan nilai CR ke kolom rasioKonsistensi pada sesi.
     * Status sesi TIDAK diubah di sini — perubahan status hanya oleh SPK-03 dan SPK-07.
     */
    public function saveConsistencyRatio(SpkMelonSesiPenilaian $sesi, float $cr): void
    {
        $sesi->update(['rasioKonsistensi' => $cr]);
    }

    // ─── PRIVATE HELPERS ────────────────────────────────────────────────────

    /**
     * Bangun crisp matrix n×n dari kolom tfnM pada tabel spk_melon_perbandingan.
     *
     * Mengembalikan array 2D terindeks [i][j] di mana i dan j adalah
     * indeks dari $kriteriaList (0-based). Urutan sesuai dengan urutan
     * $kriteriaList yang sudah diberikan (ordered by kode: C1, C2, ...).
     */
    private function buildCrispMatrix(SpkMelonSesiPenilaian $sesi, array $kriteriaList): array
    {
        // Ambil seluruh perbandingan untuk sesi ini
        $rows = SpkMelonPerbandingan::where('sesiId', $sesi->id)->get();

        // Buat lookup map: "kriteria1Id::kriteria2Id" => tfnM
        $lookup = [];
        foreach ($rows as $row) {
            $key = $row->kriteria1Id . '::' . $row->kriteria2Id;
            $lookup[$key] = (float) $row->tfnM;
        }

        $n = count($kriteriaList);
        $matrix = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $key = $kriteriaList[$i]->id . '::' . $kriteriaList[$j]->id;
                $matrix[$i][$j] = round($lookup[$key] ?? 1.0, 6);
            }
        }

        return $matrix;
    }

    /**
     * Hitung column sums dari crisp matrix.
     * Mengembalikan array [j] = sum of column j.
     */
    private function calculateColumnSums(array $matrix, int $n): array
    {
        $sums = array_fill(0, $n, 0.0);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $sums[$j] = round($sums[$j] + $matrix[$i][$j], 6);
            }
        }

        return $sums;
    }

    /**
     * Normalisasi matriks: tiap elemen dibagi dengan column sum kolom-nya.
     */
    private function normalizeMatrix(array $matrix, array $columnSums, int $n): array
    {
        $normalized = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $sum = $columnSums[$j];
                $normalized[$i][$j] = ($sum > 0)
                    ? round($matrix[$i][$j] / $sum, 6)
                    : 0.0;
            }
        }

        return $normalized;
    }

    /**
     * Hitung priority vector: rata-rata setiap baris dari normalized matrix.
     * Mengembalikan array [i] = average of row i.
     */
    private function calculatePriorityVector(array $normalizedMatrix, int $n): array
    {
        $priorityVector = [];

        for ($i = 0; $i < $n; $i++) {
            $rowSum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $rowSum += $normalizedMatrix[$i][$j];
            }
            $priorityVector[$i] = round($rowSum / $n, 6);
        }

        return $priorityVector;
    }

    /**
     * Kalikan matriks dengan vektor (A × v).
     * Mengembalikan array [i] = dot product of row i with vector v.
     */
    private function multiplyMatrixByVector(array $matrix, array $vector, int $n): array
    {
        $result = [];

        for ($i = 0; $i < $n; $i++) {
            $sum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $sum += $matrix[$i][$j] * $vector[$j];
            }
            $result[$i] = round($sum, 6);
        }

        return $result;
    }
}
