<?php

namespace App\Http\Controllers\SPKMelon;

use App\Http\Controllers\Controller;
use App\Http\Requests\SPKMelon\StorePerbandinganRequest;
use App\Models\SPKMelon\SpkMelonSesiPenilaian;
use App\Services\SPKMelon\PerbandinganService;
use App\Services\SPKMelon\TfnHelper;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Controller untuk SPK-03: Input Perbandingan Berpasangan.
 *
 * Tidak ada constructor middleware — mengandalkan route-level middleware
 * 'auth.api' + 'role:inventor,admin,pjawab,petugas' yang sudah diatur
 * di routes/web.php. Authorization granular di-handle oleh Form Request
 * dan logic isReadOnly di edit().
 */
class PerbandinganController extends Controller
{
    public function __construct(
        protected PerbandinganService $perbandinganService
    ) {
        // Tidak perlu constructor middleware — menggunakan route-level middleware.
    }

    /**
     * SPK-03: Tampilkan halaman matriks perbandingan berpasangan.
     *
     * Mode:
     *  - Sesi status 'draft'  → Matriks kosong, editable
     *  - Sesi status 'proses' → Matriks terisi dari row lama, editable
     *  - Sesi status 'selesai'/'gagal' → Matriks terisi, READ-ONLY
     *  - Role 'pjawab' → READ-ONLY apapun status sesinya
     */
    public function edit(string $id): View
    {
        // SCOPE SPK-03: Hanya tampilkan matriks, tidak ada kalkulasi CR di sini.
        $sesi = SpkMelonSesiPenilaian::findOrFail($id);
        $kriteriaList = $this->perbandinganService->getKriteriaForSesi($sesi);

        // Validasi minimal jumlah kriteria
        if ($kriteriaList->count() < 2) {
            return view('spk-melon.perbandingan.edit', [
                'sesi'           => $sesi,
                'kriteriaList'   => $kriteriaList,
                'existingMatrix' => [],
                'saatyOptions'   => $this->buildSaatyOptions(),
                'isReadOnly'     => true,
                'errorMessage'   => 'Sesi ini memiliki kurang dari 2 kriteria. Tambah kriteria di SPK-01 terlebih dahulu.',
            ]);
        }

        $existingMatrix = $this->perbandinganService->getExistingMatrix($sesi);

        $userRole = session('user')['role'] ?? null;
        $isReadOnly = ! $sesi->isPerbandinganEditable() || $userRole === 'pjawab';

        return view('spk-melon.perbandingan.edit', [
            'sesi'           => $sesi,
            'kriteriaList'   => $kriteriaList,
            'existingMatrix' => $existingMatrix,
            'saatyOptions'   => $this->buildSaatyOptions(),
            'isReadOnly'     => $isReadOnly,
            'errorMessage'   => null,
        ]);
    }

    /**
     * SPK-03: Simpan matriks perbandingan berpasangan.
     *
     * Side effect:
     *  - Transisi status sesi: 'draft' → 'proses' (otomatis di service)
     *  - Reset rasioKonsistensi sesi menjadi NULL
     *
     * Setelah simpan sukses: redirect ke halaman detail sesi (SPK-02 show)
     * dengan flash message. CR validation ditunda ke SPK-04.
     */
    public function update(
        StorePerbandinganRequest $request,
        string $id
    ): RedirectResponse {
        $sesi = SpkMelonSesiPenilaian::findOrFail($id);
        $kriteriaList = $this->perbandinganService->getKriteriaForSesi($sesi);
        $upperTriangle = $request->validated()['perbandingan'];

        try {
            $this->perbandinganService->saveMatrix($sesi, $kriteriaList->all(), $upperTriangle);
        } catch (\Throwable $e) {
            return back()
                ->withInput()
                ->with('error', 'Gagal menyimpan matriks perbandingan: ' . $e->getMessage());
        }

        // ============================================================
        // TODO: SPK-04 (UPDATE WAJIB SAAT SPK-04 SUDAH DIIMPLEMENTASI)
        // ============================================================
        // Target redirect FINAL setelah SPK-04 selesai:
        //
        //   return redirect()
        //       ->route('spk-melon.sesi-penilaian.validasi-konsistensi.show', $sesi->id)
        //       ->with('success', 'Matriks perbandingan berhasil disimpan. Memvalidasi konsistensi matriks...');
        //
        // Saat ini (SPK-04 belum ada), gunakan FALLBACK SEMENTARA ke halaman detail
        // sesi agar tidak terjadi RouteNotFoundException. AI Agent yang mengerjakan
        // SPK-04 WAJIB:
        //   1. Hapus blok fallback di bawah ini.
        //   2. Aktifkan blok target redirect FINAL di atas.
        //   3. Pastikan route name 'spk-melon.sesi-penilaian.validasi-konsistensi.show'
        //      sudah terdaftar di routes/web.php.
        // ------------------------------------------------------------
        // [FALLBACK SEMENTARA — HAPUS SAAT SPK-04 SELESAI]
        return redirect()
            ->route('spk-melon.sesi-penilaian.show', $sesi->id)
            ->with('success', 'Matriks perbandingan berhasil disimpan. Halaman validasi konsistensi (SPK-04) sedang dalam pengembangan.');
        // ============================================================
    }

    /**
     * Bangun array opsi untuk dropdown Skala Saaty (untuk view).
     */
    private function buildSaatyOptions(): array
    {
        $options = [];

        // Urutan: 1, 2, 3, ..., 9, lalu 1/2, 1/3, ..., 1/9
        $integers = [1, 2, 3, 4, 5, 6, 7, 8, 9];
        foreach ($integers as $val) {
            $options[] = [
                'value' => $val,
                'label' => $val . ' - ' . TfnHelper::labelVerbal($val),
            ];
        }

        $reciprocals = [2, 3, 4, 5, 6, 7, 8, 9];
        foreach ($reciprocals as $val) {
            $options[] = [
                'value' => round(1 / $val, 6),
                'label' => '1/' . $val . ' - ' . TfnHelper::labelVerbal(1 / $val),
            ];
        }

        return $options;
    }
}
