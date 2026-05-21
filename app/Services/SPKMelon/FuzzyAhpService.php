<?php

namespace App\Services\SPKMelon;

use App\Models\SPKMelon\SpkMelonBobot;
use App\Models\SPKMelon\SpkMelonKriteria;
use App\Models\SPKMelon\SpkMelonPerbandingan;
use App\Models\SPKMelon\SpkMelonSesiPenilaian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service untuk kalkulasi Fuzzy AHP.
 *
 * Stage 2: Pairwise comparison input (dilakukan di PerbandinganService — SPK-03)
 * Stage 3: Consistency Ratio validation (SPK-04)
 * Stage 4: Weight calculation (SPK-05)
 *
 * @see docs/references/algorithm-implementation.md Stage 3-4
 */
class FuzzyAhpService
{
    /**
     * Batas bawah jumlah kriteria yang diizinkan (sesuai zona standar AHP).
     * Saaty (1980) merekomendasikan n >= 5 untuk threshold CR < 0.10 standar.
     * Untuk n = 3 atau 4 dibutuhkan threshold lebih ketat (0.05 atau 0.08)
     * yang menambah kompleksitas implementasi. Sistem memilih range standar
     * tanpa threshold adaptif untuk kesederhanaan UX.
     */
    public const MIN_KRITERIA = 5;

    /**
     * Batas atas jumlah kriteria yang diizinkan (sesuai zona standar AHP).
     * Miller (1956) "magical number 7±2" — kapasitas memori kerja manusia
     * untuk diskriminasi pairwise comparison. Di atas 9 kriteria, pakar
     * mulai kesulitan menjaga konsistensi dan CR cenderung tinggi.
     */
    public const MAX_KRITERIA = 9;

    /**
     * Tabel Random Index (RI) dari Saaty (1980).
     * Hanya menyimpan range valid sistem (5-9) sesuai zona standar AHP.
     * Entry untuk n = 1, 2, 3, 4, 10+ dihapus karena tidak relevan pasca-v1.1.
     */
    private const RI_TABLE = [
        5 => 1.12, 6 => 1.24, 7 => 1.32, 8 => 1.41, 9 => 1.45,
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
     *   isConsistent: bool
     * }
     *
     * @throws \DomainException jika jumlah kriteria di luar range valid (5-9)
     */
    public function calculateConsistencyRatio(SpkMelonSesiPenilaian $sesi, array $kriteriaList): array
    {
        $n = count($kriteriaList);

        // Guard: range valid zona standar AHP (5 ≤ n ≤ 9).
        // Validasi seharusnya sudah dilakukan di SPK-01 dan SPK-02.
        // Guard ini adalah defense in depth untuk bypass via direct DB manipulation.
        if ($n < self::MIN_KRITERIA || $n > self::MAX_KRITERIA) {
            throw new \DomainException(
                "Jumlah kriteria di luar range valid (" . self::MIN_KRITERIA . "-" . self::MAX_KRITERIA . "). Saat ini n = {$n}."
            );
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
        // Sudah dijamin valid oleh guard di awal method (n ∈ [5, 9])
        $ri = self::RI_TABLE[$n];

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

    // ─── SPK-05: KALKULASI BOBOT FUZZY AHP ──────────────────────────────

    /**
     * Menghitung bobot prioritas kriteria menggunakan Fuzzy AHP.
     *
     * Method ini PURE FUNCTION — tidak melakukan operasi tulis ke database.
     * Seluruh hasil kalkulasi dikembalikan sebagai array untuk digunakan
     * oleh controller (display) dan saveWeights() (persist).
     *
     * Algoritma sesuai algorithm-implementation.md Stage 4 (Buckley 1985):
     * 1. Bangun fuzzy matrix n×n dari TFN perbandingan
     * 2. Hitung Fuzzy Geometric Mean per baris
     * 3. Fuzzy Synthesis (pembagian silang L/U — metode Buckley)
     * 4. Defuzzifikasi Center of Area
     * 5. Normalisasi akhir (Σ W = 1.0)
     *
     * @param SpkMelonSesiPenilaian $sesi
     * @param array $kriteriaList Array of SpkMelonKriteria, ordered by kode
     * @return array{
     *   n: int,
     *   kriteriaList: array,
     *   fuzzyMatrix: array,
     *   geometricMeans: array,
     *   sumGeometricMean: array,
     *   fuzzyWeights: array,
     *   crispWeights: array,
     *   sumCrispWeights: float,
     *   normalizedWeights: array
     * }
     */
    public function calculateWeights(SpkMelonSesiPenilaian $sesi, array $kriteriaList): array
    {
        $n = count($kriteriaList);

        // Guard: range valid zona standar AHP (5 ≤ n ≤ 9).
        // Sama dengan guard di calculateConsistencyRatio() — defense in depth.
        if ($n < self::MIN_KRITERIA || $n > self::MAX_KRITERIA) {
            throw new \DomainException(
                "Jumlah kriteria di luar range valid (" . self::MIN_KRITERIA . "-" . self::MAX_KRITERIA . "). Saat ini n = {$n}."
            );
        }

        // Langkah 1: Bangun fuzzy matrix n×n
        $fuzzyMatrix = $this->buildFuzzyMatrix($sesi, $kriteriaList);

        // Langkah 2: Fuzzy Geometric Mean per baris
        $geometricMeans = $this->calculateGeometricMeans($fuzzyMatrix, $n);

        // Langkah 3: Fuzzy Synthesis (Buckley 1985)
        $synthesis = $this->calculateFuzzySynthesis($geometricMeans, $n);

        // Langkah 4: Defuzzifikasi Center of Area
        $crispWeights = $this->defuzzifyWeights($synthesis['weights'], $n);

        // Langkah 5: Normalisasi akhir
        $normalization = $this->normalizeFinalWeights($crispWeights, $n);

        return [
            'n'                 => $n,
            'kriteriaList'      => $kriteriaList,
            'fuzzyMatrix'       => $fuzzyMatrix,
            'geometricMeans'    => $geometricMeans,
            'sumGeometricMean'  => $synthesis['sum'],
            'fuzzyWeights'      => $synthesis['weights'],
            'crispWeights'      => $crispWeights,
            'sumCrispWeights'   => $normalization['sum'],
            'normalizedWeights' => $normalization['normalized'],
        ];
    }

    /**
     * Menyimpan bobot ke tabel spk_melon_bobot dengan pattern soft delete + insert.
     *
     * Operasi wajib di dalam DB::transaction() untuk konsistensi:
     * 1. Soft delete row lama (audit trail terjaga)
     * 2. Insert row baru dengan hasil kalkulasi terkini
     *
     * @param SpkMelonSesiPenilaian $sesi
     * @param array $kriteriaList Array of SpkMelonKriteria, ordered by kode
     * @param array $result Output dari calculateWeights()
     */
    public function saveWeights(SpkMelonSesiPenilaian $sesi, array $kriteriaList, array $result): void
    {
        DB::transaction(function () use ($sesi, $kriteriaList, $result) {
            // 1. Soft delete row lama (audit trail terjaga)
            SpkMelonBobot::where('sesiId', $sesi->id)
                ->where('isDeleted', 0)
                ->update(['isDeleted' => 1, 'updatedAt' => now()]);

            // 2. Build dan insert row baru
            $rows = [];
            $now = now();
            foreach ($kriteriaList as $i => $kriteria) {
                $rows[] = [
                    'id'          => (string) Str::uuid(),
                    'sesiId'      => $sesi->id,
                    'kriteriaId'  => $kriteria->id,
                    'bobotFuzzyL' => $result['fuzzyWeights'][$i]['l'],
                    'bobotFuzzyM' => $result['fuzzyWeights'][$i]['m'],
                    'bobotFuzzyU' => $result['fuzzyWeights'][$i]['u'],
                    'bobotAkhir'  => $result['normalizedWeights'][$i],
                    'isDeleted'   => 0,
                    'createdAt'   => $now,
                    'updatedAt'   => $now,
                ];
            }
            SpkMelonBobot::insert($rows);
        });
    }

    /**
     * Mengambil bobot tersimpan untuk sesi tertentu (hybrid mode).
     *
     * Mengembalikan Collection yang sudah eager-load relasi kriteria,
     * atau null jika belum ada bobot yang dihitung.
     *
     * @param SpkMelonSesiPenilaian $sesi
     * @return Collection|null
     */
    public function getExistingWeights(SpkMelonSesiPenilaian $sesi): ?Collection
    {
        $bobot = SpkMelonBobot::where('sesiId', $sesi->id)
            ->with('kriteria')
            ->get();

        return $bobot->isEmpty() ? null : $bobot;
    }

    // ─── PRIVATE HELPERS (SPK-04: Consistency Ratio) ────────────────────

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

    // ─── PRIVATE HELPERS (SPK-05: Fuzzy AHP Weights) ────────────────────

    /**
     * Bangun fuzzy matrix n×n dari TFN (tfnL, tfnM, tfnU) di spk_melon_perbandingan.
     *
     * Setiap sel [i][j] berisi TFN associative array ['l' => x, 'm' => y, 'u' => z].
     * Urutan baris/kolom mengikuti $kriteriaList (ordered by kode).
     *
     * @return array 2D fuzzy matrix [i][j] => ['l', 'm', 'u']
     */
    private function buildFuzzyMatrix(SpkMelonSesiPenilaian $sesi, array $kriteriaList): array
    {
        $rows = SpkMelonPerbandingan::where('sesiId', $sesi->id)->get();

        // Buat lookup map: "kriteria1Id::kriteria2Id" => ['l', 'm', 'u']
        $lookup = [];
        foreach ($rows as $row) {
            $key = $row->kriteria1Id . '::' . $row->kriteria2Id;
            $lookup[$key] = [
                'l' => (float) $row->tfnL,
                'm' => (float) $row->tfnM,
                'u' => (float) $row->tfnU,
            ];
        }

        $n = count($kriteriaList);
        $matrix = [];

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $key = $kriteriaList[$i]->id . '::' . $kriteriaList[$j]->id;
                $matrix[$i][$j] = $lookup[$key] ?? ['l' => 1.0, 'm' => 1.0, 'u' => 1.0];
            }
        }

        return $matrix;
    }

    /**
     * Menghitung Fuzzy Geometric Mean per baris matriks fuzzy.
     *
     * Untuk setiap baris i:
     *   prod = ∏ fuzzyMatrix[i][j] untuk j = 0..n-1 (TFN element-wise)
     *   r_i = prod^(1/n) (akar pangkat n, TFN element-wise)
     *
     * @return array [i] => ['l' => x, 'm' => y, 'u' => z]
     */
    private function calculateGeometricMeans(array $fuzzyMatrix, int $n): array
    {
        $geometricMeans = [];

        for ($i = 0; $i < $n; $i++) {
            // Inisialisasi produk akumulatif sebagai [1, 1, 1]
            $prod = [1.0, 1.0, 1.0];

            for ($j = 0; $j < $n; $j++) {
                $tfn = $fuzzyMatrix[$i][$j];
                $prod = TfnHelper::multiply($prod, [$tfn['l'], $tfn['m'], $tfn['u']]);
            }

            // Akar pangkat n
            $root = TfnHelper::nthRoot($prod, $n);

            $geometricMeans[$i] = [
                'l' => $root[0],
                'm' => $root[1],
                'u' => $root[2],
            ];
        }

        return $geometricMeans;
    }

    /**
     * Menghitung Fuzzy Synthesis (metode Buckley 1985).
     *
     * 1. Hitung S = Σ r_i (jumlahkan semua geometric mean, TFN-wise)
     * 2. Untuk setiap kriteria i, hitung bobot fuzzy:
     *    w_i.l = r_i.l / S.u  (pembagian silang — bukan typo!)
     *    w_i.m = r_i.m / S.m
     *    w_i.u = r_i.u / S.l  (pembagian silang — bukan typo!)
     *
     * Catatan: Pembagian silang L/U dan U/L adalah metode Buckley (1985)
     * untuk menjaga sifat TFN setelah pembagian fuzzy.
     *
     * @return array{sum: array, weights: array}
     * @throws \RuntimeException jika S.l = 0
     */
    private function calculateFuzzySynthesis(array $geometricMeans, int $n): array
    {
        // Hitung S = Σ r_i
        $sumL = 0.0;
        $sumM = 0.0;
        $sumU = 0.0;

        foreach ($geometricMeans as $gm) {
            $sumL = round($sumL + $gm['l'], 6);
            $sumM = round($sumM + $gm['m'], 6);
            $sumU = round($sumU + $gm['u'], 6);
        }

        // Guard: S.l tidak boleh nol
        if ($sumL == 0) {
            throw new \RuntimeException(
                'Sum geometric mean lower bound zero, kalkulasi sintesis tidak dapat dilanjutkan.'
            );
        }

        $sum = ['l' => $sumL, 'm' => $sumM, 'u' => $sumU];

        // Hitung bobot fuzzy per kriteria (pembagian silang Buckley)
        $weights = [];
        for ($i = 0; $i < $n; $i++) {
            $weights[$i] = [
                'l' => round($geometricMeans[$i]['l'] / $sumU, 6),
                'm' => round($geometricMeans[$i]['m'] / $sumM, 6),
                'u' => round($geometricMeans[$i]['u'] / $sumL, 6),
            ];
        }

        return [
            'sum'     => $sum,
            'weights' => $weights,
        ];
    }

    /**
     * Menghitung defuzzifikasi Center of Area untuk setiap bobot fuzzy.
     *
     * W_i = (w_i.l + w_i.m + w_i.u) / 3
     *
     * @return array [i] => float (crisp weight sebelum normalisasi)
     */
    private function defuzzifyWeights(array $fuzzyWeights, int $n): array
    {
        $crispWeights = [];

        for ($i = 0; $i < $n; $i++) {
            $crispWeights[$i] = TfnHelper::defuzzify([
                $fuzzyWeights[$i]['l'],
                $fuzzyWeights[$i]['m'],
                $fuzzyWeights[$i]['u'],
            ]);
        }

        return $crispWeights;
    }

    /**
     * Normalisasi bobot crisp agar Σ W_final = 1.0.
     *
     * W_final_i = W_i / Σ W_j
     *
     * @return array{sum: float, normalized: array}
     */
    private function normalizeFinalWeights(array $crispWeights, int $n): array
    {
        $sumCrisp = round(array_sum($crispWeights), 6);

        $normalized = [];
        for ($i = 0; $i < $n; $i++) {
            $normalized[$i] = ($sumCrisp > 0)
                ? round($crispWeights[$i] / $sumCrisp, 6)
                : 0.0;
        }

        return [
            'sum'        => $sumCrisp,
            'normalized' => $normalized,
        ];
    }
}

