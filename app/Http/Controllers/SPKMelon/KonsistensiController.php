<?php

namespace App\Http\Controllers\SPKMelon;

use App\Http\Controllers\Controller;
use App\Models\SPKMelon\SpkMelonSesiPenilaian;
use App\Services\SPKMelon\FuzzyAhpService;
use App\Services\SPKMelon\PerbandinganService;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * Controller untuk SPK-04: Validasi Consistency Ratio.
 *
 * Halaman ini menampilkan hasil kalkulasi CR untuk matriks perbandingan
 * yang sudah disimpan di SPK-03. Kalkulasi dilakukan lazy setiap kali
 * halaman diakses, dan nilai CR disimpan ke kolom rasioKonsistensi.
 *
 * Route-level middleware: 'auth.api' + 'role:inventor,admin,pjawab,petugas'
 * (sudah diatur di routes/web.php). Authorization granular (read-only
 * untuk pjawab/petugas) di-handle di view via session('user')['role'].
 */
class KonsistensiController extends Controller
{
    public function __construct(
        protected FuzzyAhpService    $fuzzyAhpService,
        protected PerbandinganService $perbandinganService
    ) {}

    /**
     * Halaman validasi Consistency Ratio.
     *
     * Alur:
     *  1. Ambil sesi berdasarkan ID.
     *  2. Validasi pre-condition: sesi harus punya record di spk_melon_perbandingan.
     *  3. Ambil daftar kriteria terurut (untuk membangun matriks dengan urutan konsisten).
     *  4. Jalankan FuzzyAhpService::calculateConsistencyRatio() untuk dapatkan hasil.
     *  5. Simpan CR ke kolom rasioKonsistensi via FuzzyAhpService::saveConsistencyRatio().
     *  6. Tentukan isReadOnly berdasarkan role dan status sesi.
     *  7. Return view dengan seluruh data perhitungan.
     */
    public function show(string $id): View|RedirectResponse
    {
        $sesi = SpkMelonSesiPenilaian::findOrFail($id);

        // Pre-condition: sesi harus punya matriks perbandingan
        if (! $sesi->perbandingan()->exists()) {
            return redirect()
                ->route('spk-melon.sesi-penilaian.show', $sesi->id)
                ->with('error', 'Matriks perbandingan belum diisi. Silakan isi matriks terlebih dahulu pada halaman SPK-03.');
        }

        // Ambil daftar kriteria terurut (by kode: C1, C2, ...) — konsisten dengan SPK-03
        $kriteriaList = $this->perbandinganService->getKriteriaForSesi($sesi)->all();

        // Jalankan kalkulasi CR
        try {
            $result = $this->fuzzyAhpService->calculateConsistencyRatio($sesi, $kriteriaList);
        } catch (\Throwable $e) {
            Log::error('[SPK-04] Gagal menghitung Consistency Ratio', [
                'sesiId' => $sesi->id,
                'error'  => $e->getMessage(),
            ]);

            return redirect()
                ->route('spk-melon.sesi-penilaian.show', $sesi->id)
                ->with('error', 'Gagal menghitung Consistency Ratio. Silakan coba lagi atau hubungi administrator.');
        }

        // Simpan nilai CR ke database (terlepas dari konsisten atau tidak)
        $this->fuzzyAhpService->saveConsistencyRatio($sesi, $result['cr']);

        // Tentukan mode read-only:
        // - Pjawab dan petugas selalu read-only
        // - Sesi berstatus selesai/gagal juga read-only (audit mode)
        $userRole   = session('user')['role'] ?? null;
        $isReadOnly = in_array($userRole, ['pjawab', 'petugas'], true)
                      || in_array($sesi->status, ['selesai', 'gagal'], true);

        return view('spk-melon.validasi-konsistensi.show', [
            'sesi'       => $sesi,
            'result'     => $result,
            'isReadOnly' => $isReadOnly,
            'userRole'   => $userRole,
        ]);
    }
}
