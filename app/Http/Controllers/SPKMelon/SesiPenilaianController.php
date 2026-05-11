<?php

namespace App\Http\Controllers\SPKMelon;

use App\Http\Controllers\Controller;
use App\Http\Requests\SPKMelon\StoreSesiPenilaianRequest;
use App\Models\SPKMelon\SpkMelonKriteria;
use App\Models\SPKMelon\SpkMelonSesiPenilaian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

use Illuminate\View\View;

/*
 * SCOPE SPK-02:
 * - CREATE sesi baru (status awal: 'draft')
 * - READ sesi (index listing dengan filter, show detail)
 * - DELETE sesi (soft delete, HANYA untuk sesi berstatus 'draft')
 *
 * OUT-OF-SCOPE (akan dikerjakan di sprint berikutnya):
 * - UPDATE sesi (edit nama, tipe, periode)
 * - Transisi status:
 *   - 'draft' -> 'proses' akan ditangani oleh SPK-03 (saat user mulai input perbandingan)
 *   - 'proses' -> 'selesai' akan ditangani oleh SPK-07 (saat TOPSIS selesai dengan CR < 0.1)
 *   - 'proses' -> 'gagal' akan ditangani oleh SPK-04 atau SPK-07 (jika validation/computation gagal)
 */
class SesiPenilaianController extends Controller
{
    /**
     * Halaman listing sesi evaluasi (Level 2).
     * Mendukung filter: tipe evaluasi, status (4 nilai), rentang periode.
     */
    public function index(Request $request): View
    {
        $query = SpkMelonSesiPenilaian::with('penilai')
            ->orderBy('createdAt', 'desc');

        // Filter: tipe evaluasi
        if ($request->filled('tipeEvaluasi')) {
            $query->where('tipeEvaluasi', $request->input('tipeEvaluasi'));
        }

        // Filter: status (draft, proses, selesai, gagal)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter: rentang periode (mencari sesi yang periodenya overlap dengan filter)
        if ($request->filled('periodeMulai')) {
            $query->where('periodeSelesai', '>=', $request->input('periodeMulai'));
        }
        if ($request->filled('periodeSelesai')) {
            $query->where('periodeMulai', '<=', $request->input('periodeSelesai'));
        }

        $sesiList = $query->paginate(10)->withQueryString();

        return view('spk-melon.sesi-penilaian.index', compact('sesiList'));
    }

    /**
     * Halaman detail sesi (Level 3).
     * Menampilkan info sesi + preview kriteria yang ter-load.
     */
    public function show(string $id): View
    {
        $sesi = SpkMelonSesiPenilaian::with('penilai')->findOrFail($id);

        // Ambil kriteria yang sesuai tipe evaluasi sesi (untuk preview)
        $kriteriaList = $this->getKriteriaByTipe($sesi->tipeEvaluasi);

        return view('spk-melon.sesi-penilaian.show', compact('sesi', 'kriteriaList'));
    }

    /*
     * CATATAN PERIODE TIPIKAL (Soft Warning):
     * - Tipe 'produktivitas': periode tipikal 60-90 hari (siklus tanam melon penuh).
     * - Tipe 'kualitas': periode tipikal 1-7 hari (pengukuran pasca-panen).
     * Sistem TIDAK MENOLAK input di luar rentang ini. Hanya menampilkan warning
     * via JavaScript confirm() di sisi client agar user yang sedang melakukan
     * eksperimen riset tetap bisa menyimpan sesi dengan periode non-standar.
     */
    public function store(StoreSesiPenilaianRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $sesi = SpkMelonSesiPenilaian::create([
            'namaSesi' => $validated['namaSesi'],
            'tipeEvaluasi' => $validated['tipeEvaluasi'],
            'periodeMulai' => $validated['periodeMulai'],
            'periodeSelesai' => $validated['periodeSelesai'],
            'catatanSesi' => $validated['catatanSesi'] ?? null,
            'dinilaiOleh' => session('user')['id'] ?? null,
            'status' => 'draft',
        ]);

        return redirect()
            ->route('spk-melon.sesi-penilaian.show', $sesi->id)
            ->with('success', 'Sesi evaluasi berhasil dibuat.');
    }

    /**
     * Soft delete sesi. HANYA untuk sesi berstatus 'draft'.
     * Sesi 'proses', 'selesai', atau 'gagal' tidak dapat dihapus.
     */
    public function destroy(string $id): RedirectResponse
    {
        $sesi = SpkMelonSesiPenilaian::findOrFail($id);

        // Guard: hanya sesi 'draft' yang boleh dihapus
        if ($sesi->status !== 'draft') {
            return back()->with('error', 'Sesi yang sedang diproses, sudah selesai, atau gagal tidak dapat dihapus.');
        }

        $sesi->update(['isDeleted' => 1]);

        return redirect()
            ->route('spk-melon.sesi-penilaian.index')
            ->with('success', 'Sesi evaluasi berhasil dihapus.');
    }

    /**
     * Helper privat: ambil kriteria sesuai tipe evaluasi.
     */
    private function getKriteriaByTipe(string $tipe): \Illuminate\Database\Eloquent\Collection
    {
        return SpkMelonKriteria::where(function ($q) use ($tipe) {
            $q->where('kategori', $tipe)
                ->orWhere('kategori', 'lingkungan');
        })
            ->orderBy('kode', 'asc')
            ->get();
    }
}
