<?php

namespace App\Http\Controllers\SPKMelon;

use App\Models\SPKMelon\SpkMelonSesiPenilaian;
use App\Services\SPKMelon\FuzzyAhpService;
use App\Services\SPKMelon\PerbandinganService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Controller untuk Kalkulasi Bobot Kriteria Fuzzy AHP (SPK-05).
 *
 * - GET (show): Lazy calculate pada akses pertama, ambil existing di akses berikutnya
 * - POST (calculate): Re-kalkulasi (hanya inventor/admin), soft delete + insert baru
 *
 * Pre-condition chain (sesuai planning document Bagian 3):
 * 1. Sesi harus exist dan aktif (not soft-deleted)
 * 2. Status sesi harus 'proses' atau 'selesai'
 * 3. Record perbandingan berpasangan harus ada (SPK-03)
 * 4. CR harus sudah dihitung dan konsisten (SPK-04)
 * 5. POST: hanya role inventor/admin yang boleh trigger re-calculation
 */
class BobotController extends Controller
{
    /**
     * Tampilkan halaman bobot kriteria (GET).
     *
     * - First access (belum ada bobot): auto-calculate + save + display
     * - Subsequent access: ambil dari DB, display tanpa re-calculate
     */
    public function show(string $id)
    {
        $fuzzyAhpService = new FuzzyAhpService();
        $perbandinganService = new PerbandinganService();

        // Pre-condition 1: Sesi harus exist
        $sesi = SpkMelonSesiPenilaian::find($id);
        if (! $sesi) {
            abort(404, 'Sesi penilaian tidak ditemukan.');
        }

        // Pre-condition 2: Status sesi minimal 'proses'
        if (! in_array($sesi->status, ['proses', 'selesai'], true)) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.show', $sesi->id)
                ->with('error', 'Sesi belum memiliki data perbandingan. Selesaikan input perbandingan (SPK-03) terlebih dahulu.');
        }

        // Pre-condition 3: Perbandingan harus ada
        if (! $sesi->perbandingan()->exists()) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.show', $sesi->id)
                ->with('error', 'Data matriks perbandingan berpasangan belum tersedia. Lengkapi SPK-03 terlebih dahulu.');
        }

        // Pre-condition 4: CR harus konsisten
        $kriteriaList = $perbandinganService->getKriteriaForSesi($sesi);
        $kriteriaArray = $kriteriaList->values()->all();
        $crResult = $fuzzyAhpService->calculateConsistencyRatio($sesi, $kriteriaArray);

        if (! $crResult['isConsistent']) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.validasi-konsistensi.show', $sesi->id)
                ->with('error', 'Matriks perbandingan belum konsisten (CR = ' . number_format($crResult['cr'], 4) . '). Revisi perbandingan terlebih dahulu.');
        }

        // Hybrid mode: cek apakah bobot sudah ada di DB
        $existingWeights = $fuzzyAhpService->getExistingWeights($sesi);

        // SELALU jalankan calculateWeights() untuk mendapat data intermediate lengkap
        // (fuzzyMatrix, geometricMeans) yang dibutuhkan breakdown Tahap 1 & 2 di view.
        // Data ini TIDAK disimpan di DB, sehingga harus di-generate ulang dari matrix.
        $result = $fuzzyAhpService->calculateWeights($sesi, $kriteriaArray);

        if ($existingWeights) {
            // Data sudah ada di DB: override fuzzyWeights & normalizedWeights
            // dengan nilai tersimpan (audit trail) agar konsisten dengan history.
            $isFromDb = true;

            // Sort existing weights sesuai urutan kriteriaList
            $sortedWeights = $existingWeights->sortBy(function ($bobot) use ($kriteriaArray) {
                foreach ($kriteriaArray as $i => $k) {
                    if ($k->id === $bobot->kriteriaId) {
                        return $i;
                    }
                }
                return PHP_INT_MAX;
            })->values();

            // Override dengan nilai dari DB
            $fuzzyWeightsFromDb   = [];
            $normalizedFromDb     = [];
            foreach ($sortedWeights as $i => $bobot) {
                $fuzzyWeightsFromDb[$i] = [
                    'l' => (float) $bobot->bobotFuzzyL,
                    'm' => (float) $bobot->bobotFuzzyM,
                    'u' => (float) $bobot->bobotFuzzyU,
                ];
                $normalizedFromDb[$i] = (float) $bobot->bobotAkhir;
            }

            // Rekonstruksi crisp weights dari fuzzy weights DB
            $crispFromDb = [];
            foreach ($fuzzyWeightsFromDb as $i => $fw) {
                $crispFromDb[$i] = round(($fw['l'] + $fw['m'] + $fw['u']) / 3, 6);
            }

            $result['fuzzyWeights']      = $fuzzyWeightsFromDb;
            $result['normalizedWeights'] = $normalizedFromDb;
            $result['crispWeights']      = $crispFromDb;
            $result['sumCrispWeights']   = round(array_sum($crispFromDb), 6);
        } else {
            // First access — simpan ke DB
            $isFromDb = false;
            $fuzzyAhpService->saveWeights($sesi, $kriteriaArray, $result);
        }

        // Determine read-only mode
        $userRole = session('user')['role'] ?? '';
        $isReadOnly = ! in_array($userRole, ['inventor', 'admin'], true);

        return view('spk-melon.bobot.show', [
            'sesi'       => $sesi,
            'result'     => $result,
            'isFromDb'   => $isFromDb,
            'isReadOnly' => $isReadOnly,
            'cr'         => $crResult['cr'],
        ]);
    }

    /**
     * Re-kalkulasi bobot (POST).
     *
     * Hanya inventor/admin yang boleh trigger.
     * Soft delete row lama → insert baru.
     */
    public function calculate(string $id)
    {
        $fuzzyAhpService = new FuzzyAhpService();
        $perbandinganService = new PerbandinganService();

        // Pre-condition 1: Sesi harus exist
        $sesi = SpkMelonSesiPenilaian::find($id);
        if (! $sesi) {
            abort(404, 'Sesi penilaian tidak ditemukan.');
        }

        // Pre-condition 2: Status sesi minimal 'proses'
        if (! in_array($sesi->status, ['proses', 'selesai'], true)) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.show', $sesi->id)
                ->with('error', 'Status sesi tidak memenuhi syarat untuk kalkulasi bobot.');
        }

        // Pre-condition 3: Perbandingan harus ada
        if (! $sesi->perbandingan()->exists()) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.show', $sesi->id)
                ->with('error', 'Data matriks perbandingan berpasangan belum tersedia.');
        }

        // Pre-condition 4: CR harus konsisten
        $kriteriaList = $perbandinganService->getKriteriaForSesi($sesi);
        $kriteriaArray = $kriteriaList->values()->all();
        $crResult = $fuzzyAhpService->calculateConsistencyRatio($sesi, $kriteriaArray);

        if (! $crResult['isConsistent']) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.validasi-konsistensi.show', $sesi->id)
                ->with('error', 'Matriks belum konsisten. CR = ' . number_format($crResult['cr'], 4));
        }

        // Pre-condition 5: Authorization (hanya inventor/admin)
        $userRole = session('user')['role'] ?? '';
        if (! in_array($userRole, ['inventor', 'admin'], true)) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.bobot-kriteria.show', $sesi->id)
                ->with('error', 'Anda tidak memiliki izin untuk melakukan kalkulasi ulang.');
        }

        // Execute: calculate + save (soft delete old + insert new)
        $result = $fuzzyAhpService->calculateWeights($sesi, $kriteriaArray);
        $fuzzyAhpService->saveWeights($sesi, $kriteriaArray, $result);

        return redirect()
            ->route('spk-melon.sesi-penilaian.bobot-kriteria.show', $sesi->id)
            ->with('success', 'Bobot kriteria berhasil dihitung ulang.');
    }

}

